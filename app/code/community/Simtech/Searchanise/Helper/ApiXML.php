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

    protected static $flWithoutTags = false;
    
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
        $tagCollection = null;

        if (self::$flWithoutTags) {
            return $tagCollection;
        }
        
        $tagModel = Mage::getModel('tag/tag');
        
        if ($tagModel) {
            $tagCollection = $tagModel->getResourceCollection();
        }
        // Check if tags don't work correctly.
        if (!$tagCollection) {
            self::$flWithoutTags = true;
        
        } else {
            $tagCollection = $tagCollection
                ->setFlag('relation', true)
                ->setActiveFilter();
            
            if (!empty($store)) {
                $tagCollection->addStoreFilter($store->getId(), true);
            }

            $tagCollection = $tagCollection
                ->addPopularity()
                ->addStatusFilter(Mage::getModel('tag/tag')->getApprovedStatus())
                ->addStoresVisibility()
                ->addProductFilter($product->getId())
                ->load();
        }
        
        
        return $tagCollection;
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
     * @param array Mage_Catalog_Model_Product $unitedProducts - Current product + childrens products (if exists)
     * @return float
     */
    private static function getProductQty($product, $store, $unitedProducts = array())
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

                    if ($unitedProducts) {
                        $quantity = 0;
                        foreach ($unitedProducts as $itemProductKey => $itemProduct) {
                            $quantity += self::getProductQty($itemProduct, $store);
                        }
                    }
                }
            }
        }

        return $quantity;
    }

    /**
     * Get product price with tax if it is need
     *
     * @param Mage_Catalog_Model_Product $product
     * @param float $price
     * @return float
     */
    private static function getProductShowPrice($product, $price)
    {
        static $taxHelper;
        static $showPricesTax;

        if (!isset($taxHelper)) {
            $taxHelper = Mage::helper('tax');
            $showPricesTax = ($taxHelper->displayPriceIncludingTax() || $taxHelper->displayBothPrices());
        }

        $finalPrice = $taxHelper->getPrice($product, $price, $showPricesTax);

        return $finalPrice;
    }

    /**
     * Get product minimal price without "Tier Price" (quantity discount) and with tax (if it is need)
     *
     * @param Mage_Catalog_Model_Product $product
     * @param Mage_Core_Model_Store $store
     * @param bool $flagWithChildrenProducts
     * @param int $customerGroupId
     * @param float $groupPrice
     * @return float
     */
    private static function getProductMinimalPrice($product, $store, $flagWithChildrenProducts = true, $customerGroupId = null, $groupPrice = null)
    {
        $minimalPrice = '';

        if ($customerGroupId != null) {
            $product->setCustomerGroupId($customerGroupId);
        }

        if ($groupPrice == null) {
            $minimalPrice = $product->getFinalPrice();
        } else {
            $minimalPrice = $groupPrice;
        }
        $minimalPrice = self::getProductShowPrice($product, $minimalPrice);
        
        if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
            $_priceModel = $product->getPriceModel();
            if ($_priceModel) {
                // [1.5]
                if (version_compare(Mage::getVersion(), '1.6', '<')) {
                    $minimalPrice = $_priceModel->getPrices($product, 'min');
                // [/1.5]
                // [v1.6] [v1.7] [v1.8]
                } else {
                    $minimalPrice = $_priceModel->getTotalPrices($product, 'min', null, false);
                }
                // [/v1.6] [/v1.7] [/v1.8]
                $minimalPrice = self::getProductShowPrice($product, $minimalPrice);
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

    /**
     * Get childs products
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array Mage_Catalog_Model_Resource_Product
     */
    private static function getChildrenProducts($product, $store = null)
    {
        $childrenProducts = array();

        // if CONFIGURABLE OR GROUPED OR BUNDLE
        if (($product->getData('type_id') == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) ||
            ($product->isSuper())) {

            if ($typeInstance = $product->getTypeInstance()) {
                $requiredChildrenIds = $typeInstance->getChildrenIds($product->getId(), true);
                if ($requiredChildrenIds) {
                    $childrenIds = array();

                    foreach ($requiredChildrenIds as $groupedChildrenIds) {
                        $childrenIds = array_merge($childrenIds, $groupedChildrenIds);
                    }

                    if ($childrenIds) {
                        $childrenProducts = self::getProducts($childrenIds, $store);
                    }
                }
            }
        }

        return $childrenProducts;
    }

    private static function getIdAttributeValuesXML($value)
    {
        $strIdValues = '';

        $arrValues = explode(',', $value);
        if (!empty($arrValues)) {
            foreach ($arrValues as $v) {
                if ($v != '') {
                    // Example values: '0', '1', 'AF'.
                    $strIdValues .= '<value><![CDATA[' . $v . ']]></value>';
                }
            }
        }

        return $strIdValues;
    }

    private static function getIdAttributesValuesXML($values)
    {
        $strIdValues = '';

        foreach ($values as $v) {
            $strIdValues .= self::getIdAttributeValuesXML($v);
        }

        return $strIdValues;
    }

    private static function addArrTextAttributeValues($product, $attributeCode, &$arrTextValues)
    {
        $textValues = $product->getResource()->getAttribute($attributeCode)->getFrontend()->getValue($product);
        if ($textValues != '') {
            $arrValues = explode(',', $textValues);
            if (!empty($arrValues)) {
                foreach ($arrValues as $v) {
                    if ($v != '') {
                        $trimValue = trim($v);
                        if ($trimValue != '' && !in_array($trimValue, $arrTextValues)) {
                            $arrTextValues[] .= $trimValue;
                         }
                    }
                }
            }
        }

        return true;
    }

    private static function getTextAttributesValuesXML($products, $attributeCode)
    {
        $strTextValues = '';
        $arrTextValues = array();

        foreach ($products as $p) {
            self::addArrTextAttributeValues($p, $attributeCode, $arrTextValues);
        }
        if ($arrTextValues) {
            foreach ($arrTextValues as $textValue) {
                $strTextValues .= '<value><![CDATA[' . $textValue . ']]></value>';
            }
        }

        return $strTextValues;
    }

    public static function generateProductXML($product, $store = null, $checkData = true)
    {
        $entry = '';
        if ($checkData) {
            if (!$product ||
                !$product->getId() ||
                !$product->getName()
                ) {
                return $entry;
            }
        }

        $unitedProducts = array($product); // current product + childrens products (if exists)
        $childrenProducts = self::getChildrenProducts($product, $store);
        if ($childrenProducts) {
            foreach ($childrenProducts as $childrenProductsKey => $childrenProduct) {
                $unitedProducts[] = $childrenProduct;
            }
        }
        $entry .= '<entry>' . self::XML_END_LINE;
        $entry .= '<id>' . $product->getId() . '</id>' . self::XML_END_LINE;
        
        $entry .= '<title><![CDATA[' . $product->getName() . ']]></title>' . self::XML_END_LINE;
        
        $summary = $product->getData('short_description');
        
        if ($summary == '') { 
            $summary = $product->getData('description');
        }
        $entry .= '<summary><![CDATA[' . $summary. ']]></summary>' . self::XML_END_LINE;
        
        $productUrl = $product->getProductUrl(false);
        $productUrl = htmlspecialchars($productUrl);
        $entry .= '<link href="' . $productUrl . '" />' . self::XML_END_LINE;
        $entry .= '<cs:product_code><![CDATA[' . $product->getSku() . ']]></cs:product_code>' . self::XML_END_LINE;

        // <prices>
        {
            $customerGroups = Mage::getModel('customer/group')->getCollection()->load();

            foreach ($customerGroups as $keyCustomerGroup => $customerGroup) {
                // It is needed because the 'setCustomerGroupId' function works only once.
                $productCurrentGroup = clone $product;
                $customerGroupId = $customerGroup->getId();

                $price = self::getProductMinimalPrice($productCurrentGroup, $store, true, $customerGroupId);
                if ($price != '') {
                    $price = round($price, Mage::helper('searchanise/ApiSe')->getFloatPrecision());
                }

                if ($customerGroupId == Mage_Customer_Model_Group::NOT_LOGGED_IN_ID) {
                    $entry .= '<cs:price>' . $price . '</cs:price>'. self::XML_END_LINE;
                    $defaultPrice = $price; // default price get for not logged user
                }
                $label_ = Mage::helper('searchanise/ApiSe')->getLabelForPricesUsergroup() . $customerGroup->getId();
                $entry .= '<cs:attribute name="' . $label_ . '" type="float">' . $price . '</cs:attribute>' . self::XML_END_LINE;
                unset($productCurrentGroup);
            }
        }
        // </prices>

        // <quantity>
        {
            $quantity = self::getProductQty($product, $store, $unitedProducts);

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
            // Fixme in the feature:
            // products could have different position in different categories, sort by "position" disabled.
            $position = $product->getData('position');
            if ($position) {
                $entry .= '<cs:attribute name="position" type="int">';
                $entry .= $product->getData('position');
                
                $entry .= '</cs:attribute>' . self::XML_END_LINE;
            }
            // end
        }
        // </attributes_position>
        
        // <attributes>
        {
            //~ $product->getAttributes();
            $attributes = Mage::getResourceModel('catalog/product_attribute_collection');
            $attributes
                ->setItemObjectClass('catalog/resource_eav_attribute')
                // ->setOrder('position', 'ASC') // not need, because It will slow with "order"
                ->load();

            if (!empty($attributes)) {
                foreach ($attributes as $attribute) {
                    $attributeCode = $attribute->getAttributeCode();
                    $value = $product->getData($attributeCode);

                    // unitedValues - childrens values + main value
                    $unitedValues = array();
                    {
                        if ($value == '') {
                            // nothing
                        } elseif (is_array($value) && empty($value)) {
                            // nothing
                        } else {
                            $unitedValues[] = $value;
                        }                    
                        if ($childrenProducts) {
                            foreach ($childrenProducts as $childrenProductsKey => $childrenProduct) {
                                $childValue = $childrenProduct->getData($attributeCode);
                                if ($childValue == '') {
                                    // Nothing.
                                } elseif (is_array($childValue) && empty($childValue)) {
                                    // Nothing.
                                } else {
                                    if (!in_array($childValue, $unitedValues)) {
                                        $unitedValues[] = $childValue;
                                    }
                                }
                            }
                        }
                    }
                    $inputType = $attribute->getData('frontend_input');
                    $isSearchable = $attribute->getIsSearchable();
                    $attributeName = 'attribute_' . $attribute->getId();
                    $attributeWeight = 0;

                    if (empty($unitedValues)) {
                        // nothing

                    } elseif ($attributeCode == 'price') {
                        // nothing
                        // already defined in the '<cs:price>' field

                    } elseif ($attributeCode == 'group_price') {
                        // nothing
                        // fixme in the future if need
                    
                    } elseif (
                        $attributeCode == 'short_description' || 
                        $attributeCode == 'description' ||
                        $attributeCode == 'meta_title' || 
                        $attributeCode == 'meta_description' || 
                        $attributeCode == 'meta_keyword') {

                        if ($isSearchable) {
                            // <descriptions>
                                if ($inputType == 'short_description') {
                                    $attributeWeight = self::WEIGHT_SHORT_DESCRIPTION;
                                } elseif ($inputType == 'description') {
                                    $attributeWeight = self::WEIGHT_DESCRIPTION;
                            // </descriptions>

                            // <meta_information>
                                } elseif ($inputType == 'meta_title') {
                                    $attributeWeight = self::WEIGHT_META_TITLE;
                                } elseif ($inputType == 'meta_description') {
                                    $attributeWeight = self::WEIGHT_META_DESCRIPTION;
                                } elseif ($inputType == 'meta_keyword') {
                                    $attributeWeight = self::WEIGHT_META_KEYWORDS;
                            // </meta_information>
                            } else {
                                // Nothing.
                            }
                        }

                        $strTextValues = self::getTextAttributesValuesXML($unitedProducts, $attributeCode);
                        if ($strTextValues != '') {
                            $entry .= '<cs:attribute name="' . $attributeName .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                            // fixme in the future
                            // need for fixed bug of Server
                            $entry .= ' ';
                            // end fixme
                            $entry .= $strTextValues;
                            $entry .= '</cs:attribute>' . self::XML_END_LINE;
                        }

                    // <dates>
                    // It can be used for sort.
                    } elseif ($attributeCode == 'created_at' || $attributeCode == 'updated_at') {
                        // Fixme in the future
                        // may add check for used_for_sort_by
                        $dateTimestamp = Mage::getModel('core/date')->timestamp(strtotime($value));
                        $entry .= '<cs:attribute name="' . $attributeName .'" type="int" text_search="N">';
                        $entry .= $dateTimestamp;
                        $entry .= '</cs:attribute>' . self::XML_END_LINE;
                    // Fixme in the future
                    // Need add other date attributes ('look for' by type)
                    // </dates>

                    // <unused attributes>
                        // <system_attributes>
                        } elseif ($attributeCode == 'status') {
                        } elseif ($attributeCode == 'visibility') {
                        } elseif ($attributeCode == 'has_options') {
                        } elseif ($attributeCode == 'required_options') {
                        } elseif ($attributeCode == 'custom_layout_update') {
                        } elseif ($attributeCode == 'tier_price') { // quantity discount
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

                    } elseif ($inputType == 'select' || $inputType == 'multiselect') {
                        // <id_values>
                        if ($strIdValues = self::getIdAttributesValuesXML($unitedValues)) {
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
                            $strTextValues = self::getTextAttributesValuesXML($unitedProducts, $attributeCode);
                        }

                        if ($strTextValues != '') {
                            $entry .= '<cs:attribute name="' . $attributeCode .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                            // fixme in the future
                            // need for fixed bug of Server
                            $entry .= ' ';
                            // end fixme
                            $entry .= $strTextValues;
                            $entry .= '</cs:attribute>' . self::XML_END_LINE;
                        }
                        // <text_values>

                    } elseif ($inputType == 'text' || $inputType == 'textarea') {
                        if ($isSearchable) {
                            if ($inputType == 'text') {
                                $attributeWeight = self::WEIGHT_TEXT_ATTRIBUTES;
                            } elseif ($inputType == 'textarea') {
                                $attributeWeight = self::WEIGHT_TEXT_AREA_ATTRIBUTES;
                            } else {
                                // Nothing.
                            }
                        }
                        $strTextValues = self::getTextAttributesValuesXML($unitedProducts, $attributeCode);
                        if ($strTextValues != '') {
                            $entry .= '<cs:attribute name="' . $attributeName .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                            // fixme in the future
                            // need for fixed bug of Server
                            $entry .= ' ';
                            // end fixme
                            $entry .= $strTextValues;
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
            $categoryIds = $product->getCategoryIds();
            if (!empty($categoryIds)) {
                foreach ($categoryIds as $catKey => $categoryId) {
                    $entry .= '<value><![CDATA[' . $categoryId . ']]></value>';
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
            
            if ($tags && count($tags) > 0) {
                foreach ($tags as $tag) {
                    if ($tag) {
                        $strTagIds .= '<value><![CDATA[' . $tag->getId() . ']]></value>';
                        $strTagNames .= '<value><![CDATA[' . $tag->getName() . ']]></value>';
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

    private static function validateProductIds($productIds, $store = null)
    {
        $validProductIds = array();
        if ($store) {
            Mage::app()->setCurrentStore($store->getId());
        } else {
            Mage::app()->setCurrentStore(0);
        }

        $products = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('entity_id');

        if ($store) {
            $products->addStoreFilter($store);
        }

        // Already exist automatic definition 'one value' or 'array'.
        $products->addIdFilter($productIds);

        $products->load();
        if ($products) {
            // Not used because 'arrProducts' comprising 'stock_item' field and is 'array(array())'
            // $arrProducts = $products->toArray(array('entity_id'));
            foreach ($products as $product) {
                $validProductIds[] = $product->getId();
            }
        }
        // It is necessary for save memory.
        unset($products);

        return $validProductIds;
    }

    // public static function getProductsByItems($productIds, $store = null, $flagAddMinimalPrice = false, $customerGroupId = null)
    public static function getProducts($productIds, $store = null, $flagAddMinimalPrice = false, $customerGroupId = null)
    {
        // Need for generate correct url and get right products.
        if ($store) {
            Mage::app()->setCurrentStore($store->getId());
        } else {
            Mage::app()->setCurrentStore(0);
        }

        $products = array();
        $productIds = self::validateProductIds($productIds, $store);

        if ($productIds) {
            foreach ($productIds as $key => $productId) {
                if (empty($productId)) {
                    continue;
                }
                
                // It can use various types of data.
                if (is_array($productId)) {
                    if (isset($productId['entity_id'])) {
                        $productId = $productId['entity_id'];
                    }
                }

                try {
                    $product = Mage::getModel('catalog/product')->load($productId);

                } catch (Exception $e) {
                    Mage::helper('searchanise/ApiSe')->log($e->getMessage(), "Error: Script couldn't get product");
                    continue;
                }

                if ($product) {
                    if ($store) {
                        $product->setWebsiteId($store->getWebsiteId());
                    }
                    if ($customerGroupId != null) {
                        $product->setCustomerGroupId($customerGroupId);
                    }

                    $products[] = $product;
                }
            }
        }

        return $products;
    }

    public static function getProductsOld($productIds = null, $store = null, $flagAddMinimalPrice = false, $customerGroupId = null)
    {
        // Need for generate correct url and get right data.
        if ($store) {
            Mage::app()->setCurrentStore($store->getId());
        } else {
            Mage::app()->setCurrentStore(0);
        }

        $productId = 0;

        if (!empty($productIds)) {
            if (!is_array($productIds)) {
                $productId = $productIds;
            } elseif (is_array($productIds) && count($productIds) == 1){
                $flGetOneProduct = true;
                $productId = reset($productIds);
            }
        }

        if (!empty($productId)) {
            $products = array();
            $product = Mage::getModel('catalog/product')->load($productId);

            if ($customerGroupId != null) {
                $product->setCustomerGroupId($customerGroupId);
            }

            $products[] = $product;

        } else {
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
        }

        return $products;
    }

    // Main functions //
    public static function generateProductsXML($productIds = null, $store = null, $flagAddMinimalPrice = false, $checkData = true)
    {
        $ret = '';

        $products = self::getProducts($productIds, $store, $flagAddMinimalPrice);

        // fixme, need delete
        // additional check for products without minimal price
        // deprecated, because use only $flagAddMinimalPrice = false in current module
        if ($flagAddMinimalPrice === true) {
            if (!$products) {
                return self::generateProductsXML($productIds, $store, false);
            }
            $products2 = self::getProducts($productIds, $store, false);
            $arrProduct2 = $products2->toArray();
            if (count($products2) > count($products)) {
                $additionalProductsIds = array();
                foreach ($products2 as $productId => $product) {
                    // fixme array_key_exists
                    if (!array_key_exists($productId, $products)) {
                        $additionalProductsIds[] = $productId;
                    }
                }
                if (!empty($additionalProductsIds)) {
                    $additionalProducts = self::getProducts($additionalProductsIds, $store, false);
                    $arrAdditionalProducts = $additionalProducts->toArray();
                    if ((!empty($arrAdditionalProducts)) && (count($arrAdditionalProducts) != 0)) {
                        foreach ($additionalProducts as $product) {
                            $ret .= self::generateProductXML($product, $store, $checkData);
                        }
                    }
                }
            }
        }
        // end fixme

        if ($products) {
            foreach ($products as $product) {
                $ret .= self::generateProductXML($product, $store, $checkData);
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
            $url = Mage::app()->getStore()->getBaseUrl();
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