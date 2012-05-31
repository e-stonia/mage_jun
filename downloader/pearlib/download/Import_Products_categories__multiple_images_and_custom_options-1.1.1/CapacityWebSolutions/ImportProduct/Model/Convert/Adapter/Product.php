<?php
/**
 * Import Multiple Images during Product Import
 * Copyright © 2010 Web Design by Capacity Web Solutions Pvt. Ltd. All Rights Reserved.
 * http://www.capacitywebsolutions.com
 */
 
class CapacityWebSolutions_ImportProduct_Model_Convert_Adapter_Product extends Mage_Catalog_Model_Convert_Adapter_Product
{
     /**
     * Save product (import)
     *
     * @param array $importData
     * @throws Mage_Core_Exception 
     * @return bool
     */
	  protected $custom_options = array();
	  
	  
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
 		/*	if category name is in csv file		*/
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
		 /* Add the custom options specified in the CSV import file 	*/
		
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
	
	protected $_categoryCache = array();
	/*	Add category and sub category.	 */
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