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

class Simtech_Searchanise_Model_Resource_Product_CollectionTag extends Mage_Tag_Model_Resource_Product_Collection
{
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
    
    public function addSearchaniseFilter()
    {
        $this->addFieldToFilter('entity_id', array('in' => $this->getSearchaniseRequest()->getProductIds()));
        
        return $this;
    }
    
    /**
     * Retrieve collection last page number
     *
     * @return int
     */
    public function getLastPageNumber()
    {
        if (!$this->checkSearchaniseResult()) {
            return parent::getLastPageNumber();
        }
        
        $collectionSize = (int) $this
            ->getSearchaniseRequest()
            ->getTotalProduct();
        
        if (0 === $collectionSize) {
            return 1;
            
        } elseif ($this->_pageSize) {
            return ceil($collectionSize/$this->_pageSize);
        }
        
        return 1;
    }
}