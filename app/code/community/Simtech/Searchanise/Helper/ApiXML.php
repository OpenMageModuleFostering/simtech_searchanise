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

    const WEIGHT_SHORT_DESCRIPTION =  0; // not need because use in summary
    const WEIGHT_DESCRIPTION       = 40;

    const WEIGHT_TAGS              = 60;

    // <if_isSearchable>
    const WEIGHT_META_TITLE        =  80;
    const WEIGHT_META_KEYWORDS     = 100;
    const WEIGHT_META_DESCRIPTION  =  40;

    const WEIGHT_SELECT_ATTRIBUTES    = 60;
    const WEIGHT_TEXT_ATTRIBUTES      = 60;
    const WEIGHT_TEXT_AREA_ATTRIBUTES = 40;
    // </if_isSearchable>

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

    /**
     * getProductImageLink
     *
     * @param Mage_Catalog_Model_Product $product
     * @param bool $flagKeepFrame
     * @param int $width
     * @param int $height
     * @return string
     */
    private static function getProductImageLink($product, $flagKeepFrame = true, $width = 70, $height = 70)
    {
        $imageLink = '';

        if ($product) {
            if (empty($imageLink)) {
                $smallImage = $product->getData('small_image');

                if (!empty($smallImage) && $smallImage != 'no_selection') {
                    try {
                        $imageLink = Mage::helper('catalog/image')
                            ->init($product, 'small_image')
                            ->constrainOnly(true)       // Guarantee, that image picture will not be bigger, than it was.
                            ->keepAspectRatio(true)     // Guarantee, that image picture width/height will not be distorted.
                            ->keepFrame($flagKeepFrame) // Guarantee, that image will have dimensions, set in $width/$height
                            ->resize($width, $height);
                    } catch (Exception $e) {
                        // image not exists
                        $imageLink = '';
                    }
                }
            }

            if (empty($imageLink)) {
                $image = $product->getData('image');

                if (!empty($image) && $image != 'no_selection') {
                    try {
                        $imageLink = Mage::helper('catalog/image')
                            ->init($product, 'image')
                            ->constrainOnly(true)       // Guarantee, that image picture will not be bigger, than it was.
                            ->keepAspectRatio(true)     // Guarantee, that image picture width/height will not be distorted.
                            ->keepFrame($flagKeepFrame) // Guarantee, that image will have dimensions, set in $width/$height
                            ->resize($width, $height);
                    } catch (Exception $e) {
                        // image not exists
                        $imageLink = '';
                    }
                }
            }

            if (empty($imageLink)) {
                $thumbnail = $product->getData('thumbnail');
                
                if (!empty($thumbnail) && $thumbnail != 'no_selection') {
                    try {
                        $imageLink = Mage::helper('catalog/image')
                            ->init($product, 'thumbnail')
                            ->constrainOnly(true)       // Guarantee, that image picture will not be bigger, than it was.
                            ->keepAspectRatio(true)     // Guarantee, that image picture width/height will not be distorted.
                            ->keepFrame($flagKeepFrame) // Guarantee, that image will have dimensions, set in $width/$height
                            ->resize($width, $height);
                    } catch (Exception $e) {
                        // image not exists
                        $imageLink = '';
                    }
                }
            }
        }

        return $imageLink;
    }

    /**
     * getProductQty
     *
     * @param Mage_Catalog_Model_Product $product
     * @param Mage_Core_Model_Store $store
     * @param bool $flagWithChildrenProducts
     * @return float
     */
    private static function getProductQty($product, $store, $flagWithChildrenProducts = true)
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

                if (empty($isInStock)) {
                    $quantity = 0;
                } else {
                    $quantity = $stockItem->getQty();

                    if ($flagWithChildrenProducts) {
                        // if CONFIGURABLE OR GROUPED OR BUNDLE
                        if (($product->getData('type_id') == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) ||
                            ($product->isSuper())) {

                            // fixme in the future
                            // maybe exist simple solution get `quantity` for TYPE_BUNDLE or TYPE_CONFIGURABLE product
                            if ($typeInstance = $product->getTypeInstance()) {
                                $requiredChildrenIds = $typeInstance->getChildrenIds($product->getId(), true);
                                if ($requiredChildrenIds) {
                                    $childrenIds = array();
                                    $childrenProducts = null;

                                    foreach ($requiredChildrenIds as $groupedChildrenIds) {
                                        $childrenIds = array_merge($childrenIds, $groupedChildrenIds);
                                    }

                                    if ($childrenIds) {
                                        $childrenProducts = self::getProducts($childrenIds, $store);
                                    }

                                    if ($childrenProducts) {
                                        $quantity = 0;
                                        foreach ($childrenProducts as $childrenProductsKey => $childrenProduct) {
                                            if ($childrenProduct) {
                                                $quantity += self::getProductQty($childrenProduct, $store, false);
                                            }
                                        }
                                    }
                                }
                            }
                            // end fixme
                        }
                    }
                }
            }
        }

        return $quantity;
    }

    /**
     * Get product minimal price without "Tier Price" (quantity discount)
     *
     * @param Mage_Catalog_Model_Product $product
     * @param Mage_Core_Model_Store $store
     * @param bool $flagWithChildrenProducts
     * @return float
     */
    private static function getProductMinimalPrice($product, $store, $flagWithChildrenProducts = true, $customerGroupId = null)
    {
        $minimalPrice = '';
        // The "getMinimalPrice" function gets price with "Tier Price" (quantity discount)
        // $minimalPrice = $product->getMinimalPrice();

        if ($minimalPrice == '') {
            $minimalPrice = $product->getFinalPrice();
        }

        $taxHelper = Mage::helper('tax');

        $showPricesTax = ($taxHelper->displayPriceIncludingTax() || $taxHelper->displayBothPrices());
        $minimalPrice = $taxHelper->getPrice($product, $product->getFinalPrice(), $showPricesTax);

        if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
            $_priceModel  = $product->getPriceModel();
            if ($_priceModel) {
                // [1.5]
                if (version_compare(Mage::getVersion(), '1.6', '<')) {
                    $minimalPrice = $_priceModel->getPrices($product, 'min');
                // [/1.5]
                // [1.6] [1.7]
                } else {
                    $minimalPrice = $_priceModel->getTotalPrices($product, 'min', null, false);
                }
                // [/1.6] [/1.7]                
            }

        } elseif ($flagWithChildrenProducts) {
            if ($product->isGrouped()) {
                // fixme in the future
                // maybe exist better solution get `minimalPrice` for `Grouped` product
                if ($typeInstance = $product->getTypeInstance()) {
                    $requiredChildrenIds = $typeInstance->getChildrenIds($product->getId(), true);
                    if ($requiredChildrenIds) {
                        $childrenIds = array();
                        $childrenProducts = null;

                        foreach ($requiredChildrenIds as $groupedChildrenIds) {
                            $childrenIds = array_merge($childrenIds, $groupedChildrenIds);
                        }

                        if ($childrenIds) {
                            $childrenProducts = self::getProducts($childrenIds, $store, false, $customerGroupId);
                        }

                        if ($childrenProducts) {
                            $minimalPrice = '';

                            foreach ($childrenProducts as $childrenProductsKey => $childrenProduct) {
                                if ($childrenProduct) {
                                    $minimalPriceChildren = self::getProductMinimalPrice($childrenProduct, $store, false, $customerGroupId);

                                    if (($minimalPriceChildren < $minimalPrice) || 
                                        ($minimalPrice == '')) {
                                        $minimalPrice = $minimalPriceChildren;
                                    }
                                }
                            }
                        }
                    }
                }
                // end fixme
            }
        }

        return $minimalPrice;
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
        // maybe exist better solution get customerGroupPrices
        $customerGroups = Mage::getModel('customer/group')->getCollection()->load();
        $defaultPrice = '';

        if ($customerGroups) {
            foreach ($customerGroups as $kCostomerGroup => $customerGroup) {
                $price = '';

                $productsCustomerGroup = self::getProducts($product->getId(), $store, false, $customerGroup->getId());
                if (($productsCustomerGroup) && (count($productsCustomerGroup) > 0)) {
                    foreach ($productsCustomerGroup as $productCustomerGroup) {
                        $price = self::getProductMinimalPrice($productCustomerGroup, $store, true, $customerGroup->getId());
                        break;
                    }
                } else {
                    $price = self::getProductMinimalPrice($product, $store, true, $customerGroup->getId());
                }

                if ($price != '') {
                    $price = round($price, Mage::helper('searchanise/ApiSe')->getFloatPrecision());
                }

                if ($customerGroup->getId() == Mage_Customer_Model_Group::NOT_LOGGED_IN_ID) {
                    $entry .= '<cs:price>' . $price . '</cs:price>'. self::XML_END_LINE;
                    $defaultPrice = $price; // need in `<attributes>` with $inputType == 'price'
                }
                $label_ = Mage::helper('searchanise/ApiSe')->getLabelForPricesUsergroup() . $customerGroup->getId();
                $entry .= '<cs:attribute name="' . $label_ . '" type="float">' . $price . '</cs:attribute>' . self::XML_END_LINE;
            }
        }

        $entry .= '<cs:product_code><![CDATA[' . $product->getSku() . ']]></cs:product_code>' . self::XML_END_LINE;

        // <quantity>
        {
            $quantity = self::getProductQty($product, $store, true);

            $entry .= '<cs:quantity>' . ceil($quantity) . '</cs:quantity>' . self::XML_END_LINE;
            $isInStock = $quantity > 0;
            if ($isInStock) {
                $entry .= '<cs:attribute name="is_in_stock" type="text" text_search="N">' . $isInStock . '</cs:attribute>' . self::XML_END_LINE;
            }
            $quantity = round($quantity, Mage::helper('searchanise/ApiSe')->getFloatPrecision());
            $entry .= '<cs:attribute name="quantity_decimals" type="float">' . $quantity . '</cs:attribute>' . self::XML_END_LINE;
        }
        // </quantity>

        // <image_link>
        {
            // Show images without white field
            // Example: image 360 x 535 => 47 х 70
            $flagKeepFrame = false;
            $imageLink = self::getProductImageLink($product, $flagKeepFrame);

            if ($imageLink != '') {
                $entry .= '<cs:image_link><![CDATA[' . $imageLink . ']]></cs:image_link>' . self::XML_END_LINE;
            }
        }
        // </image_link>
        
        // <attributes_position>
        {
            $position = $product->getData('position');
            if ($position) {
                $entry .= '<cs:attribute name="position" type="int">';
                
                // fixme in the feature
                // sort by "position" disabled
                $entry .= $product->getData('position');
                
                $entry .= '</cs:attribute>' . self::XML_END_LINE;
            }
        }
        // </attributes_position>
        
        // <attributes>
        {
            //~ $product->getAttributes();
            $attributes = Mage::getResourceModel('catalog/product_attribute_collection');
            $attributes
                ->setItemObjectClass('catalog/resource_eav_attribute')
                // ->setOrder('position', 'ASC') // not need, because "order" will slow
                ->load();

            if (!empty($attributes)) {
                foreach ($attributes as $attribute) {
                    $attributeCode = $attribute->getAttributeCode();
                    $value = $product->getData($attributeCode);
                    $inputType = $attribute->getData('frontend_input');
                    $isSearchable = $attribute->getIsSearchable();
                    $attributeName = 'attribute_' . $attribute->getId();
                    $attributeWeight = 0;

                    if ($value == '') {
                        // nothing

                    } elseif (is_array($value) && empty($value)) {
                        // nothing

                    } elseif ($attributeCode == 'price') {
                        // nothing
                        // already defined in the '<cs:price>' field

                    } elseif ($attributeCode == 'group_price') {
                        // nothing
                        // fixme in the future if need

                    } elseif ($attributeCode == 'short_description') {
                        if ($isSearchable) {
                            $attributeWeight = self::WEIGHT_SHORT_DESCRIPTION;
                        }
                        $entry .= '<cs:attribute name="' . $attributeName .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                        $entry .= '<![CDATA[' . $value . ']]>';
                        $entry .= '</cs:attribute>' . self::XML_END_LINE;

                    } elseif ($attributeCode == 'description') {
                        if ($isSearchable) {
                            $attributeWeight = self::WEIGHT_DESCRIPTION;
                        }
                        $entry .= '<cs:attribute name="' . $attributeName .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                        $entry .= '<![CDATA[' . $value . ']]>';
                        $entry .= '</cs:attribute>' . self::XML_END_LINE;

                    // <meta_information>
                        } elseif ($attributeCode == 'meta_title') {
                            if ($isSearchable) {
                                $attributeWeight = self::WEIGHT_META_TITLE;
                            }
                            $entry .= '<cs:attribute name="' . $attributeName .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                            $entry .= '<![CDATA[' . $value . ']]>';
                            $entry .= '</cs:attribute>' . self::XML_END_LINE;

                        } elseif ($attributeCode == 'meta_description') {
                            if ($isSearchable) {
                                $attributeWeight = self::WEIGHT_META_DESCRIPTION;
                            }
                            $entry .= '<cs:attribute name="' . $attributeName .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                            $entry .= '<![CDATA[' . $value . ']]>';
                            $entry .= '</cs:attribute>' . self::XML_END_LINE;

                        } elseif ($attributeCode == 'meta_keyword') {
                            if ($isSearchable) {
                                $attributeWeight = self::WEIGHT_META_KEYWORDS;
                            }
                            $entry .= '<cs:attribute name="' . $attributeName .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                            $entry .= '<![CDATA[' . $value . ']]>';
                            $entry .= '</cs:attribute>' . self::XML_END_LINE;
                    // </meta_information>

                    // <unused attributes>
                        // <system_attributes>
                        } elseif ($attributeCode == 'status') {
                        } elseif ($attributeCode == 'visibility') {
                        } elseif ($attributeCode == 'has_options') {
                        } elseif ($attributeCode == 'required_options') {
                        } elseif ($attributeCode == 'custom_layout_update') {
                        } elseif ($attributeCode == 'tier_price') { // quantity discount
                        } elseif ($attributeCode == 'created_at') { // date
                        } elseif ($attributeCode == 'updated_at') { // date
                        } elseif ($attributeCode == 'image_label') {
                        } elseif ($attributeCode == 'small_image_label') {
                        } elseif ($attributeCode == 'thumbnail_label') {
                        } elseif ($attributeCode == 'url_key') { // seo name
                        // <system_attributes>
                    // </unused attributes>

                    } elseif ($inputType == 'price') {
                        $entry .= '<cs:attribute name="' . $attributeName .'" type="float">';
                        $entry .= $value;
                        $entry .= '</cs:attribute>' . self::XML_END_LINE;

                    } elseif ($inputType == 'select') {
                        // <id_value>
                        // Example values: '0', '1', 'AF'.
                        $entry .= '<cs:attribute name="' . $attributeName .'" type="text" text_search="N">';
                        $entry .= '<![CDATA[' . $value . ']]>';
                        $entry .= '</cs:attribute>' . self::XML_END_LINE;
                        // </id_value>

                        // <text_value>
                        if ($isSearchable) {
                            $attributeWeight = self::WEIGHT_SELECT_ATTRIBUTES;
                            $textValue = $product->getResource()->getAttribute($attributeCode)->getFrontend()->getValue($product);

                            if ($textValue != '') {
                                $entry .= '<cs:attribute name="' . $attributeCode .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                                // fixme in the future
                                // need for fixed bug of Server
                                $entry .= ' ';
                                // end fixme
                                $entry .= '<![CDATA[' . $textValue . ']]>';
                                $entry .= '</cs:attribute>' . self::XML_END_LINE;
                            }
                        }
                        // <text_value>

                    } elseif ($inputType == 'multiselect') {
                        // <id_values>
                        $strIdValues = '';
                        if ($value != '') {
                            $arrValues = explode(',', $value);
                            if (!empty($arrValues)) {
                                foreach ($arrValues as $v) {
                                    if ($v != '') {
                                        $strIdValues .= '<value>' . $v . '</value>';
                                    }
                                }
                            }
                        }

                        if ($strIdValues != '') {
                            $entry .= '<cs:attribute name="' . $attributeName .'" type="text">';
                            // fixme in the future
                            // need for fixed bug of Server
                            $entry .= ' ';
                            // end fixme

                            $entry .= $strIdValues;
                            $entry .= '</cs:attribute>' . self::XML_END_LINE;
                        }
                        // </id_values>

                        // <text_values>
                        $strTextValues = '';
                        if ($isSearchable) {
                            $attributeWeight = self::WEIGHT_SELECT_ATTRIBUTES;
                            $textValues = $product->getResource()->getAttribute($attributeCode)->getFrontend()->getValue($product);
                            if ($textValues != '') {
                                $arrValues = explode(',', $textValues);
                                if (!empty($arrValues)) {
                                    foreach ($arrValues as $v) {
                                        if ($v != '') {
                                            $trimValue = trim($v);
                                            $strTextValues .= '<value><![CDATA[' . $trimValue . ']]></value>';
                                        }
                                    }
                                }
                            }
                        }

                        if ($strIdValues != '') {
                            $entry .= '<cs:attribute name="' . $attributeCode .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                            // fixme in the future
                            // need for fixed bug of Server
                            $entry .= ' ';
                            // end fixme
                            $entry .= $strTextValues;
                            $entry .= '</cs:attribute>' . self::XML_END_LINE;
                        }
                        // <text_values>

                    } elseif ($inputType == 'text') {
                        if ($isSearchable) {
                            $attributeWeight = self::WEIGHT_TEXT_ATTRIBUTES;
                        }

                        $entry .= '<cs:attribute name="' . $attributeName .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                        $entry .= '<![CDATA[' . $value . ']]>';
                        $entry .= '</cs:attribute>' . self::XML_END_LINE;

                    } elseif ($inputType == 'textarea') {
                        if ($isSearchable) {
                            $attributeWeight = self::WEIGHT_TEXT_AREA_ATTRIBUTES;
                        }

                        $entry .= '<cs:attribute name="' . $attributeName .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                        $entry .= '<![CDATA[' . $value . ']]>';
                        $entry .= '</cs:attribute>' . self::XML_END_LINE;

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
        $entry .= '<cs:attribute name="status" type="text" text_search="N">' . $product->getStatus() . '</cs:attribute>' . self::XML_END_LINE;
        // </status>
        
        // <visibility>
        $entry .= '<cs:attribute name="visibility" type="text" text_search="N">' . $product->getData('visibility'). '</cs:attribute>' . self::XML_END_LINE;
        // </visibility>
        
        // <tags>
        {
            $strTagIds = '';
            $strTagNames = '';

            $tags = self::getTagCollection($product, $store);
            
            if ($tags) {
                foreach ($tags as $tag) {
                    if ($tag != '') {
                        $strTagIds .= '<value>' . $tag->getId() . '</value>';
                        $strTagNames .= '<value>' . $tag->getName() . '</value>';
                    }
                }
            }

            if ($strTagIds != '') {
                $entry .= '<cs:attribute name="tag_ids" type="text" text_search="N">';
                // fixme in the future
                // need for fixed bug of Server
                $entry .= ' ';
                // end fixme
                $entry .= $strTagIds;
                $entry .= '</cs:attribute>' . self::XML_END_LINE;

                $entry .= '<cs:attribute name="tags" type="text" text_search="Y" weight="' . self::WEIGHT_TAGS .'">';
                // fixme in the future
                // need for fixed bug of Server
                $entry .= ' ';
                // end fixme
                $entry .= $strTagNames;
                $entry .= '</cs:attribute>' . self::XML_END_LINE;
            }
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

    public static function checkFacet($attribute)
    {
        $isFilterable         = $attribute->getIsFilterable();
        $isFilterableInSearch = $attribute->getIsFilterableInSearch();
        
        return $isFilterable || $isFilterableInSearch;
    }

    public static function generateFacetXMLFromFilter($filter, $store = null)
    {
        $entry = '';

        if (self::checkFacet($filter)) {
            $attributeType = '';

            $inputType = $filter->getData('frontend_input');

            // "Can be used only with catalog input type Dropdown, Multiple Select and Price".
            if (($inputType == 'select') || ($inputType == 'multiselect')) {
                $attributeType = '<cs:type>select</cs:type>' . self::XML_END_LINE;
                
            } elseif ($inputType == 'price') {
                $attributeType = '<cs:type>dynamic</cs:type>' . self::XML_END_LINE;
                $step = self::getPriceNavigationStep($store);
                
                if (!empty($step)) {
                    $attributeType .= '<cs:min_range>' . $step . '</cs:min_range>' . self::XML_END_LINE;
                }
            } else {
                // attribute is not filtrable
                // nothing
            }

            if ($attributeType != '') {
                $entry = '<entry>' . self::XML_END_LINE;
                $entry .= '<title><![CDATA[' . $filter->getData('frontend_label') . ']]></title>' . self::XML_END_LINE;
                $entry .= '<cs:position>' . $filter->getPosition() . '</cs:position>' . self::XML_END_LINE;           

                $attributeCode = $filter->getAttributeCode();

                if ($attributeCode == 'price') {
                    $labelAttribute = 'price';
                } else {
                    $labelAttribute = 'attribute_' . $filter->getId();
                }
                
                $entry .= '<cs:attribute>' . $labelAttribute . '</cs:attribute>' . self::XML_END_LINE;
                $entry .= $attributeType;
                $entry .= '</entry>' . self::XML_END_LINE;
            }
        }
        
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

    private static function getProducts($productIds = null, $store = null, $flagAddMinimalPrice = false, $customerGroupId = null)
    {
        // Need for generate correct url and get right products.
        if ($store) {
            Mage::app()->setCurrentStore($store->getId());
        } else {
            Mage::app()->setCurrentStore(0);
        }
        
        $products = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addUrlRewrite();

        if ($customerGroupId != null) {
            if ($store) {
                $products->addPriceData($customerGroupId, $store->getWebsiteId());
            } else {
                $products->addPriceData($customerGroupId);
            }
        }
            
        if (!empty($store)) {
            $products
                ->setStoreId($store)
                ->addStoreFilter($store);
        } else {
            // nothing
        }
        
        if (!empty($productIds)) {
            // Already exist automatic definition 'one value' or 'array'.
            $products->addIdFilter($productIds);
        }

        if ($flagAddMinimalPrice == true) {
            $products->addMinimalPrice();
        }

        $products->load();

        return $products;
    }

    // Main functions //
    public static function generateProductsXML($productIds = null, $store = null, $flagAddMinimalPrice = false)
    {
        $ret = '';

        $products = self::getProducts($productIds, $store, $flagAddMinimalPrice);
        $arrProduct = $products->toArray();

        // fixme, need delete
        // additional check for products without minimal price
        // deprecated, because use only $flagAddMinimalPrice = false in current module
        if ($flagAddMinimalPrice === true) {
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
        // end fixme

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

        if ($store) {
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

        $filters = Mage::getResourceModel('catalog/product_attribute_collection')
            ->setItemObjectClass('catalog/resource_eav_attribute')
            ->addFieldToFilter('main_table.frontend_input', array('eq' => 'price'));

        if ($store) {
            $filters->addStoreLabel($store->getId());
        }

        $filters->load();
        
        if (!empty($filters)) {
            foreach ($filters as $filter) {
                $entry .= self::generateFacetXMLFromFilter($filter, $store);
            }
        }

        return $entry;
    }
    
    public static function generateFacetXMLTags()
    {
        return self::generateFacetXMLFromCustom('Tag', 0, 'tag_ids', 'select');
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