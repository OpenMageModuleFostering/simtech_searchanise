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

    // Tweaks
    const ADDITIONAL_CHECK_FOR_INCORRECT_PRODUCTS = false;

    protected static $flWithoutTags = false;

    public static $isGetProductsByItems = false;

    public static function setIsGetProductsByItems($value = false)
    {
        self::$isGetProductsByItems = $value;
    }
    
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
     * generateProductImage
     *
     * @param Mage_Catalog_Model_Product $product
     * @param bool $flagKeepFrame
     * @param int $width
     * @param int $height
     * @return Mage_Catalog_Model_Product_Image $image
     */
    private static function generateProductImage($product, $imageType = 'small_image', $flagKeepFrame = true, $width = 70, $height = 70)
    {
        $image = '';
        $productImage = $product->getData($imageType);

        if (!empty($productImage) && $productImage != 'no_selection') {
            try {
                $image = Mage::helper('catalog/image')
                    ->init($product, $imageType)
                    ->constrainOnly(true)        // Guarantee, that image picture will not be bigger, than it was.
                    ->keepAspectRatio(true)      // Guarantee, that image picture width/height will not be distorted.
                    ->keepFrame($flagKeepFrame); // Guarantee, that image will have dimensions, set in $width/$height

                if ($width || $height) {
                    $image->resize($width, $height);
                }
            } catch (Exception $e) {
                // image not exists
                $image = '';
            }
        }

        return $image;
    }

    /**
     * getProductImageLink
     *
     * @param Mage_Catalog_Model_Product $product
     * @param bool $flagKeepFrame
     * @param int $width
     * @param int $height
     * @return Mage_Catalog_Model_Product_Image $image
     */
    private static function getProductImageLink($product, $flagKeepFrame = true, $width = 70, $height = 70)
    {
        $image = '';

        if ($product) {
            if (empty($image)) {
                $image = self::generateProductImage($product, 'small_image', $flagKeepFrame, $width, $height);
            }
            if (empty($image)) {
                $image = self::generateProductImage($product, 'image', $flagKeepFrame, $width, $height);
            }
            if (empty($image)) {
                $image = self::generateProductImage($product, 'thumbnail', $flagKeepFrame, $width, $height);
            }
        }

        return $image;
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
     * @param Mage_Catalog_Model_Resource_Product_Collection $childrenProducts
     * @param int $customerGroupId
     * @param float $groupPrice
     * @return float
     */
    private static function getProductMinimalPrice($product, $store, $childrenProducts = null, $customerGroupId = null, $groupPrice = null)
    {
        $minimalPrice = false;

        if ($customerGroupId != null) {
            $product->setCustomerGroupId($customerGroupId);
        }

        $_priceModel = $product->getPriceModel();
        
        if ($_priceModel && $product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
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

        } elseif ($product->isGrouped() && $childrenProducts) {
            // fixme in the future
            // maybe exist better solution get `minimalPrice` for `Grouped` product
            $minimalPrice = '';

            foreach ($childrenProducts as $childrenProductsKey => $childrenProduct) {
                if ($childrenProduct) {
                    $minimalPriceChildren = self::getProductMinimalPrice($childrenProduct, $store, null, $customerGroupId);

                    if (($minimalPriceChildren < $minimalPrice) || 
                        ($minimalPrice == '')) {
                        $minimalPrice = $minimalPriceChildren;
                    }
                }
            }
            // end fixme
        } else {
            if ($groupPrice != null) {
                $minimalPrice = $groupPrice;
            } else {
                $isCorrectProduct = true;

                // Additional check for incorrect configurable products.
                if (self::ADDITIONAL_CHECK_FOR_INCORRECT_PRODUCTS) {
                    static $arrIncorrectProductIds = array();

                    if (in_array($product->getId(), $arrIncorrectProductIds)) {
                        $isCorrectProduct = false;
                    }

                    if ($isCorrectProduct && $product->isConfigurable()) {
                        try {
                            $attributes = $product->getTypeInstance(true)
                                ->getConfigurableAttributes($product);
                            foreach ($attributes as $attribute) {
                                if (!$attribute->getProductAttribute()) {
                                    $isCorrectProduct = false;
                                    $arrIncorrectProductIds[] = $product->getId();
                                    Mage::helper('searchanise/ApiSe')->log('Incorrect configurable product ID = ' . $product->getId(), 'Warning');
                                    break;
                                }
                            }
                        } catch (Exception $e) {
                            Mage::helper('searchanise/ApiSe')->log($e->getMessage(), "Error: Script couldn't check for incorrect configurable product ID = " . $product->getId());
                        }
                    }
                }
                // end check

                if ($isCorrectProduct) {
                    try {
                        // $minimalPrice = $product->getFinalPrice();
                    } catch (Exception $e) {
                        $minimalPrice = false;
                        Mage::helper('searchanise/ApiSe')->log($e->getMessage(), "Error: Script couldn't get final price for product ID = " . $product->getId());
                    }
                }
            }

            if ($minimalPrice === false) {
                $minimalPrice = $product->getPrice();
            }

            $minimalPrice = self::getProductShowPrice($product, $minimalPrice);
        }

        return $minimalPrice;
    }

    private static function _generateProductPricesXML($product, $childrenProducts = null, $store = null)
    {
        $result = '';

        static $customerGroups;

        if (!isset($customerGroups)) {
            $customerGroups = Mage::getModel('customer/group')->getCollection()->load();
        }

        if ($customerGroups) {
            foreach ($customerGroups as $keyCustomerGroup => $customerGroup) {
                // It is needed because the 'setCustomerGroupId' function works only once.
                $productCurrentGroup = clone $product;
                $customerGroupId = $customerGroup->getId();

                $price = self::getProductMinimalPrice($productCurrentGroup, $store, $childrenProducts, $customerGroupId);
                if ($price != '') {
                    $price = round($price, Mage::helper('searchanise/ApiSe')->getFloatPrecision());
                }

                if ($customerGroupId == Mage_Customer_Model_Group::NOT_LOGGED_IN_ID) {
                    $result .= '<cs:price>' . $price . '</cs:price>'. self::XML_END_LINE;
                    $defaultPrice = $price; // default price get for not logged user
                }
                $label_ = Mage::helper('searchanise/ApiSe')->getLabelForPricesUsergroup() . $customerGroup->getId();
                $result .= '<cs:attribute name="' . $label_ . '" type="float">' . $price . '</cs:attribute>' . self::XML_END_LINE;
                unset($productCurrentGroup);
            }
        }

        return $result;
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
                        $childrenProducts = self::getProducts($childrenIds, $store, null);
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

    private static function addArrTextAttributeValues($product, $attributeCode, $inputType, &$arrTextValues)
    {
        $textValues = $product->getResource()->getAttribute($attributeCode)->getFrontend()->getValue($product);

        if ($textValues != '') {
            if ($inputType == 'multiselect') {
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
            } else {
                $trimValue = trim($textValues);
                $arrTextValues[] .= $trimValue;
            }
        }

        return true;
    }

    private static function getTextAttributesValuesXML($products, $attributeCode, $inputType)
    {
        $strTextValues = '';
        $arrTextValues = array();

        foreach ($products as $p) {
            self::addArrTextAttributeValues($p, $attributeCode, $inputType, $arrTextValues);
        }
        if ($arrTextValues) {
            foreach ($arrTextValues as $textValue) {
                $strTextValues .= '<value><![CDATA[' . $textValue . ']]></value>';
            }
        }

        return $strTextValues;
    }

    private static function _generateProductAttributesXML($product, $childrenProducts = null, $unitedProducts = null, $store = null)
    {
        $result = '';

        static $attributes;

        if (!isset($attributes)) {
            $attributes = Mage::getResourceModel('catalog/product_attribute_collection');
            $attributes
                ->setItemObjectClass('catalog/resource_eav_attribute')
                // ->setOrder('position', 'ASC') // not need, because It will slow with "order"
                ->load();
        }
        
        if ($attributes) {
            $useFullFeed = Mage::helper('searchanise/ApiSe')->getUseFullFeed();
            foreach ($attributes as $attribute) {
                $attributeCode = $attribute->getAttributeCode();
                $value = $product->getData($attributeCode);

                // unitedValues - main value + childrens values
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
                $isVisibleInAdvancedSearch = $attribute->getIsVisibleInAdvancedSearch();
                $usedForSortBy = $attribute->getUsedForSortBy();
                $attributeName = 'attribute_' . $attribute->getId();
                $attributeWeight = 0;
                $isRequireAttribute = $useFullFeed || $isSearchable || $isVisibleInAdvancedSearch || $usedForSortBy;

                if (empty($unitedValues)) {
                    // nothing

                // <system_attributes>
                    } elseif ($attributeCode == 'price') {
                        // already defined in the '<cs:price>' field

                    } elseif ($attributeCode == 'status' || $attributeCode == 'visibility') {
                        $result .= '<cs:attribute name="' . $attributeCode . '" type="text" text_search="N">';
                        $result .= $value;
                        $result .= '</cs:attribute>' . self::XML_END_LINE;

                    } elseif ($attributeCode == 'weight') {
                        $strTextValues = self::getTextAttributesValuesXML($unitedProducts, $attributeCode, $inputType);
                        if ($strTextValues != '') {
                            $result .= '<cs:attribute name="' . $attributeCode . '" type="float" text_search="N">';
                            // fixme in the future
                            // need for fixed bug of Server
                            $result .= ' ';
                            // end fixme
                            $result .= $strTextValues;
                            $result .= '</cs:attribute>' . self::XML_END_LINE;
                        }

                    // <dates>
                    } elseif ($attributeCode == 'created_at' || $attributeCode == 'updated_at') {
                        $dateTimestamp = Mage::getModel('core/date')->timestamp(strtotime($value));
                        $result .= '<cs:attribute name="' . $attributeCode .'" type="int" text_search="N">';
                        $result .= $dateTimestamp;
                        $result .= '</cs:attribute>' . self::XML_END_LINE;
                    // </dates>

                    } elseif ($attributeCode == 'has_options') {
                    } elseif ($attributeCode == 'required_options') {
                    } elseif ($attributeCode == 'custom_layout_update') {
                    } elseif ($attributeCode == 'tier_price') { // quantity discount
                    } elseif ($attributeCode == 'image_label') {
                    } elseif ($attributeCode == 'small_image_label') {
                    } elseif ($attributeCode == 'thumbnail_label') {
                    } elseif ($attributeCode == 'url_key') { // seo name
                // <system_attributes>

                } elseif ($attributeCode == 'group_price') {
                    // nothing
                    // fixme in the future if need
                
                } elseif (
                    $attributeCode == 'short_description' || 
                    $attributeCode == 'description' ||
                    $attributeCode == 'meta_title' || 
                    $attributeCode == 'meta_description' || 
                    $attributeCode == 'meta_keyword') {

                    if ($isRequireAttribute) {
                        if ($isSearchable) {
                            if ($attributeCode == 'short_description') {
                                $attributeWeight = self::WEIGHT_SHORT_DESCRIPTION;
                            } elseif ($attributeCode == 'description') {
                                $attributeWeight = self::WEIGHT_DESCRIPTION;
                            } elseif ($attributeCode == 'meta_title') {
                                $attributeWeight = self::WEIGHT_META_TITLE;
                            } elseif ($attributeCode == 'meta_description') {
                                $attributeWeight = self::WEIGHT_META_DESCRIPTION;
                            } elseif ($attributeCode == 'meta_keyword') {
                                $attributeWeight = self::WEIGHT_META_KEYWORDS;
                            } else {
                                // Nothing.
                            }
                        }

                        $strTextValues = self::getTextAttributesValuesXML($unitedProducts, $attributeCode, $inputType);
                        if ($strTextValues != '') {
                            if ($attributeCode == 'description') {
                                if ($value != '') {
                                    $result .= '<cs:attribute name="' . $attributeCode .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                                    $result .= '<![CDATA[' . $value . ']]>';
                                    $result .= '</cs:attribute>' . self::XML_END_LINE;
                                }

                                $result .= '<cs:attribute name="se_grouped_' . $attributeCode .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                            } else {
                                $result .= '<cs:attribute name="' . $attributeCode .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                            }
                            // fixme in the future
                            // need for fixed bug of Server
                            $result .= ' ';
                            // end fixme
                            $result .= $strTextValues;
                            $result .= '</cs:attribute>' . self::XML_END_LINE;
                        }
                    }

                } elseif ($inputType == 'price') {
                    // Other attributes with type 'price'.
                    $result .= '<cs:attribute name="' . $attributeName .'" type="float">';
                    $result .= $value;
                    $result .= '</cs:attribute>' . self::XML_END_LINE;

                } elseif ($inputType == 'select' || $inputType == 'multiselect') {
                    // <id_values>
                    if ($strIdValues = self::getIdAttributesValuesXML($unitedValues)) {
                        $result .= '<cs:attribute name="' . $attributeName .'" type="text">';
                        // fixme in the future
                        // need for fixed bug of Server
                        $result .= ' ';
                        // end fixme

                        $result .= $strIdValues;
                        $result .= '</cs:attribute>' . self::XML_END_LINE;
                    }
                    // </id_values>

                    // <text_values>
                    if ($isRequireAttribute) {
                        $strTextValues = self::getTextAttributesValuesXML($unitedProducts, $attributeCode, $inputType);

                        if ($strTextValues != '') {
                            if ($isSearchable) {
                                $attributeWeight = self::WEIGHT_SELECT_ATTRIBUTES;
                            }

                            $result .= '<cs:attribute name="' . $attributeCode .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                            // fixme in the future
                            // need for fixed bug of Server
                            $result .= ' ';
                            // end fixme
                            $result .= $strTextValues;
                            $result .= '</cs:attribute>' . self::XML_END_LINE;
                        }
                    }
                    // </text_values>

                } elseif ($inputType == 'text' || $inputType == 'textarea') {
                    if ($isRequireAttribute) {
                        $strTextValues = self::getTextAttributesValuesXML($unitedProducts, $attributeCode, $inputType);

                        if ($strTextValues != '') {
                            if ($isSearchable) {
                                if ($inputType == 'text') {
                                    $attributeWeight = self::WEIGHT_TEXT_ATTRIBUTES;
                                } elseif ($inputType == 'textarea') {
                                    $attributeWeight = self::WEIGHT_TEXT_AREA_ATTRIBUTES;
                                } else {
                                    // Nothing.
                                }
                            }
                            $result .= '<cs:attribute name="' . $attributeName .'" type="text" text_search="Y" weight="' . $attributeWeight . '">';
                            // fixme in the future
                            // need for fixed bug of Server
                            $result .= ' ';
                            // end fixme
                            $result .= $strTextValues;
                            $result .= '</cs:attribute>' . self::XML_END_LINE;
                        }
                    }
                } elseif ($inputType == 'date') {
                    if ($isRequireAttribute) {
                        $dateTimestamp = Mage::getModel('core/date')->timestamp(strtotime($value));
                        $result .= '<cs:attribute name="' . $attributeCode .'" type="int" text_search="N">';
                        $result .= $dateTimestamp;
                        $result .= '</cs:attribute>' . self::XML_END_LINE;
                    }
                } elseif ($inputType == 'media_image') {
                    if ($isRequireAttribute) {
                        $image = self::generateProductImage($product, $attributeCode, true, 0, 0);
                        if (!empty($image)) {
                            $imageLink = '' . $image;
                            if (!empty($imageLink)) {
                                $result .= '<cs:attribute name="' . $attributeCode .'" type="text" text_search="N" weight="0"><![CDATA[';
                                $result .= $imageLink;
                                $result .= ']]></cs:attribute>' . self::XML_END_LINE;
                            }
                        }
                    }
                } elseif ($inputType == 'gallery') {
                    // Nothing.
                } else {
                    // Attribute not will use.
                }
            }
        }

        return $result;
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

        $entry .= self::_generateProductPricesXML($product, $childrenProducts, $store);

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
        
        $entry .= self::_generateProductAttributesXML($product, $childrenProducts, $unitedProducts, $store);

        // <categories>
        {
            $entry .= '<cs:attribute name="category_ids" type="text">';
            // fixme in the future
            // need for fixed bug of Server
            $entry .= ' ';
            $nameCategories = ' ';
            // end fixme
            $categoryIds = $product->getCategoryIds();
            if (!empty($categoryIds)) {
                foreach ($categoryIds as $catKey => $categoryId) {
                    $entry .= '<value><![CDATA[' . $categoryId . ']]></value>';
                    $category = Mage::getModel('catalog/category')->load($categoryId);
                    if ($category) {
                        $nameCategories .= '<value><![CDATA[' . $category->getName() . ']]></value>';
                    }
                }
            }
            $entry .= '</cs:attribute>' . self::XML_END_LINE;

            if ($nameCategories != ' ') {
                $attributeWeight = 0;
                $entry .= '<cs:attribute name="categories" type="text" text_search="N" weight="' . $attributeWeight . '">';
                $entry .= $nameCategories;
                $entry .= '</cs:attribute>' . self::XML_END_LINE;
            }
        }
        // </categories>

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

    private static function _getProductsByItems($productIds, $store = null)
    {
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
                    $products[] = $product;
                }
            }
        }

        return $products;
    }

    public static function getProducts($productIds = null, $store = null, $customerGroupId = null)
    {
        $resultProducts = array();
        if (empty($productIds)) {
            return $resultProducts;
        }

        // Need for generate correct url and get right products.
        if ($store) {
            Mage::app()->setCurrentStore($store->getId());
        } else {
            Mage::app()->setCurrentStore(0);
        }

        static $arrProducts = array();
        $keyProductIds = implode('_', $productIds) . ':' .  ($store ? $store->getId() : '0') . ':' . $customerGroupId . ':' . (self::$isGetProductsByItems ? '1' : '0');
        
        if (isset($arrProducts[$keyProductIds])) {
            // Nothing.
        } else {
            $products = null;

            // Need for generate correct url and get right data.
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
                
            if ($store) {
                $products
                    ->setStoreId($store)
                    ->addStoreFilter($store);
            }
            
            // if (!empty($productIds)) {
                // Already exist automatic definition 'one value' or 'array'.
                $products->addIdFilter($productIds);
            // }

            $products->load();

            $arrProducts[$keyProductIds] = $products;
        }

        $resultProducts = $arrProducts[$keyProductIds];

        if ($resultProducts && ($store || $customerGroupId != null)) {
            foreach ($resultProducts as $key => &$product) {
                if ($product) {
                    if ($store) {
                        $product->setWebsiteId($store->getWebsiteId());
                    }
                    if ($customerGroupId != null) {
                        $product->setCustomerGroupId($customerGroupId);
                    }
                }
            }
        }

        return $resultProducts;
    }

    // Main functions //
    public static function generateProductsXML($productIds = null, $store = null, $checkData = true)
    {
        $ret = '';

        $products = self::getProducts($productIds, $store, null);

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
        return self::generateFacetXMLFromCustom('Category', 0, 'category_ids', 'select');
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