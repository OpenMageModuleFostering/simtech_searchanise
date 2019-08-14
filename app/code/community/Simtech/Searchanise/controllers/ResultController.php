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
require_once("Mage/CatalogSearch/controllers/ResultController.php");

class Simtech_Searchanise_ResultController extends Mage_CatalogSearch_ResultController
{
	protected $_defaultToolbarBlock = 'catalog/product_list_toolbar';
	protected $_defaultListBlock    = 'catalog/product_list';
	
	/**
	 * Display search result
	 */
	public function indexAction()
	{
		if (!Mage::helper('searchanise/ApiSe')->checkSearchaniseResult(true)) {
			return parent::indexAction();
		}
		
		$query = Mage::helper('catalogsearch')->getQuery();
		/* @var $query Mage_CatalogSearch_Model_Query */
		
		$query->setStoreId(Mage::app()->getStore()->getId());
		
		if ($query->getQueryText()) {
			if (Mage::helper('searchanise')->checkEnabled()) {
				$block_toolbar = $this->getLayout()->createBlock($this->_defaultToolbarBlock, microtime());
				
				Mage::helper('searchanise')->execute(Simtech_Searchanise_Helper_Data::TEXT_FIND, $this, $block_toolbar, $query);
			}
		}
		
		return parent::indexAction();
	}
}