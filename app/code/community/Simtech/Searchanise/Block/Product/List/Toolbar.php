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

class Simtech_Searchanise_Block_Product_List_Toolbar extends Mage_Catalog_Block_Product_List_Toolbar
{
	public function getCollectionPageSize()
	{
		if (!Mage::helper('searchanise/ApiSe')->checkSearchaniseResult(true)) {
			return parent::getCollectionPageSize();
		}
		
		return (int) $this->getLimit();
	}
	
	/**
	 * Get current collection page
	 *
	 * @param  int $displacement
	 * @return int
	 */
	public function getCollectionCurPage($displacement = 0)
	{
		if (!Mage::helper('searchanise/ApiSe')->checkSearchaniseResult(true)) {
			return parent::getCollectionCurPage($displacement);
		}
		
		if ($this->_curPage + $displacement < 1) {
			return 1;

		} elseif ($this->_curPage + $displacement > $this->getLastPageNumber()) {
			return $this->getLastPageNumber();

		} else {
			return $this->_curPage + $displacement;
		}
	}

	public function getFirstNum()
	{
		if (!Mage::helper('searchanise/ApiSe')->checkSearchaniseResult(true)) {
			return parent::getFirstNum();
		}
		
		$collection = $this->getCollection();
		if ((!method_exists($collection, 'checkSearchaniseResult')) || (!$collection->checkSearchaniseResult()))
		{
			return parent::getFirstNum();
		}
		
		return $this->getCollectionPageSize()*($this->getCurrentPage()-1)+1;
		
	}
	
	public function getLastNum()
	{
		if (!Mage::helper('searchanise/ApiSe')->checkSearchaniseResult(true)) {
			return parent::getLastNum();
		}
		
		$collection = $this->getCollection();
		if ((!method_exists($collection, 'checkSearchaniseResult')) || (!$collection->checkSearchaniseResult())) {
			return parent::getLastNum();
		}
		
		return $this->getCollectionPageSize()*($this->getCurrentPage()-1)+$collection->count();
	}
	
	public function getLastPageNum()
	{
		if (!Mage::helper('searchanise/ApiSe')->checkSearchaniseResult(true)) {
			return parent::getLastPageNum();
		}
		
		$collection = $this->getCollection();
		
		if ((!method_exists($collection, 'checkSearchaniseResult')) || (!$collection->checkSearchaniseResult())) {
			return parent::getLastPageNum();
		}
		
		$collectionSize = (int) $collection
			->getSearchaniseRequest()
			->getTotalProduct();
		
		$limit = (int) $this->getLimit();
		if (0 === $collectionSize) { 
			return 1; 

		} elseif ($limit) { 
			return ceil($collectionSize/$limit); 
		}
		
		return 1;
	}
	
	public function getTotalNum()
	{
		if (!Mage::helper('searchanise/ApiSe')->checkSearchaniseResult(true)) {
			return parent::getTotalNum();
		}
		
		$collection = $this->getCollection();
		
		if ((!method_exists($collection, 'checkSearchaniseResult')) || (!$collection->checkSearchaniseResult())) {
			return parent::getTotalNum();
		}
		
		return $collection
			->getSearchaniseRequest()
			->getTotalProduct();
	}
	
	public function setCollection($collection)
	{
		if (!Mage::helper('searchanise/ApiSe')->checkSearchaniseResult(true)) {
			return parent::setCollection($collection);
		}
		
		if ((!method_exists($collection, 'checkSearchaniseResult')) || (!$collection->checkSearchaniseResult())) {
			return parent::setCollection($collection);
		}
		
		$this->_collection = $collection;
		
		$this->_collection->setCurPage($this->getCurrentPage());
		
		// we need to set pagination only if passed value integer and more that 0
		$limit = (int)$this->getLimit();
		
		if ($limit) {
			//~ $this->_collection->setPageSize($limit);
		}
		
		if ($this->getCurrentOrder()) {
			$this->_collection->setOrder($this->getCurrentOrder(), $this->getCurrentDirection());
		}
		
		return $this;
	}
}