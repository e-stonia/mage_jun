<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Catalog
 * @copyright   Copyright (c) 2010 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Mage_Catalog_Model_Convert_Adapter_Product
    extends Mage_Eav_Model_Convert_Adapter_Entity
{
    const MULTI_DELIMITER   = ' , ';
    const ENTITY            = 'catalog_product_import';

    /**
     * Product model
     *
     * @var Mage_Catalog_Model_Product
     */
    protected $_productModel;

    /**
     * product types collection array
     *
     * @var array
     */
    protected $_productTypes;

    /**
     * Product Type Instances singletons
     *
     * @var array
     */
    protected $_productTypeInstances = array();

    /**
     * product attribute set collection array
     *
     * @var array
     */
    protected $_productAttributeSets;

    protected $_stores;

    protected $_attributes = array();

    protected $_configs = array();

    protected $_requiredFields = array();

    protected $_ignoreFields = array();

    protected $_imageFields = array();

    /**
     * Inventory Fields array
     *
     * @var array
     */
    protected $_inventoryFields             = array();

    /**
     * Inventory Fields by product Types
     *
     * @var array
     */
    protected $_inventoryFieldsProductTypes = array();

    protected $_toNumber = array();
    
    protected $custom_options = array();
    protected $_categoryCache = array();    

    /**
     * Load product collection Id(s)
     *
     */
    public function load()
    {
        $attrFilterArray = array();
        $attrFilterArray ['name']           = 'like';
        $attrFilterArray ['sku']            = 'startsWith';
        $attrFilterArray ['type']           = 'eq';
        $attrFilterArray ['attribute_set']  = 'eq';
        $attrFilterArray ['visibility']     = 'eq';
        $attrFilterArray ['status']         = 'eq';
        $attrFilterArray ['price']          = 'fromTo';
        $attrFilterArray ['qty']            = 'fromTo';
        $attrFilterArray ['store_id']       = 'eq';

        $attrToDb = array(
            'type'          => 'type_id',
            'attribute_set' => 'attribute_set_id'
        );

        $filters = $this->_parseVars();

        if ($qty = $this->getFieldValue($filters, 'qty')) {
            $qtyFrom = isset($qty['from']) ? $qty['from'] : 0;
            $qtyTo   = isset($qty['to']) ? $qty['to'] : 0;

            $qtyAttr = array();
            $qtyAttr['alias']       = 'qty';
            $qtyAttr['attribute']   = 'cataloginventory/stock_item';
            $qtyAttr['field']       = 'qty';
            $qtyAttr['bind']        = 'product_id=entity_id';
            $qtyAttr['cond']        = "{{table}}.qty between '{$qtyFrom}' AND '{$qtyTo}'";
            $qtyAttr['joinType']    = 'inner';

            $this->setJoinField($qtyAttr);
        }

        parent::setFilter($attrFilterArray, $attrToDb);

        if ($price = $this->getFieldValue($filters, 'price')) {
            $this->_filter[] = array(
                'attribute' => 'price',
                'from'      => $price['from'],
                'to'        => $price['to']
            );
            $this->setJoinAttr(array(
                'alias'     => 'price',
                'attribute' => 'catalog_product/price',
                'bind'      => 'entity_id',
                'joinType'  => 'LEFT'
            ));
        }

        return parent::load();
    }

    /**
     * Retrieve product model cache
     *
     * @return Mage_Catalog_Model_Product
     */
    public function getProductModel()
    {
        if (is_null($this->_productModel)) {
            $productModel = Mage::getModel('catalog/product');
            $this->_productModel = Mage::objects()->save($productModel);
        }
        return Mage::objects()->load($this->_productModel);
    }

    /**
     * Retrieve eav entity attribute model
     *
     * @param string $code
     * @return Mage_Eav_Model_Entity_Attribute
     */
    public function getAttribute($code)
    {
        if (!isset($this->_attributes[$code])) {
            $this->_attributes[$code] = $this->getProductModel()->getResource()->getAttribute($code);
        }
        if ($this->_attributes[$code] instanceof Mage_Catalog_Model_Resource_Eav_Attribute) {
            $applyTo = $this->_attributes[$code]->getApplyTo();
            if ($applyTo && !in_array($this->getProductModel()->getTypeId(), $applyTo)) {
                return false;
            }
        }
        return $this->_attributes[$code];
    }

    /**
     * Retrieve product type collection array
     *
     * @return array
     */
    public function getProductTypes()
    {
        if (is_null($this->_productTypes)) {
            $this->_productTypes = array();
            $options = Mage::getModel('catalog/product_type')
                ->getOptionArray();
            foreach ($options as $k => $v) {
                $this->_productTypes[$k] = $k;
            }
        }
        return $this->_productTypes;
    }

    /**
     * ReDefine Product Type Instance to Product
     *
     * @param Mage_Catalog_Model_Product $product
     * @return Mage_Catalog_Model_Convert_Adapter_Product
     */
    public function setProductTypeInstance(Mage_Catalog_Model_Product $product)
    {
        $type = $product->getTypeId();
        if (!isset($this->_productTypeInstances[$type])) {
            $this->_productTypeInstances[$type] = Mage::getSingleton('catalog/product_type')
                ->factory($product, true);
        }
        $product->setTypeInstance($this->_productTypeInstances[$type], true);
        return $this;
    }

    /**
     * Retrieve product attribute set collection array
     *
     * @return array
     */
    public function getProductAttributeSets()
    {
        if (is_null($this->_productAttributeSets)) {
            $this->_productAttributeSets = array();

            $entityTypeId = Mage::getModel('eav/entity')
                ->setType('catalog_product')
                ->getTypeId();
            $collection = Mage::getResourceModel('eav/entity_attribute_set_collection')
                ->setEntityTypeFilter($entityTypeId);
            foreach ($collection as $set) {
                $this->_productAttributeSets[$set->getAttributeSetName()] = $set->getId();
            }
        }
        return $this->_productAttributeSets;
    }

    /**
     *  Init stores
     */
    protected function _initStores ()
    {
        if (is_null($this->_stores)) {
            $this->_stores = Mage::app()->getStores(true, true);
            foreach ($this->_stores as $code => $store) {
                $this->_storesIdCode[$store->getId()] = $code;
            }
        }
    }

    /**
     * Retrieve store object by code
     *
     * @param string $store
     * @return Mage_Core_Model_Store
     */
    public function getStoreByCode($store)
    {
        $this->_initStores();
        /**
         * In single store mode all data should be saved as default
         */
        if (Mage::app()->isSingleStoreMode()) {
            return Mage::app()->getStore(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
        }

        if (isset($this->_stores[$store])) {
            return $this->_stores[$store];
        }
        return false;
    }

    /**
     * Retrieve store object by code
     *
     * @param string $store
     * @return Mage_Core_Model_Store
     */
    public function getStoreById($id)
    {
        $this->_initStores();
        /**
         * In single store mode all data should be saved as default
         */
        if (Mage::app()->isSingleStoreMode()) {
            return Mage::app()->getStore(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
        }

        if (isset($this->_storesIdCode[$id])) {
            return $this->getStoreByCode($this->_storesIdCode[$id]);
        }
        return false;
    }

    public function parse()
    {
        $batchModel = Mage::getSingleton('dataflow/batch');
        /* @var $batchModel Mage_Dataflow_Model_Batch */

        $batchImportModel = $batchModel->getBatchImportModel();
        $importIds = $batchImportModel->getIdCollection();

        foreach ($importIds as $importId) {
            //print '<pre>'.memory_get_usage().'</pre>';
            $batchImportModel->load($importId);
            $importData = $batchImportModel->getBatchData();

            $this->saveRow($importData);
        }
    }

    protected $_productId = '';

    /**
     * Initialize convert adapter model for products collection
     *
     */
    public function __construct()
    {
        $fieldset = Mage::getConfig()->getFieldset('catalog_product_dataflow', 'admin');
        foreach ($fieldset as $code => $node) {
            /* @var $node Mage_Core_Model_Config_Element */
            if ($node->is('inventory')) {
                foreach ($node->product_type->children() as $productType) {
                    $productType = $productType->getName();
                    $this->_inventoryFieldsProductTypes[$productType][] = $code;
                    if ($node->is('use_config')) {
                        $this->_inventoryFieldsProductTypes[$productType][] = 'use_config_' . $code;
                    }
                }

                $this->_inventoryFields[] = $code;
                if ($node->is('use_config')) {
                    $this->_inventoryFields[] = 'use_config_'.$code;
                }
            }
            if ($node->is('required')) {
                $this->_requiredFields[] = $code;
            }
            if ($node->is('ignore')) {
                $this->_ignoreFields[] = $code;
            }
            if ($node->is('img')) {
                $this->_imageFields[] = $code;
            }
            if ($node->is('to_number')) {
                $this->_toNumber[] = $code;
            }
        }

        $this->setVar('entity_type', 'catalog/product');
        if (!Mage::registry('Object_Cache_Product')) {
            $this->setProduct(Mage::getModel('catalog/product'));
        }

        if (!Mage::registry('Object_Cache_StockItem')) {
            $this->setStockItem(Mage::getModel('cataloginventory/stock_item'));
        }
    }

    /**
     * Retrieve not loaded collection
     *
     * @param string $entityType
     * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection
     */
    protected function _getCollectionForLoad($entityType)
    {
        $collection = parent::_getCollectionForLoad($entityType)
            ->setStoreId($this->getStoreId())
            ->addStoreFilter($this->getStoreId());
        return $collection;
    }

    public function setProduct(Mage_Catalog_Model_Product $object)
    {
        $id = Mage::objects()->save($object);
        //$this->_product = $object;
        Mage::register('Object_Cache_Product', $id);
    }

    public function getProduct()
    {
        return Mage::objects()->load(Mage::registry('Object_Cache_Product'));
    }

    public function setStockItem(Mage_CatalogInventory_Model_Stock_Item $object)
    {
        $id = Mage::objects()->save($object);
        //$this->_product = $object;
        Mage::register('Object_Cache_StockItem', $id);

        //$this->_stockItem = $object;
    }

    public function getStockItem()
    {
        return Mage::objects()->load(Mage::registry('Object_Cache_StockItem'));
        //return $this->_stockItem;
    }

    public function save()
    {
        $stores = array();
        foreach (Mage::getConfig()->getNode('stores')->children() as $storeNode) {
            $stores[(int)$storeNode->system->store->id] = $storeNode->getName();
        }

        $collections = $this->getData();
        if ($collections instanceof Mage_Catalog_Model_Entity_Product_Collection) {
            $collections = array($collections->getEntity()->getStoreId()=>$collections);
        } elseif (!is_array($collections)) {
            $this->addException(Mage::helper('catalog')->__('No product collections found.'), Mage_Dataflow_Model_Convert_Exception::FATAL);
        }

        //$stockItems = $this->getInventoryItems();
        $stockItems = Mage::registry('current_imported_inventory');
        if ($collections) foreach ($collections as $storeId=>$collection) {
            $this->addException(Mage::helper('catalog')->__('Records for "'.$stores[$storeId].'" store found.'));

            if (!$collection instanceof Mage_Catalog_Model_Entity_Product_Collection) {
                $this->addException(Mage::helper('catalog')->__('Product collection expected.'), Mage_Dataflow_Model_Convert_Exception::FATAL);
            }
            try {
                $i = 0;
                foreach ($collection->getIterator() as $model) {
                    $new = false;
                    // if product is new, create default values first
                    if (!$model->getId()) {
                        $new = true;
                        $model->save();

                        // if new product and then store is not default
                        // we duplicate product as default product with store_id -
                        if (0 !== $storeId ) {
                            $data = $model->getData();
                            $default = Mage::getModel('catalog/product');
                            $default->setData($data);
                            $default->setStoreId(0);
                            $default->save();
                            unset($default);
                        } // end

                        #Mage::getResourceSingleton('catalog_entity/convert')->addProductToStore($model->getId(), 0);
                    }
                    if (!$new || 0!==$storeId) {
                        if (0!==$storeId) {
                            Mage::getResourceSingleton('catalog_entity/convert')->addProductToStore($model->getId(), $storeId);
                        }
                        $model->save();
                    }

                    if (isset($stockItems[$model->getSku()]) && $stock = $stockItems[$model->getSku()]) {
                        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($model->getId());
                        $stockItemId = $stockItem->getId();

                        if (!$stockItemId) {
                            $stockItem->setData('product_id', $model->getId());
                            $stockItem->setData('stock_id', 1);
                            $data = array();
                        } else {
                            $data = $stockItem->getData();
                        }

                        foreach($stock as $field => $value) {
                            if (!$stockItemId) {
                                if (in_array($field, $this->_configs)) {
                                    $stockItem->setData('use_config_'.$field, 0);
                                }
                                $stockItem->setData($field, $value?$value:0);
                            } else {

                                if (in_array($field, $this->_configs)) {
                                    if ($data['use_config_'.$field] == 0) {
                                        $stockItem->setData($field, $value?$value:0);
                                    }
                                } else {
                                    $stockItem->setData($field, $value?$value:0);
                                }
                            }
                        }
                        $stockItem->save();
                        unset($data);
                        unset($stockItem);
                        unset($stockItemId);
                    }
                    unset($model);
                    $i++;
                }
                $this->addException(Mage::helper('catalog')->__("Saved %d record(s)", $i));
            } catch (Exception $e) {
                if (!$e instanceof Mage_Dataflow_Model_Convert_Exception) {
                    $this->addException(Mage::helper('catalog')->__('An error occurred while saving the collection, aborting. Error message: %s', $e->getMessage()),
                        Mage_Dataflow_Model_Convert_Exception::FATAL);
                }
            }
        }
        //unset(Zend::unregister('imported_stock_item'));
        unset($collections);
        return $this;
    }

    /**
     * Save product (import)
     *
     * @param array $importData
     * @throws Mage_Core_Exception
     * @return bool
     */
    public function saveRow_1(array $importData)
    {
        $product = $this->getProductModel()
            ->reset();

        if (empty($importData['store'])) {
            if (!is_null($this->getBatchParams('store'))) {
                $store = $this->getStoreById($this->getBatchParams('store'));
            } else {
                $message = Mage::helper('catalog')->__('Skipping import row, required field "%s" is not defined.', 'store');
                Mage::throwException($message);
            }
        }
        else {
            $store = $this->getStoreByCode($importData['store']);
        }

        if ($store === false) {
            $message = Mage::helper('catalog')->__('Skipping import row, store "%s" field does not exist.', $importData['store']);
            Mage::throwException($message);
        }

        if (empty($importData['sku'])) {
            $message = Mage::helper('catalog')->__('Skipping import row, required field "%s" is not defined.', 'sku');
            Mage::throwException($message);
        }
        $product->setStoreId($store->getId());
        $productId = $product->getIdBySku($importData['sku']);

        if ($productId) {
            $product->load($productId);
        }
        else {
            $productTypes = $this->getProductTypes();
            $productAttributeSets = $this->getProductAttributeSets();

            /**
             * Check product define type
             */
            if (empty($importData['type']) || !isset($productTypes[strtolower($importData['type'])])) {
                $value = isset($importData['type']) ? $importData['type'] : '';
                $message = Mage::helper('catalog')->__('Skip import row, is not valid value "%s" for field "%s"', $value, 'type');
                Mage::throwException($message);
            }
            $product->setTypeId($productTypes[strtolower($importData['type'])]);
            /**
             * Check product define attribute set
             */
            if (empty($importData['attribute_set']) || !isset($productAttributeSets[$importData['attribute_set']])) {
                $value = isset($importData['attribute_set']) ? $importData['attribute_set'] : '';
                $message = Mage::helper('catalog')->__('Skip import row, the value "%s" is invalid for field "%s"', $value, 'attribute_set');
                Mage::throwException($message);
            }
            $product->setAttributeSetId($productAttributeSets[$importData['attribute_set']]);

            foreach ($this->_requiredFields as $field) {
                $attribute = $this->getAttribute($field);
                if (!isset($importData[$field]) && $attribute && $attribute->getIsRequired()) {
                    $message = Mage::helper('catalog')->__('Skipping import row, required field "%s" for new products is not defined.', $field);
                    Mage::throwException($message);
                }
            }
        }

        $this->setProductTypeInstance($product);

        if (isset($importData['category_ids'])) {
            $product->setCategoryIds($importData['category_ids']);
        }

        foreach ($this->_ignoreFields as $field) {
            if (isset($importData[$field])) {
                unset($importData[$field]);
            }
        }

        if ($store->getId() != 0) {
            $websiteIds = $product->getWebsiteIds();
            if (!is_array($websiteIds)) {
                $websiteIds = array();
            }
            if (!in_array($store->getWebsiteId(), $websiteIds)) {
                $websiteIds[] = $store->getWebsiteId();
            }
            $product->setWebsiteIds($websiteIds);
        }

        if (isset($importData['websites'])) {
            $websiteIds = $product->getWebsiteIds();
            if (!is_array($websiteIds) || !$store->getId()) {
                $websiteIds = array();
            }
            $websiteCodes = explode(',', $importData['websites']);
            foreach ($websiteCodes as $websiteCode) {
                try {
                    $website = Mage::app()->getWebsite(trim($websiteCode));
                    if (!in_array($website->getId(), $websiteIds)) {
                        $websiteIds[] = $website->getId();
                    }
                }
                catch (Exception $e) {}
            }
            $product->setWebsiteIds($websiteIds);
            unset($websiteIds);
        }

        foreach ($importData as $field => $value) {
            if (in_array($field, $this->_inventoryFields)) {
                continue;
            }
            if (in_array($field, $this->_imageFields)) {
                continue;
            }
            if (is_null($value)) {
                continue;
            }

            $attribute = $this->getAttribute($field);
            if (!$attribute) {
                continue;
            }

            $isArray = false;
            $setValue = $value;

            if ($attribute->getFrontendInput() == 'multiselect') {
                $value = explode(self::MULTI_DELIMITER, $value);
                $isArray = true;
                $setValue = array();
            }

            if ($value && $attribute->getBackendType() == 'decimal') {
                $setValue = $this->getNumber($value);
            }

            if ($attribute->usesSource()) {
                $options = $attribute->getSource()->getAllOptions(false);

                if ($isArray) {
                    foreach ($options as $item) {
                        if (in_array($item['label'], $value)) {
                            $setValue[] = $item['value'];
                        }
                    }
                } else {
                    $setValue = false;
                    foreach ($options as $item) {
                        if ($item['label'] == $value) {
                            $setValue = $item['value'];
                        }
                    }
                }
            }

            $product->setData($field, $setValue);
        }

        if (!$product->getVisibility()) {
            $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE);
        }

        $stockData = array();
        $inventoryFields = isset($this->_inventoryFieldsProductTypes[$product->getTypeId()])
            ? $this->_inventoryFieldsProductTypes[$product->getTypeId()]
            : array();
        foreach ($inventoryFields as $field) {
            if (isset($importData[$field])) {
                if (in_array($field, $this->_toNumber)) {
                    $stockData[$field] = $this->getNumber($importData[$field]);
                }
                else {
                    $stockData[$field] = $importData[$field];
                }
            }
        }
        $product->setStockData($stockData);

        $imageData = array();
        foreach ($this->_imageFields as $field) {
            if (!empty($importData[$field]) && $importData[$field] != 'no_selection') {
                if (!isset($imageData[$importData[$field]])) {
                    $imageData[$importData[$field]] = array();
                }
                $imageData[$importData[$field]][] = $field;
            }
        }

        foreach ($imageData as $file => $fields) {
            try {
                $product->addImageToMediaGallery(Mage::getBaseDir('media') . DS . 'import' . trim($file), $fields);
            }
            catch (Exception $e) {}
        }

        $product->setIsMassupdate(true);
        $product->setExcludeUrlRewrite(true);

        $product->save();

        return true;
    }

    /**
     * Silently save product (import)
     *
     * @param array $
     * @return bool
     */
    public function saveRowSilently(array $importData)
    {
        try {
            $result = $this->saveRow($importData);
            return $result;
        }
        catch (Exception $e) {
            return false;
        }
    }

    /**
     * Process after import data
     * Init indexing process after catalog product import
     *
     */
    public function finish()
    {
        /**
         * Back compatibility event
         */
        Mage::dispatchEvent('catalog_product_import_after', array());

        $entity = new Varien_Object();
        Mage::getSingleton('index/indexer')->processEntityAction(
            $entity, self::ENTITY, Mage_Index_Model_Event::TYPE_SAVE
        );
    }
    
    
    
    
    
    /**
     * Save product (import)
     *
     * @param array $importData
     * @throws Mage_Core_Exception 
     * @return bool
     */
        
      
    public function saveRow(array $importData)
    {
        

    
        
        $product = $this->getProductModel()
            ->reset();

        if (empty($importData['store'])) {
            if (!is_null($this->getBatchParams('store'))) {
                $store = $this->getStoreById($this->getBatchParams('store'));
            } else {
                $message = Mage::helper('catalog')->__('Skip import row, required field "%s" not defined', 'store');
                Mage::throwException($message);
            }
        }
        else {
             $store = $this->getStoreByCode($importData['store']);
        }
 
        if ($store === false) {
            $message = Mage::helper('catalog')->__('Skip import row, store "%s" field not exists', $importData['store']);
            Mage::throwException($message);
        }
 
        if (empty($importData['sku'])) {
            $message = Mage::helper('catalog')->__('Skip import row, required field "%s" not defined', 'sku');
            Mage::throwException($message);
        }
        $product->setStoreId($store->getId());
        $productId = $product->getIdBySku($importData['sku']);
 
        if ($productId) {
            $product->load($productId);
        }
        else {
            $productTypes = $this->getProductTypes();
            $productAttributeSets = $this->getProductAttributeSets();
 
            /**
             * Check product define type
             */
            if (empty($importData['type']) || !isset($productTypes[strtolower($importData['type'])])) {
                $value = isset($importData['type']) ? $importData['type'] : '';
                $message = Mage::helper('catalog')->__('Skip import row, is not valid value "%s" for field "%s"', $value, 'type');
                Mage::throwException($message);
            }
            $product->setTypeId($productTypes[strtolower($importData['type'])]);
            /**
             * Check product define attribute set
             */
            if (empty($importData['attribute_set']) || !isset($productAttributeSets[$importData['attribute_set']])) {
                /*$value = isset($importData['attribute_set']) ? $importData['attribute_set'] : '';
                $message = Mage::helper('catalog')->__('Skip import row, is not valid value "%s" for field "%s"', $value, 'attribute_set');
                Mage::throwException($message);*/
            }
            $product->setAttributeSetId($productAttributeSets[$importData['attribute_set']]);
 
            foreach ($this->_requiredFields as $field) {
                $attribute = $this->getAttribute($field);
                if (!isset($importData[$field]) && $attribute && $attribute->getIsRequired()) {
                    $message = Mage::helper('catalog')->__('Skip import row, required field "%s" for new products not defined', $field);
                    Mage::throwException($message);
                }
            }
        }
 
        $this->setProductTypeInstance($product);
 
        if (isset($importData['category_ids'])) {
            $product->setCategoryIds($importData['category_ids']);
        }
         /*    if category name is in csv file        */
        if (isset($importData['categories'])) {
           
            $categoryIds = $this->_addCategories($importData['categories'], $store);
            if ($categoryIds) {
                $product->setCategoryIds($categoryIds);
            }
        }
        foreach ($this->_ignoreFields as $field) {
            if (isset($importData[$field])) {
                unset($importData[$field]);
            }
        }
 
        if ($store->getId() != 0) {
            $websiteIds = $product->getWebsiteIds();
            if (!is_array($websiteIds)) {
                $websiteIds = array();
            }
            if (!in_array($store->getWebsiteId(), $websiteIds)) {
                $websiteIds[] = $store->getWebsiteId();
            }
            $product->setWebsiteIds($websiteIds);
        }
 
        if (isset($importData['websites'])) {
            $websiteIds = $product->getWebsiteIds();
            if (!is_array($websiteIds)) {
                $websiteIds = array();
            }
            $websiteCodes = explode(',', $importData['websites']);
            foreach ($websiteCodes as $websiteCode) {
                try {
                    $website = Mage::app()->getWebsite(trim($websiteCode));
                    if (!in_array($website->getId(), $websiteIds)) {
                        $websiteIds[] = $website->getId();
                    }
                }
                catch (Exception $e) {}
            }
            $product->setWebsiteIds($websiteIds);
            unset($websiteIds);
        }
 
        foreach ($importData as $field => $value) {
            if (in_array($field, $this->_inventoryFields)) {
                continue;
            }
            if (in_array($field, $this->_imageFields)) {
                continue;
            }
            $attribute = $this->getAttribute($field);
              if (!$attribute) {
            
                if(strpos($field,':')!==FALSE && strlen($value)) {
                   $values=explode('|',$value);
                   if(count($values)>0) {
                      @list($title,$type,$is_required,$sort_order) = explode(':',$field);
                      $title = ucfirst(str_replace('_',' ',$title));
                      $custom_options[] = array(
                         'is_delete'=>0,
                         'title'=>$title,
                         'previous_group'=>'',
                         'previous_type'=>'',
                         'type'=>$type,
                         'is_require'=>$is_required,
                         'sort_order'=>$sort_order,
                         'values'=>array()
                      );
                      foreach($values as $v) {
                         $parts = explode(':',$v);
                         $title = $parts[0];
                         if(count($parts)>1) {
                            $price_type = $parts[1];
                         } else {
                            $price_type = 'fixed';
                         }
                         if(count($parts)>2) {
                            $price = $parts[2];
                         } else {
                            $price =0;
                         }
                         if(count($parts)>3) {
                            $sku = $parts[3];
                         } else {
                            $sku='';
                         }
                         if(count($parts)>4) {
                            $sort_order = $parts[4];
                         } else {
                            $sort_order = 0;
                         }
                         switch($type) {
                            case 'file':
                               /* TODO */
                               break;
                               
                            case 'field':
                            case 'area':
                               $custom_options[count($custom_options) - 1]['max_characters'] = $sort_order;
                               /* NO BREAK */
                               
                            case 'date':
                            case 'date_time':
                            case 'time':
                               $custom_options[count($custom_options) - 1]['price_type'] = $price_type;
                               $custom_options[count($custom_options) - 1]['price'] = $price;
                               $custom_options[count($custom_options) - 1]['sku'] = $sku;
                               break;
                                                          
                            case 'drop_down':
                            case 'radio':
                            case 'checkbox':
                            case 'multiple':
                            default:
                               $custom_options[count($custom_options) - 1]['values'][]=array(
                                  'is_delete'=>0,
                                  'title'=>$title,
                                  'option_type_id'=>-1,
                                  'price_type'=>$price_type,
                                  'price'=>$price,
                                  'sku'=>$sku,
                                  'sort_order'=>$sort_order,
                               );
                               break;
                         }
                      }
                   }
                }

                continue;
            }
 
            $isArray = false;
            $setValue = $value;
 
            if ($attribute->getFrontendInput() == 'multiselect') {
                $value = explode(self::MULTI_DELIMITER, $value);
                $isArray = true;
                $setValue = array();
            }
 
            if ($value && $attribute->getBackendType() == 'decimal') {
                $setValue = $this->getNumber($value);
            }
             
        
            if ($attribute->usesSource()) {
                $options = $attribute->getSource()->getAllOptions(false);
 
                if ($isArray) {
                    foreach ($options as $item) {
                        if (in_array($item['label'], $value)) {
                            $setValue[] = $item['value'];
                        }
                    }
                }
                else {
                    $setValue = null;
                    foreach ($options as $item) {
                        if ($item['label'] == $value) {
                            $setValue = $item['value'];
                        }
                    }
                }
            }
 
            $product->setData($field, $setValue);
        }
 
        if (!$product->getVisibility()) {
            $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE);
        }
 
        $stockData = array();
        $inventoryFields = isset($this->_inventoryFieldsProductTypes[$product->getTypeId()])
            ? $this->_inventoryFieldsProductTypes[$product->getTypeId()]
            : array();
        foreach ($inventoryFields as $field) {
            if (isset($importData[$field])) {
                if (in_array($field, $this->_toNumber)) {
                    $stockData[$field] = $this->getNumber($importData[$field]);
                }
                else {
                    $stockData[$field] = $importData[$field];
                }
            }
        }
        $product->setStockData($stockData);
 
        $imageData = array();
        foreach ($this->_imageFields as $field) {
            if (!empty($importData[$field]) && $importData[$field] != 'no_selection') {
                if (!isset($imageData[$importData[$field]])) {
                    $imageData[$importData[$field]] = array();
                }
                $imageData[$importData[$field]][] = $field;
            }
        }
 
        foreach ($imageData as $file => $fields) {
            try {
                $product->addImageToMediaGallery(Mage::getBaseDir('media') . DS . 'import' . $file, $fields);
            }
            catch (Exception $e) {}
        }
 
        /**
         * Allows you to import multiple images for each product.
         * Simply add a 'gallery' column to the import file, and separate
         * each image with a semi-colon.
         */
            try {
                    $galleryData = explode(';',$importData["gallery"]);
                    foreach($galleryData as $gallery_img)
                    /**
                     * @param directory where import image resides
                     * @param leave 'null' so that it isn't imported as thumbnail, base, or small
                     * @param false = the image is copied, not moved from the import directory to it's new location
                     * @param false = not excluded from the front end gallery
                     */
                    {
                            $product->addImageToMediaGallery(Mage::getBaseDir('media') . DS . 'import' . $gallery_img, null, false, false);
                    }
                }
            catch (Exception $e) {}        
        /* End Modification */
 
        $product->setIsMassupdate(true);
        $product->setExcludeUrlRewrite(true);
 
        $product->save();
         /* Add the custom options specified in the CSV import file     */
        
        if(isset($custom_options)){
        if(count($custom_options)) {
           foreach($custom_options as $option) {
              try {
                $opt = Mage::getModel('catalog/product_option');
                $opt->setProduct($product);
                $opt->addOption($option);
                $opt->saveOptions();
              }
              catch (Exception $e) {}
           }
        }
        }
        return true;
    }
    
    
    /*    Add category and sub category.     */
    protected function _addCategories($categories, $store)
    {
        
        
         $rootId = $store->getRootCategoryId();
        
        if (!$rootId) {
            /* If stoder not create that mense admin then assign 1 to storeId */
            $storeId = 1;
             $rootId = Mage::app()->getStore($storeId)->getRootCategoryId();
            
        }
        
        if($categories=="")
           return array();
        $rootPath = '1/'.$rootId;
        if (empty($this->_categoryCache[$store->getId()])) {
            $collection = Mage::getModel('catalog/category')->getCollection()
                ->setStore($store)
                ->addAttributeToSelect('name');
            $collection->getSelect()->where("path like '".$rootPath."/%'");

            foreach ($collection as $cat) {
                $pathArr = explode('/', $cat->getPath());
                $namePath = '';
                for ($i=2, $l=sizeof($pathArr); $i<$l; $i++) {
                    $name = $collection->getItemById($pathArr[$i])->getName();
                    $namePath .= (empty($namePath) ? '' : '/').trim($name);
                }
                $cat->setNamePath($namePath);
            }
            
            $cache = array();
            foreach ($collection as $cat) {
                $cache[strtolower($cat->getNamePath())] = $cat;
                $cat->unsNamePath();
            }
            $this->_categoryCache[$store->getId()] = $cache;
        }
        $cache =& $this->_categoryCache[$store->getId()];
      
        $catIds = array();
        foreach (explode(',', $categories) as $categoryPathStr) {
            $categoryPathStr = preg_replace('#\s*/\s*#', '/', trim($categoryPathStr));
            if (!empty($cache[$categoryPathStr])) {
                $catIds[] = $cache[$categoryPathStr]->getId();
                continue;
            }
            $path = $rootPath;
            $namePath = '';
            foreach (explode('/', $categoryPathStr) as $catName) {
                $namePath .= (empty($namePath) ? '' : '/').strtolower($catName);
                if (empty($cache[$namePath])) {
                    $cat = Mage::getModel('catalog/category')
                        ->setStoreId($store->getId())
                         ->setPath($path)
                        ->setName($catName)
                        ->setIsActive(1)
                        ->save();
                    $cache[$namePath] = $cat;
                }
                $catId = $cache[$namePath]->getId();
                $path .= '/'.$catId;
            }
            if ($catId) {
                $catIds[] = $catId;
            }
        }
        return join(',', $catIds);
    }    
    
    
}
