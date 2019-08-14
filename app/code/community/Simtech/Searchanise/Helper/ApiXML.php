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
class Simtech_Searchanise_Helper_ApiXML extends Mage_Core_Helper_Data
{
	const XML_END_LINE = "\n";
	
	public static function getStockItem($product, $store = null)
	{
		$stockItem = null;
		
		if (Mage::helper('catalog')->isModuleEnabled('Mage_CatalogInventory')) {
			$stockItem = Mage::getModel('cataloginventory/stock_item')
				->loadByProduct($product);
		}
		
		return $stockItem;
	}
	
	public static function getTagCollection($product, $store = null)
	{
		$tagCollection = Mage::getModel('tag/tag')
			->getResourceCollection()
			->setFlag('relation', true)
			->setActiveFilter();
		
		if (!empty($store)) {
			$tagCollection->addStoreFilter($store->getId(), true);
		}

		return $tagCollection
			->addPopularity()
			->addStatusFilter(Mage::getModel('tag/tag')->getApprovedStatus())
			->addStoresVisibility()
			->addProductFilter($product->getId())
			->load();
	}
	
	public static function generateProductXML($product, $store = null)
	{
		$entry = '<entry>' . self::XML_END_LINE;
		$entry .= '<id>' . $product->getId() . '</id>' . self::XML_END_LINE;
		
		$entry .= '<title><![CDATA[' . $product->getName() . ']]></title>' . self::XML_END_LINE;
		
		$summary = $product->getData('short_description');
		
		if ($summary == '') { 
			$summary = $product->getData('description'); 
		}
		$entry .= '<summary><![CDATA[' . $summary. ']]></summary>' . self::XML_END_LINE;
		
		$productUrl = $product->getProductUrl(false);
		$productUrl = Mage::helper('searchanise/ApiSe')->changeAmpersand($productUrl);
		$entry .= '<link href="' . $productUrl . '" />' . self::XML_END_LINE;

		// fixme in the future
		// maybe exist simple solution get customerGroupPrices
		$customerGroups = Mage::getModel('customer/group')->getCollection()->load();
		$defaultPrice = '';

		if ($customerGroups) {
			if (($product->getData('type_id') == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) ||
				($product->getData('type_id') == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)) {
				$productsCustomerGroup = self::getProduct($product->getId(), $store, true);
				if ($productsCustomerGroup) {
					foreach ($productsCustomerGroup as $productCustomerGroup) {
						$defaultPrice = $productCustomerGroup->getData('min_price');
						break;
					}
				}
			}

			foreach ($customerGroups as $kCostomerGroup => $customerGroup) {
				$price = '';
				$minPrice = '';

				if (($product->getData('type_id') == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) ||
					($product->getData('type_id') == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)) {
					$price = $defaultPrice;
				} else {
					$productsCustomerGroup = self::getProduct($product->getId(), $store, false, $customerGroup->getId());
					if ($productsCustomerGroup) {
						foreach ($productsCustomerGroup as $productCustomerGroup) {
							$price = $productCustomerGroup->getFinalPrice();
							break;
						}
					}
				}

				if ($price != '') {
					$price = round($price, Mage::helper('searchanise/ApiSe')->getFloatPrecision());
				}

				if ($customerGroup->getId() == Mage_Customer_Model_Group::NOT_LOGGED_IN_ID) {
					$entry .= '<cs:price>' . $price . '</cs:price>'. self::XML_END_LINE;
					$defaultPrice = $price;
				}
				$label_ = Mage::helper('searchanise/ApiSe')->getLabelForPricesUsergroup() . $customerGroup->getId();
				$entry .= '<cs:attribute name="' . $label_ . '" type="float">' . $price . '</cs:attribute>' . self::XML_END_LINE;
			}
		}

		$entry .= '<cs:product_code><![CDATA[' . $product->getSku() . ']]></cs:product_code>' . self::XML_END_LINE;
		// <quantity>
		{
			$quantity = 1;
			$stockItem = self::getStockItem($product);
			if ($stockItem) {
				$manageStock = null;
				if ($stockItem->getData('use_config_manage_stock')) {
					$manageStock = Mage::getStoreConfig(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK);
				} else {
					$manageStock = $stockItem->getData('manage_stock');
				}

				if (!$manageStock) {
					$quantity = 1;
				} else {
					$isInStock = $stockItem->getIsInStock();
					if (!empty($isInStock)) { 
						$quantity = $stockItem->getQty(); 
					} else { 
						$quantity = 0;
					}
				}
			}
			
			$entry .= '<cs:quantity>' . ceil($quantity) . '</cs:quantity>' . self::XML_END_LINE;
			$entry .= '<cs:attribute name="is_in_stock" type="text">' . ($quantity > 0) . '</cs:attribute>' . self::XML_END_LINE;
			$quantity = round($quantity, Mage::helper('searchanise/ApiSe')->getFloatPrecision());
			$entry .= '<cs:attribute name="quantity_decimals" type="float">' . $quantity . '</cs:attribute>' . self::XML_END_LINE;
		}
		// </quantity>

		// <image_link>
		{
			$image_link = '';
			$small_image = null;
			// Uncomment the lines below if it is necessary to send image icons.
			//~ $small_image = $product->getData('small_image');
			
			if (!empty($small_image) && $small_image != 'no_selection') {
				$image_link = $product->getSmallImageUrl();
				
			} else {
				$image = $product->getData('image');
				
				if (!empty($image) && $image != 'no_selection') { 
					$image_link = $product->getImageUrl(); 
				} else {
					$thumbnail = $product->getData('thumbnail');
					
					if (!empty($thumbnail) && $thumbnail != 'no_selection') { $image_link = $product->getThumbnailUrl(); }
				}
			}
			
			$entry .= '<cs:image_link><![CDATA[' . $image_link . ']]></cs:image_link>' . self::XML_END_LINE;
		}
		// </image_link>
		
		// <attributes_position>
		{
			$entry .= '<cs:attribute name="position" type="int">';
			
			// fixme in the feature
			// sort by "position" disabled
			$entry .= $product->getData('position');
			
			$entry .= '</cs:attribute>' . self::XML_END_LINE;
		}
		// </attributes_position>
		
		// <attributes>
		{
			//~ $product->getAttributes();
			$attributes = Mage::getResourceModel('catalog/product_attribute_collection');
			$attributes
				->setItemObjectClass('catalog/resource_eav_attribute')
				->setOrder('position', 'ASC')
				->load();

			if (!empty($attributes)) {
				foreach ($attributes as $attribute) {
					$inputType = $attribute->getData('frontend_input');
					
					if ($inputType == 'price') {
						$entry .= '<cs:attribute name="attribute_' . $attribute->getId() . '" type="float">';
						$entry .= $defaultPrice;
						$entry .= '</cs:attribute>' . self::XML_END_LINE;

					} elseif ($inputType == 'select') {
						$entry .= '<cs:attribute name="attribute_' . $attribute->getId() . '" type="text">';
						
						$value = (int) $product->getData($attribute->getAttributeCode());
						if (!empty($value)) {
							$entry .= $value;
						}
						
						$entry .= '</cs:attribute>' . self::XML_END_LINE;

					} elseif ($inputType == 'multiselect') {
						$entry .= '<cs:attribute name="attribute_' . $attribute->getId() . '" type="text">';
						// fixme in the future
						// need for fixed bug of Server
						$entry .= ' ';
						
						$values = $product->getData($attribute->getAttributeCode());
						if (!empty($values)) {
							$arr_values = explode(',', $values);
							if (!empty($arr_values)) {
								foreach ($arr_values as $value) {
									if (!empty($value)) {
										$entry .= '<value>' . $value . '</value>'; 
									}
								}
							}
						}
						
						$entry .= '</cs:attribute>' . self::XML_END_LINE;

					} elseif (($inputType == 'text') || ($inputType == 'textarea')) {
						$value = $product->getData($attribute->getAttributeCode());

						if ($value != '') {
							$entry .= '<cs:attribute name="attribute_' . $attribute->getId() . '" type="text" text_search="Y" weight="0">';

							$entry .= '<![CDATA[' . $value . ']]>'; 

							$entry .= '</cs:attribute>' . self::XML_END_LINE;
						}
					} else {
						// attribute is not filtrable
					}
				}
			}
		}
		// </attributes>
		
		// <categories>
		{
			$entry .= '<cs:attribute name="categories" type="text">';
			// need, it's important
			$entry .= ' ';
			$category_ids = $product->getCategoryIds();
			if (!empty($category_ids)) {
				foreach ($category_ids as $cat_key => $category_id) {
					$entry .= '<value>' . $category_id . '</value>';
				}
			}
			$entry .= '</cs:attribute>' . self::XML_END_LINE;
		}
		// </categories>
		
		// <status>
		$entry .= '<cs:attribute name="status" type="text">' . $product->getStatus() . '</cs:attribute>' . self::XML_END_LINE;
		// </status>
		
		// <visibility>
		$entry .= '<cs:attribute name="visibility" type="text">' . $product->getData('visibility'). '</cs:attribute>' . self::XML_END_LINE;
		// </visibility>
		
		// <tags>
		{
			$entry .= '<cs:attribute name="tags" type="text">';
			// fixme in the future
			// need for fixed bug of Server
			$entry .= ' ';
			
			$tags = self::getTagCollection($product, $store);
			
			if (!empty($tags)) {
				foreach ($tags as $tag){
					if (!empty($tag)){
						$entry .= '<value>' . $tag->getId() . '</value>';
					}
				}
			}
			
			$entry .= '</cs:attribute>' . self::XML_END_LINE;
		}
		// </tags>
		
		$entry .= '</entry>' . self::XML_END_LINE;
		
		return $entry;
	}
	
	public static function getOptionCollection($filter, $store = null)
	{
		// not used in current module
		$optionCollection = Mage::getResourceModel('eav/entity_attribute_option_collection');
		
		if (!empty($store)) {
			$optionCollection->setStoreFilter($store); //fixme need check
		}
		
		return $optionCollection
			->setAttributeFilter($filter->getId())
			->setPositionOrder('desc', true)
			->load();
	}
	
	public static function getPriceNavigationStep($store = null)
	{
		if (empty($store)) {
			$store = Mage::app()->getStore(0);
		}
		
		$priceRangeCalculation = $store->getConfig(Mage_Catalog_Model_Layer_Filter_Price::XML_PATH_RANGE_CALCULATION);
		
		if ($priceRangeCalculation == Mage_Catalog_Model_Layer_Filter_Price::RANGE_CALCULATION_MANUAL) {
			return $store->getConfig(Mage_Catalog_Model_Layer_Filter_Price::XML_PATH_RANGE_STEP);
		}
		
		return null;
	}
	
	public static function generateFacetXMLFromFilter($filter, $store = null)
	{
		$entry = '<entry>' . self::XML_END_LINE;
		$entry .= '<title><![CDATA[' . $filter->getData('frontend_label') . ']]></title>' . self::XML_END_LINE;
		$entry .= '<cs:position>' . $filter->getPosition() . '</cs:position>' . self::XML_END_LINE;
		
		$inputType = $filter->getData('frontend_input');
		
		$entry .= '<cs:attribute>attribute_' . $filter->getId() . '</cs:attribute>' . self::XML_END_LINE;
		
		// "Can be used only with catalog input type Dropdown, Multiple Select and Price".
		if (($inputType == 'select') || ($inputType == 'multiselect')) {
			$entry .= '<cs:type>select</cs:type>' . self::XML_END_LINE;
			
		} elseif ($inputType == 'price') {
			$entry .= '<cs:type>dynamic</cs:type>' . self::XML_END_LINE;
			$step = self::getPriceNavigationStep($store);
			
			if (!empty($step)) {
				$entry .= '<cs:min_range>' . $step . '</cs:min_range>' . self::XML_END_LINE;
			}
			
		// attribute is not filtrable
		} else {
			return '';
		}
		
		$entry .= '</entry>' . self::XML_END_LINE;
		
		return $entry;
	}
	
	public static function generateFacetXMLFromCustom($title = '', $position = 0, $attribute = '', $type = '')
	{
		$entry = '<entry>' . self::XML_END_LINE;
		
		$entry .= '<title><![CDATA[' . $title .']]></title>' . self::XML_END_LINE;
		$entry .= '<cs:position>' . $position . '</cs:position>' . self::XML_END_LINE;
		$entry .= '<cs:attribute>' . $attribute . '</cs:attribute>' . self::XML_END_LINE;
		$entry .= '<cs:type>' . $type .'</cs:type>' . self::XML_END_LINE;
		
		$entry .= '</entry>' . self::XML_END_LINE;
		
		return $entry;
	}

	private static function getProduct($productIds = null, $store = null, $flAddMinimalPrice = true, $customerGroupId = null)
	{
		$products = Mage::getModel('catalog/product')
			->getCollection()
			->addAttributeToSelect('*');
			
		if (!empty($store)) {
			$products
				->setStoreId($store)
				->addStoreFilter($store);
		} else {
			$products->setStoreId(Mage::app()->getStores());
		}
		
		if (!empty($productIds)) {
			if (is_array($productIds)) {
				$products->addFieldToFilter('entity_id', array('in' => $productIds));
			} else {
				$products->addFieldToFilter('entity_id', array('eq' => $productIds));
			}
		}

		if ($flAddMinimalPrice == true) {
			$products->addMinimalPrice($customerGroupId);
		}

		if ($customerGroupId) {
			if (!$store) {
				$products->addPriceData($customerGroupId, $store->getWebsiteId());
			} else {
				$products->addPriceData($customerGroupId);
			}
		}

		$products
			->addUrlRewrite()
			->load();
		
		return $products;
	}

	private static function getProducts($productIds = null, $store = null, $flAddMinimalPrice = false, $customerGroupId = null)
	{
		$products = Mage::getModel('catalog/product')
			->getCollection()
			->addAttributeToSelect('*');
			
		if (!empty($store)) {
			$products
				->setStoreId($store)
				->addStoreFilter($store);
		} else {
			$products->setStoreId(Mage::app()->getStores());
		}
		
		if (!empty($productIds)) {
			if (is_array($productIds)) {
				$products->addFieldToFilter('entity_id', array('in' => $productIds));
			} else {
				$products->addFieldToFilter('entity_id', array('eq' => $productIds));
			}
		}

		if ($flAddMinimalPrice == true) {
			$products->addMinimalPrice();
		}

		if ($customerGroupId) {
			if (!$store) {
				$products->addPriceData($customerGroupId, $store->getWebsiteId());
			} else {
				$products->addPriceData($customerGroupId);
			}
		}

		$products
			->addUrlRewrite()
			->load();
		
		return $products;
	}

	// Main functions //
	public static function generateProductsXML($productIds = null, $store = null, $flAddMinimalPrice = false)
	{
		if ($store) {
			// need for generate correct url
			Mage::app()->setCurrentStore($store->getId());
		}
		$ret = '';

		$products = self::getProducts($productIds, $store, $flAddMinimalPrice);
		$arrProduct = $products->toArray();

		// additional check for products without minimal price
		// deprecated, because use only $flAddMinimalPrice = false in current module
		if ($flAddMinimalPrice === true) {
			if ((empty($arrProduct)) || (count($arrProduct) == 0)) {
				return self::generateProductsXML($productIds, $store, false);
			}
			$products2 = self::getProducts($productIds, $store, false);
			$arrProduct2 = $products2->toArray();
			if (count($arrProduct2) > count($arrProduct)) {
				$additionalProductsIds = array();
				foreach ($arrProduct2 as $productId => $product) {
					if (!array_key_exists($productId, $arrProduct)) {
						$additionalProductsIds[] = $productId;
					}
				}
				if (!empty($additionalProductsIds)) {
					$additionalProducts = self::getProducts($additionalProductsIds, $store, false);
					$arrAdditionalProducts = $additionalProducts->toArray();
					if ((!empty($arrAdditionalProducts)) && (count($arrAdditionalProducts) != 0)) {
						foreach ($additionalProducts as $product) {
							$ret .= self::generateProductXML($product, $store);
						}
					}
				}
			}
		}

		if ((!empty($arrProduct)) && (count($arrProduct) != 0)) {
			foreach ($products as $product) {
				$ret .= self::generateProductXML($product, $store);
			}
		}
		
		return $ret;
	}
	
	public static function generateFacetXMLFilters($attributeIds = null, $store = null)
	{
		$ret = '';
		
		$filters = Mage::getResourceModel('catalog/product_attribute_collection')
			->setItemObjectClass('catalog/resource_eav_attribute');

		if (!empty($store)) {
			$filters->addStoreLabel($store->getId());
		}

		if (!empty($attributeIds)) {
			if (is_array($attributeIds)) {
				$filters->addFieldToFilter('main_table.attribute_id', array('in' => $attributeIds));
			} else {
				$filters->addFieldToFilter('main_table.attribute_id', array('eq' => $attributeIds));
			}
		}
		
		$filters->load();
		
		if (!empty($filters)) {
			foreach ($filters as $filter) {
				$ret .= self::generateFacetXMLFromFilter($filter, $store);
			}
		}
		
		return $ret;
	}
	
	public static function generateFacetXMLCategories()
	{
		return self::generateFacetXMLFromCustom('Category', 0, 'categories', 'select');
	}

	public static function generateFacetXMLPrices($store = null)
	{
		$entry = '';
		$step = self::getPriceNavigationStep($store);

		$entry .= '<entry>' . self::XML_END_LINE;
		$entry .= '<title><![CDATA[Price]]></title>' . self::XML_END_LINE;
		// not set
		// $entry .= '<cs:position>' . 0 . '</cs:position>' . self::XML_END_LINE;
		$entry .= '<cs:attribute>price</cs:attribute>' . self::XML_END_LINE;
		
		$entry .= '<cs:type>dynamic</cs:type>' . self::XML_END_LINE;
		
		if (!empty($step)) {
			$entry .= '<cs:min_range>' . $step . '</cs:min_range>' . self::XML_END_LINE;
		}

		$entry .= '</entry>' . self::XML_END_LINE;

		return $entry;

		// deprecated, because use "union"
		$customerGroups = Mage::getModel('customer/group')->getCollection()->load();
		if ($customerGroups) {
			foreach ($customerGroups as $kCostomerGroup => $customerGroup) {
				$entry .= '<entry>' . self::XML_END_LINE;
				$entry .= '<title><![CDATA[Price (' . $customerGroup->getId() . ')]]></title>' . self::XML_END_LINE;
				// not set
				// $entry .= '<cs:position>' . 0 . '</cs:position>' . self::XML_END_LINE;
				$label_ = Mage::helper('searchanise/ApiSe')->getLabelForPricesUsergroup() . $customerGroup->getId();
				$entry .= '<cs:attribute>' . $label_ . '</cs:attribute>' . self::XML_END_LINE;
				
				$entry .= '<cs:type>dynamic</cs:type>' . self::XML_END_LINE;
				
				if (!empty($step)) {
					$entry .= '<cs:min_range>' . $step . '</cs:min_range>' . self::XML_END_LINE;
				}

				$entry .= '</entry>' . self::XML_END_LINE;
			}
		}
		
		return $entry;
	}
	
	public static function generateFacetXMLTags()
	{
		return self::generateFacetXMLFromCustom('Tag', 0, 'tags', 'select');
	}
	
	public static function getXMLHeader($store = null)
	{
		$url = '';
		
		if (empty($store)) {
			$store = Mage::app()->getStore()->getBaseUrl();
		} else {
			$url = $store->getUrl();
		}
		
		$date = date('c');
		
		return '<?xml version="1.0" encoding="UTF-8"?>' .
			'<feed xmlns="http://www.w3.org/2005/Atom" xmlns:cs="http://searchanise.com/ns/1.0">' . 
			'<title>Searchanise data feed</title>' . 
			"<updated>{$date}</updated>" . 
			"<id><![CDATA[{$url}]]></id>";
	}
	
	public static function getXMLFooter()
	{
		return '</feed>';
	}
}

