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

class Simtech_Searchanise_InfoController extends Mage_Core_Controller_Front_Action
{
    const RESYNC             = 'resync'; 
    const OUTPUT             = 'visual';
    const STORE_ID           = 'store_id';
    const PRODUCT_ID         = 'product_id';
    const PRODUCT_IDS        = 'product_ids';
    const PARENT_PRIVATE_KEY = 'parent_private_key';

    /**
     * Dispatch event before action
     *
     * @return void
    */
    public function preDispatch()
    {
        // It is need if it will used the "generateProductsXML" function

        // Do not start standart session
        $this->setFlag('', self::FLAG_NO_START_SESSION, 1); 
        $this->setFlag('', self::FLAG_NO_CHECK_INSTALLATION, 1);
        $this->setFlag('', self::FLAG_NO_COOKIES_REDIRECT, 0);
        $this->setFlag('', self::FLAG_NO_PRE_DISPATCH, 1);

        // Need for delete the "PDOExceptionPDOException" error
        $this->setFlag('', self::FLAG_NO_POST_DISPATCH, 1); 

        parent::preDispatch();

        return $this;
    }

    public function indexAction()
    {
        $resync           = $this->getRequest()->getParam(self::RESYNC);
        $visual           = $this->getRequest()->getParam(self::OUTPUT);
        $storeId          = $this->getRequest()->getParam(self::STORE_ID);
        $productId        = $this->getRequest()->getParam(self::PRODUCT_ID);
        $productIds       = $this->getRequest()->getParam(self::PRODUCT_IDS);
        $parentPrivateKey = $this->getRequest()->getParam(self::PARENT_PRIVATE_KEY);

        if ($productId) {
            $productIds = array($productId);
        }

        if ((empty($parentPrivateKey)) || 
            (Mage::helper('searchanise/ApiSe')->getParentPrivateKey() !== $parentPrivateKey)) {
            $_options = Mage::helper('searchanise/ApiSe')->getAddonOptions();
            $options = array(
                'status'  => $_options['addon_status'],
                'api_key' => $_options['api_key'],
            );

            if ($visual) {
                Mage::helper('searchanise/ApiSe')->printR($options);
            } else {
                echo Mage::helper('core')->jsonEncode($options);
            }
        } else {
            if ($resync) {
                Mage::helper('searchanise/ApiSe')->queueImport();

            } elseif (!empty($productIds)) {
                $store = null;
                if (!empty($storeId)) {
                    $store = Mage::app()->getStore($storeId);
                }
                $productFeeds = Mage::helper('searchanise/ApiXML')->generateProductsXML($productIds, $store);

                if ($visual) {
                    Mage::helper('searchanise/ApiSe')->printR($productFeeds);
                } else {
                    echo Mage::helper('core')->jsonEncode($productFeeds);
                }
            } else {
                Mage::helper('searchanise/ApiSe')->checkImportIsDone();
                
                $options = Mage::helper('searchanise/ApiSe')->getAddonOptions();
                if (!$options) {
                    $options = array();
                }
                $options['next_queue'] = Mage::getModel('searchanise/queue')->getNextQueue();
                $options['total_items_in_queue'] = Mage::getModel('searchanise/queue')->getTotalItems();
                
                $options['cron_async_enabled'] = Mage::helper('searchanise/ApiSe')->checkCronAsync();
                $options['ajax_async_enabled'] = Mage::helper('searchanise/ApiSe')->checkAjaxAsync();
                $options['object_async_enabled'] = Mage::helper('searchanise/ApiSe')->checkObjectAsync();

                $options['max_execution_time'] = ini_get('max_execution_time');
                @set_time_limit(0);
                $options['max_execution_time_after'] = ini_get('max_execution_time');

                $options['ignore_user_abort'] = ini_get('ignore_user_abort');
                @ignore_user_abort(1);
                $options['ignore_user_abort_after'] = ini_get('ignore_user_abort_after');

                if ($visual) {
                    Mage::helper('searchanise/ApiSe')->printR($options);
                } else {
                    echo Mage::helper('core')->jsonEncode($options);
                }
            }
        }

        die();
    }
}
