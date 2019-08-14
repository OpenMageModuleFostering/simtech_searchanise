<?php
/***************************************************************************
*                                                                          *
*   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
*                                                                          *
****************************************************************************
* PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
* "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
****************************************************************************/

class Simtech_Searchanise_Model_Queue extends Mage_Core_Model_Abstract
{
    const NOT_DATA              = 'N';
    const DATA_FACET_TAGS       = 'facet_tags';
    const DATA_FACET_PRICES     = 'facet_prices';
    const DATA_FACET_CATEGORIES = 'facet_categories';
    
    public static $dataTypes = array(
        self::DATA_FACET_TAGS,
        self::DATA_FACET_PRICES,
        self::DATA_FACET_CATEGORIES,
    );
    
    const ACT_PHRASE              = 'phrase';
    const ACT_UPDATE              = 'update';
    const ACT_DELETE              = 'delete';
    const ACT_DELETE_ALL          = 'delete';
    const ACT_FACET_UPDATE        = 'facet_update';
    const ACT_FACET_DELETE        = 'facet_delete';
    const ACT_FACET_DELETE_ALL    = 'facet_delete_all';
    const ACT_PREPARE_FULL_IMPORT = 'prepare_full_import';
    const ACT_START_FULL_IMPORT   = 'start_full_import';
    const ACT_END_FULL_IMPORT     = 'end_full_import';
    
    public static $mainActionTypes = array(
        self::ACT_PREPARE_FULL_IMPORT,
        self::ACT_START_FULL_IMPORT,
        self::ACT_END_FULL_IMPORT,
    );
    
    public static $actionTypes = array(
        self::ACT_PHRASE,
        self::ACT_UPDATE,
        self::ACT_DELETE,
        self::ACT_DELETE_ALL,
        self::ACT_FACET_UPDATE,
        self::ACT_FACET_DELETE,
        self::ACT_FACET_DELETE_ALL,
        self::ACT_PREPARE_FULL_IMPORT,
        self::ACT_START_FULL_IMPORT,
        self::ACT_END_FULL_IMPORT,
    );
    
    const STATUS_PENDING    = 'pending';
    const STATUS_DISABLED   = 'disabled'; 
    const STATUS_PROCESSING = 'processing';
    
    public static $statusTypes = array(
        self::STATUS_PENDING,
        self::STATUS_DISABLED,
        self::STATUS_PROCESSING,
    );
    
    protected function _construct()
    {
        $this->_init('searchanise/queue');
    }
    
    public function deleteKeys($cur_store = null)
    {
        $stores = Mage::helper('searchanise/ApiSe')->getStores($cur_store);
        
        foreach ($stores as $k_store => $store) {
            $queue = Mage::getModel('searchanise/queue')->getCollection()->addFilter('store_id', $store->getId())->toArray();
            
            if (!empty($queue['items'])) {
                foreach ($queue['items'] as $item) {
                    try {
                        Mage::getModel('searchanise/queue')->load($item['queue_id'])->delete();
                    } catch (Mage_Core_Exception $e) {
                        Mage::helper('searchanise/ApiSe')->log($e->getMessage(), 'Delete error');
                    }
                }
            }
        }
        
        return true;
    }

    public function getTotalItems()
    {
        $total = 0;

        $collection = $this->getCollection()
            ->setPageSize(0)
            ->load();

        if ($collection) {
            $total = count($collection);
        }

        return $total;
    }

    public function getNextQueueArray($queueId = null, $flagIgnoreError = false)
    {
        $collection = $this->getCollection()
            ->addOrder('queue_id', 'ASC')
            ->setPageSize(1);
        
        if (!empty($queueId)) {
            $collection = $collection->addFieldToFilter('queue_id', array('gt' => $queueId));
        }

        // Not use in current version.
        if ($flagIgnoreError) {
            $collection = $collection->addFieldToFilter('error_count', array('lt' => Mage::helper('searchanise/ApiSe')->getMaxErrorCount()));
        }

        return $collection->load()->toArray();
    }
    
    public function getNextQueue($queueId = null)
    {
        $q = array();
        $queueArr = self::getNextQueueArray($queueId);
        
        if (!empty($queueArr['items'])) {
            $q = reset($queueArr['items']);
        }
        
        return $q;
    }

    public function clearActions($store = null)
    {
        $collection = Mage::getModel('searchanise/queue')->getCollection();

        if ($store) {
            $collection = $collection->addFilter('store_id', $store->getId());
        }

        return $collection->load()->delete();
    }
    
    public function addAction($action, $data = NULL, $cur_store = null, $cur_store_id = null)
    {
        if (in_array($action, self::$actionTypes))
        {
            if (!Mage::helper('searchanise/ApiSe')->checkParentPrivateKey()) {
                return false;
            }
            
            $data = serialize((array)$data);
            $data = array($data);
            
            $stores = Mage::helper('searchanise/ApiSe')->getStores($cur_store, $cur_store_id);
            
            if ($action == self::ACT_PREPARE_FULL_IMPORT && !empty($cur_store)) {
                // Truncate queue for all
                Mage::getModel('searchanise/queue')->clearActions($cur_store);
            }
            
            foreach ($data as $d) {
                foreach ($stores as $k_store => $store) {
                    if (Mage::helper('searchanise/ApiSe')->getStatusModule($store) != 'Y') {
                        if (!in_array($action, self::$mainActionTypes)) {
                            continue;
                        }
                    }
                    
                    if ($action != self::ACT_PHRASE) {
                        // Remove duplicate actions
                        $exist_actions = Mage::getModel('searchanise/queue')
                            ->getCollection()
                            ->addFilter('status',   self::STATUS_PENDING)
                            ->addFilter('action',   $action)
                            ->addFilter('data',     $data)
                            ->addFilter('store_id', $store->getId())
                            ->load()
                            ->delete();
                    }
                    
                    $queue_data = array(
                        'action'    => $action,
                        'data'      => $d,
                        'store_id'  => $store->getId(),
                    );
                    
                    $this->setData($queue_data)->save();
                }
            }
            
            return true;
        }
        
        return false;
    }
    
    public function addActionProducts($products)
    {
        if (!empty($products)) {
            $productIds = array();
            
            foreach ($products as $product) {
                if ($product->getId()) {
                    $productIds[] = $product->getId();
                }
                if (count($productIds) >= Mage::helper('searchanise/ApiSe')->getProductsPerPass()) {
                    Mage::getModel('searchanise/queue')->addAction(Simtech_Searchanise_Model_Queue::ACT_UPDATE, $productIds);
                    $productIds = array();
                }
            }
            
            if ((!empty($productIds)) && (count($productIds) > 0)) {
                Mage::getModel('searchanise/queue')->addAction(Simtech_Searchanise_Model_Queue::ACT_UPDATE, $productIds);
            }
        }
        
        return $this;
    }
    
    public function addActionProductIdsForAllStore($productIds, $action = self::ACT_UPDATE)
    {
        if (!empty($productIds)) {
            if (count($productIds) <= Mage::helper('searchanise/ApiSe')->getProductsPerPass()) {
                Mage::getModel('searchanise/queue')->addAction($action, $productIds);
            } else {
                $actProductIds = array();
                
                foreach ($productIds as $productId) {
                    if ($productId) {
                        $actProductIds[] = $productId;
                    }
                    if (count($actProductIds) >= Mage::helper('searchanise/ApiSe')->getProductsPerPass()) {
                        Mage::getModel('searchanise/queue')->addAction($action, $actProductIds);
                        $actProductIds = array();
                    }
                }
                
                if (!empty($actProductIds)) {
                    Mage::getModel('searchanise/queue')->addAction($action, $productIds);
                }
            }
        }
        
        return $this;
    }
    
    public function addActionProductIds($productIds, $action = self::ACT_UPDATE)
    {
        if (!empty($productIds)) {
            if (!is_array($productIds)) {
                $productIds = array(0 => $productIds);
            }
            
            foreach ($productIds as $k => $productId) {
                $storeIds = null;
                $product = Mage::getModel('catalog/product')
                    ->load($productId);
                
                if ($product) {
                    $storeIds = $product->getStoreIds();                
                    Mage::getModel('searchanise/queue')->addAction(Simtech_Searchanise_Model_Queue::ACT_UPDATE, $productId, null, $storeIds);
                }
            }
        }
        
        return $this;
    }
    
    public function addActionOrderItems($items)
    {
        if (!empty($items)) {
            $productIds = array();
            
            foreach ($items as $item) {
                if ($item->getProductId()) {
                    $productIds[] = $item->getProductId();
                }
                if (count($productIds) >= Mage::helper('searchanise/ApiSe')->getProductsPerPass()) {
                    Mage::getModel('searchanise/queue')->addAction(Simtech_Searchanise_Model_Queue::ACT_UPDATE, $productIds, null, $item->getStoreId());
                    $productIds = array();
                }
            }
            
            if (!empty($productIds)) {
                Mage::getModel('searchanise/queue')->addAction(Simtech_Searchanise_Model_Queue::ACT_UPDATE, $productIds, null, $item->getStoreId());
            }
        }
        
        return $this;
    }
    
    public function addActionDeleteProductFromOldStore($product = null)
    {
        if ($product && $product->getId()) {
            $storeIds = $product->getStoreIds();
            
            $product_old = Mage::getModel('catalog/product')
                ->load($product->getId());
            
            if (!empty($product_old)) {
                $storeIdsOld = $product_old->getStoreIds();
                
                if (!empty($storeIdsOld)) {
                    foreach ($storeIdsOld as $k => $storeIdOld) {
                        if ((empty($storeIds)) || (!in_array($storeIdOld, $storeIds))) {
                            $this->addAction(Simtech_Searchanise_Model_Queue::ACT_DELETE, $product->getId(), null, $storeIdOld);
                        }
                    }
                }
            }
        }
        
        return $this;
    }
    
    public function addActionDeleteProduct($product = null)
    {
        if ($product && $product->getId()) {
            $storeIds = $product->getStoreIds();
            
            if (!empty($storeIds)) {
                foreach ($storeIds as $k => $storeId) {
                    $this->addAction(Simtech_Searchanise_Model_Queue::ACT_DELETE, $product->getId(), null, $storeId);
                }
            }
        }
        
        return $this;
    }
    
    public function addActionUpdateProduct($product = null, $storeIds = null)
    {
        if ($product && $product->getId()) {
            if (!empty($storeIds)) {
                if (!is_array($storeIds)) {
                    $storeIds = array(0 => $storeIds);
                }
            }
            
            if (empty($storeIds)) {
                $storeIds = $product->getStoreIds();
            }
            
            if (!empty($storeIds)) {
                foreach ($storeIds as $k => $storeId) {
                    $this->addAction(Simtech_Searchanise_Model_Queue::ACT_UPDATE, $product->getId(), null, $storeId);
                }
            }
        }
        
        return $this;
    }
}