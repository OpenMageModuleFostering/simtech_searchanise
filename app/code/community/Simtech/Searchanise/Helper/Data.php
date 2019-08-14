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

class Simtech_Searchanise_Helper_Data extends Mage_Core_Helper_Abstract
{
    const DISABLE_VAR_NAME = 'disabled_module_searchanise';
    const DISABLE_KEY      = 'Y';

    const DEBUG_VAR_NAME = 'debug_module_searchanise';
    const DEBUG_KEY      = 'Y';
    
    const TEXT_FIND          = 'TEXT_FIND';
    const TEXT_ADVANCED_FIND = 'TEXT_ADVANCED_FIND';
    const VIEW_CATEGORY      = 'VIEW_CATEGORY';
    const VIEW_TAG           = 'VIEW_TAG';
    
    protected $_disableText = null;
    protected $_debugText   = null;
    
    protected static $_searchaniseTypes = array(
        self::TEXT_FIND,
        self::TEXT_ADVANCED_FIND,
        self::VIEW_CATEGORY,
        self::VIEW_TAG,
    );
    
    /**
     * Searchanise request
     *
     * @var Simtech_Searchanise_Model_Request
     */
    protected $_searchaniseRequest = null;
    
    public function initSearchaniseRequest()
    {
        $this->_searchaniseRequest = Mage::getModel('searchanise/request');
        
        return $this;
    }
    
    public function checkSearchaniseResult()
    {
        return Mage::helper('searchanise/ApiSe')->checkSearchaniseResult($this->_searchaniseRequest);
    }
    
    public function setSearchaniseRequest($request)
    {
        $this->_searchaniseRequest = $request;
    }
    
    public function getSearchaniseRequest()
    {
        return $this->_searchaniseRequest;
    }
    
    public function getDisableText()
    {
        if (is_null($this->_disableText)) {
            $this->_disableText = $this->_getRequest()->getParam(self::DISABLE_VAR_NAME);
        }
        
        return $this->_disableText;
    }
    
    public function checkEnabled()
    {
        return ($this->getDisableText() != self::DISABLE_KEY) ? true : false;
    }

    public function getDebugText()
    {
        if (is_null($this->_debugText)) {
            $this->_debugText = $this->_getRequest()->getParam(self::DEBUG_VAR_NAME);
        }
        
        return $this->_debugText;
    }
    
    public function checkDebug()
    {
        return ($this->getDebugText() == self::DEBUG_KEY) ? true : false;
    }
    
    protected function setDefaultSort(&$params, $type)
    {
        if (empty($params)) {
            $params = array();
        }

        if (in_array($type, self::$_searchaniseTypes)) {
            if ($type == self::TEXT_FIND) {
                $params['sortBy']    = 'relevance';
                $params['sortOrder'] = 'desc';

            } elseif ($type == self::TEXT_ADVANCED_FIND) {
                $params['sortBy']    = 'title';
                $params['sortOrder'] = 'asc';

            } elseif ($type == self::VIEW_CATEGORY) {
                $params['sortBy']    = 'position';
                $params['sortOrder'] = 'asc';

            } elseif ($type == self::VIEW_TAG) {
                $params['sortBy']    = 'title';
                $params['sortOrder'] = 'asc';
            }

            if (empty($params['restrictBy'])) {
                $params['restrictBy'] = array();
            }
            if (empty($params['queryBy'])) {
                $params['queryBy'] = array();
            }
            if (empty($params['union'])) {
                $params['union'] = array();
            }
        }
    }
    
    protected function getUrlSuggestion($suggestion)
    {
        $query = array(
            'q' => $suggestion,
            Mage::getBlockSingleton('page/html_pager')->getPageVarName() => null // exclude current page from urls
        );
        
        return Mage::getUrl('*/*/*', array('_current'=>true, '_use_rewrite'=>true, '_query'=>$query));
    }
    
    public function execute($type = null, $controller = null, $block_toolbar = null, $data = null)
    {
        if (!$this->checkEnabled()) {
            return;
        }
        
        if (!Mage::helper('searchanise/ApiSe')->getUseNavigation()) {
            if (($type != self::TEXT_FIND) && ($type != self::TEXT_ADVANCED_FIND)) {
                return;
            }
        }
        if (empty($params)) {
            $params = array();
        }
        
        // Set default value.
        $this->setDefaultSort($params, $type);

        $params['restrictBy']['status'] = '1';
        $params['union']['price']['min'] = Mage::helper('searchanise/ApiSe')->getCurLabelForPricesUsergroup();
        $params['startIndex'] = 0; // tmp
        $showOutOfStock = Mage::getStoreConfigFlag(Mage_CatalogInventory_Helper_Data::XML_PATH_SHOW_OUT_OF_STOCK);
        if ($showOutOfStock) {
            // nothing
        } else {
            $params['restrictBy']['is_in_stock'] = '1';
        }
        
        if (in_array($type, self::$_searchaniseTypes)) {
            if ($type == self::TEXT_FIND) {
                $params['q'] = Mage::helper('catalogsearch')->getQueryText();
                if ($params['q']) {
                    $params['q'] = trim($params['q']);
                }

                $params['facets']                = 'true';
                $params['suggestions']           = 'true';
                $params['query_correction']      = 'false';
                $params['suggestionsMaxResults'] = Mage::helper('searchanise/ApiSe')->getSuggestionsMaxResults();
                
                $params['restrictBy']['visibility'] = '3|4';
                $minQuantityDecimals = Mage::helper('searchanise/ApiSe')->getMinQuantityDecimals();
                if (!empty($minQuantityDecimals)) {
                    $params['restrictBy']['quantity_decimals'] = $minQuantityDecimals . ',';
                }

            } elseif ($type == self::TEXT_ADVANCED_FIND) {
                $params['facets']           = 'false';
                $params['suggestions']      = 'false';
                $params['query_correction'] = 'false';
                
                $params['restrictBy']['visibility'] = '3|4';
                $minQuantityDecimals = Mage::helper('searchanise/ApiSe')->getMinQuantityDecimals();
                if (!empty($minQuantityDecimals)) {
                    $params['restrictBy']['quantity_decimals'] = $minQuantityDecimals . ',';
                }
                
            } elseif ($type == self::VIEW_CATEGORY) {
                // fixme in the future
                // need to add check to display block "Layered Navigation"
                if (true) {
                    $params['facets'] = 'true';
                    
                } else {
                    $params['facets'] = 'false';
                }
                
                $params['suggestions'] = 'false';
                $params['restrictBy']['visibility'] = '2|4';

            } elseif ($type == self::VIEW_TAG) {
                $params['facets']      = 'false';
                $params['suggestions'] = 'false';
                
                $params['restrictBy']['visibility'] = '3|2|4';
            }
        }
        
        if ((!empty($controller)) && (!empty($block_toolbar))) {
            if ($availableOrders = $block_toolbar->getAvailableOrders()) {
                if (in_array($type, self::$_searchaniseTypes)) {
                    if ($type == self::TEXT_FIND) {
                        unset($availableOrders['position']);
                        
                        $availableOrders = array_merge(
                            array('relevance' => $controller->__('Relevance')),
                            $availableOrders
                        );
                        
                        $block_toolbar->setAvailableOrders($availableOrders);
                    } elseif ($type == self::TEXT_ADVANCED_FIND) {
                        unset($availableOrders['position']);
                        $block_toolbar->setAvailableOrders($availableOrders);

                    } elseif ($type == self::VIEW_TAG) {
                        unset($availableOrders['position']);
                        
                        $block_toolbar->setAvailableOrders($availableOrders);
                    }
                }
            }
            
            $sort_by = $block_toolbar->getCurrentOrder();
            $sort_order = $block_toolbar->getCurrentDirection();
            
            $max_results = (int) $block_toolbar->getLimit();
            $start_index = 0;
            $cur_page = (int) $block_toolbar->getCurrentPage();
            $start_index = $cur_page > 1 ? ($cur_page - 1) * $max_results : 0;
            
            if ($max_results) {
                $params['maxResults'] = $max_results;
            }
            if ($start_index) {
                $params['startIndex'] = $start_index;
            }
            
            if ($sort_by) {
                if ($sort_by == 'name') {
                    $params['sortBy'] = 'title';
                } else {
                    $params['sortBy'] = $sort_by;
                }
            }
            
            if ($sort_order) {
                $params['sortOrder'] = $sort_order;
            }
        }
        
        //ADD FACETS
        $arrAttributes = array();
        $arrInputType  = array(); // need for save type $arrAttributes
        if (!empty($controller)) {
            // CATEGORIES
            {
                $arr_cat = null;
                
                if ((in_array($type, self::$_searchaniseTypes)) && ($type != self::VIEW_TAG)) {
                    $cat_id = (int) $controller->getRequest()->getParam('cat');
                    if (!empty($cat_id)) {
                        $arr_cat = array();
                        $arr_cat[] = $cat_id; // need if not exist children categories
                        
                        $categories = Mage::getModel('catalog/category')
                            ->getCollection()
                            ->setStoreId(Mage::app()->getStore()->getId())
                            ->addFieldToFilter('entity_id', $cat_id)
                            ->load()
                            ;
                            
                        if (!empty($categories)) {
                            foreach ($categories as $cat) {
                                if (!empty($cat)) {
                                    $arr_cat = $cat->getAllChildren(true);
                                }
                            }
                        }
                    } elseif (($type == self::VIEW_CATEGORY) && (!empty($data))) {
                        // data = category
                        $arr_cat = $data->getAllChildren(true);
                    }
                }
                
                if (!empty($arr_cat)) {
                    if (is_array($arr_cat)) {
                        $params['restrictBy']['categories'] = implode('|', $arr_cat);
                    } else {
                        $params['restrictBy']['categories'] = $arr_cat;
                    }
                }
            }
            // ATTRIBUTES
            {
                $attributes = Mage::getResourceModel('catalog/product_attribute_collection');
                $attributes
                    ->setItemObjectClass('catalog/resource_eav_attribute')
                    ->load();
                    
                if (!empty($attributes)) {
                    foreach ($attributes as $id => $attr) {
                        $arrAttributes[$id] = $attr->getName();
                        $arrInputType[$id] = $attr->getData('frontend_input');
                    }
                    
                    if (!empty($arrAttributes)) {
                        $req_params = $controller->getRequest()->getParams();
                        
                        if (!empty($req_params)) {
                            foreach ($req_params as $name => $val) {
                                $id = array_search($name, $arrAttributes);
                                if (($name) && ($id)) {
                                    // hook, need for 'union'
                                    if ($name == 'price') {
                                        $valPrice = Mage::helper('searchanise/ApiSe')->getPriceValueFromRequest($val);
                                        if ($valPrice != '') {
                                            $params['restrictBy']['price'] = $valPrice;
                                        }
                                        continue;
                                    }

                                    if ($arrInputType[$id] == 'price') {
                                        $valPrice = Mage::helper('searchanise/ApiSe')->getPriceValueFromRequest($val);
                                        
                                        if ($valPrice != '') {
                                            $params['restrictBy']['attribute_' . $id] = $valPrice;
                                        }
                                        
                                    } elseif (($arrInputType[$id] == 'text') || ($arrInputType[$id] == 'textarea')) {
                                        if ($val != '') {
                                            $val = Mage::helper('searchanise/ApiSe')->escapingCharacters($val);

                                            if ($val != '') {
                                                $params['queryBy']['attribute_' . $id] = $val;
                                            }
                                        }

                                    } elseif (($arrInputType[$id] == 'select') ||
                                              ($arrInputType[$id] == 'multiselect') ||
                                              ($arrInputType[$id] == 'boolean')) {
                                        if ($val) {
                                            if (is_array($val)) {
                                                $params['restrictBy']['attribute_' . $id] = implode('|', $val);
                                            } else {
                                                $params['restrictBy']['attribute_' . $id] = $val;
                                            }
                                        }

                                    } else {
                                        // nothing
                                    }
                                }
                            }
                        }
                    }
                }
            }
            // TAGS
            if ((in_array($type, self::$_searchaniseTypes)) && ($type == self::VIEW_TAG)) {
                if ($data) {
                    // data = tag
                    $params['restrictBy']['tags'] = $data->getId();
                }
            }
        }

        // need for other sort_by
        if (!empty($arrAttributes)) {
            $id = array_search($params['sortBy'], $arrAttributes);
            if (!empty($id)) {
                $params['sortBy'] = 'attribute_' . $id;
            }
        }

        if (!Mage::helper('searchanise/ApiSe')->getUseNavigation()) {
            if (empty($params['queryBy']) && (!isset($params['q']) || $params['q'] == '')) {
                return;
            }
        }
        
        Mage::helper('searchanise')
            ->initSearchaniseRequest()
            ->getSearchaniseRequest()
            ->setStore(Mage::app()->getStore())
            ->setSearchParams($params)
            ->sendSearchRequest()
            ->getSearchResult();
        
        //add suggestions
        $suggestionsMaxResults = Mage::helper('searchanise/ApiSe')->getSuggestionsMaxResults();
        if ((!empty($suggestionsMaxResults)) && (in_array($type, self::$_searchaniseTypes)) && ($type == self::TEXT_FIND)) {
            $res = Mage::helper('searchanise')->getSearchaniseRequest();
            
            if ($res->getTotalProduct() == 0) {
                $sugs = Mage::helper('searchanise')->getSearchaniseRequest()->getSuggestions();
                
                if ((!empty($sugs)) && (count($sugs) > 0)) {
                    $message = Mage::helper('searchanise')->__('Did you mean: ');
                    $link = '';
                    $textFind = Mage::helper('catalogsearch')->getQueryText();
                    $count_sug = 0;

                    foreach ($sugs as $k => $sug) {
                        if ((!empty($sug)) && ($sug != $textFind)) {    
                            $link .= '<a href="' . self::getUrlSuggestion($sug). '">' . $sug .'</a>';
                            
                            if (end($sugs) == $sug) { 
                                $link .= '?'; 
                            } else { 
                                $link .= ', '; 
                            }
                            $count_sug++;
                        }
                        if ($count_sug >= $suggestionsMaxResults) {
                            break;
                        }
                    }
                    
                    if ($link != '') {
                        Mage::helper('catalogsearch')->addNoteMessage($message . $link);
                    }
                }
            }
        }
    }
    
    /**
     * Get specified products limit display per page
     *
     * @return string
     */
    public function getLimit()
    {
        //~ $limit = $this->_getData('_current_limit');
        //~ if ($limit) {
            //~ return $limit;
        //~ }
        
        $limits = $this->getAvailableLimit();
        $defaultLimit = $this->getDefaultPerPageValue();
        if (!$defaultLimit || !isset($limits[$defaultLimit])) {
            $keys = array_keys($limits);
            $defaultLimit = $keys[0];
        }
        
        $limit = $this->getRequest()->getParam($this->getLimitVarName());
        if ($limit && isset($limits[$limit])) {
            if ($limit == $defaultLimit) {
                Mage::getSingleton('catalog/session')->unsLimitPage();
            } else {
                $this->_memorizeParam('limit_page', $limit);
            }
        } else {
            $limit = Mage::getSingleton('catalog/session')->getLimitPage();
        }
        if (!$limit || !isset($limits[$limit])) {
            $limit = $defaultLimit;
        }

        $this->setData('_current_limit', $limit);
        return $limit;
    }
    
    /**
     * Retrieve available limits for current view mode
     *
     * @return array
     */
    public function getAvailableLimit()
    {
        $currentMode = $this->getCurrentMode();
        if (in_array($currentMode, array('list', 'grid'))) {
            return $this->_getAvailableLimit($currentMode);
        } else {
            return $this->_defaultAvailableLimit;
        }
    }
}