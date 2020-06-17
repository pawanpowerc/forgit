<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$file = fopen('var/import/import.csv', 'r', '"'); // set path to the CSV file
if ($file !== false) {

    require __DIR__ . '/app/bootstrap.php';
    $bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);

    $objectManager = $bootstrap->getObjectManager();
    $objectManager1 = Magento\Framework\App\ObjectManager::getInstance();
    $directoryList = $objectManager1->get('\Magento\Framework\App\Filesystem\DirectoryList');
    $path = $directoryList->getPath('media');
    $state = $objectManager->get('Magento\Framework\App\State');
    $state->setAreaCode('adminhtml');

    // used for updating product stock - and it's important that it's inside the while loop
    $stockRegistry = $objectManager->get('Magento\CatalogInventory\Api\StockRegistryInterface');

    // add logging capability
    $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/import-new.log');
    $logger = new \Zend\Log\Logger();
    $logger->addWriter($writer);

    $header = fgetcsv($file); // get data headers and skip 1st row

    // enter the min number of data fields you require that the new product will have (only if you want to standardize the import)
    $required_data_fields = 3;
    $superAttribute ="";

    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
    $connection = $resource->getConnection();
    while (($row = fgetcsv($file, 0, ",")) !== FALSE) {

        $data_count = count($row);
        if ($data_count < 1) {
            continue;
        }
        // used for setting the new product data
        $product = $objectManager->create('Magento\Catalog\Model\Product');         
        $data = array();
        $data = array_combine($header, $row);
         //echo "<pre>";
        // print_r($data);
        // die('--end--');
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $eavConfig = $objectManager->create('\Magento\Eav\Model\Config');
        // GENDER........
        $attribute = $eavConfig->getAttribute('catalog_product','gender');
        $options = $attribute->getSource()->getAllOptions(false);
       // $attributeValue = [];
        foreach($options as $option) {
           // $attributeValue[$option['label']] = $option['value'];   
                    if($option['label'] == $data['gender']){
                        $optiongender = $option['value']; 
                //echo '<pre>'; print_r($option['label']);
            }
        }
        // COLOR........
        $attribute1 = $eavConfig->getAttribute('catalog_product','color');
        $options1 = $attribute1->getSource()->getAllOptions(false);
       // $attributeValue = [];
        foreach($options1 as $option) {
           // $attributeValue[$option['label']] = $option['value'];   
                    if($option['label'] == $data['color']){
                        $optionColor = $option['value']; 
               // echo '<pre>'; print_r($option['label']);
            }
        }
        // Size........
        $attribute2 = $eavConfig->getAttribute('catalog_product', 'size');
        $options2 = $attribute2->getSource()->getAllOptions(false);
       // $attributeValue = [];
        foreach($options2 as $option) {
           // $attributeValue[$option['label']] = $option['value'];   
                    if($option['label'] == $data['size']){
                        $optionSize = $option['value']; 
               // echo '<pre>'; print_r($option['label']);
            }
        }

        $sku = $data['sku'];
        if ($data_count < $required_data_fields) {
            $logger->info("Skipping product sku " . $sku . ", not all required fields are present to create the product.");
            continue;
        }
        $gender = $optiongender;
        $color = $optionColor;
        $colordescription=$data['color description'];
        $size = $optionSize;
        $name = $data['name'];
        $description = $data['description'];
        $shortDescription = $data['short description'];
        $collezione=$data['collezione'];
        $breech_rise=$data['breech rise'];
        $shirt_sleeve=$data['shirt sleeve'];
        $breech_type=$data['breech type'];
        $jacket_type=$data['jacket type'];

        //$categories = $data['category'];
        if(isset($data['qty'])){
        $qty = trim($data['qty']);
        }else{
            $qty = 10;
        }
        $price = trim($data['price']);

        $img_url  = trim($data['Image_1st']);
        $img_url1 = trim($data['Image_2nd']);
        $img_url2 = trim($data['Image_3rd']);
       // $img_url3 = trim($data['Image_4th']);
    
              //  $lastWord = substr($img_url, strrpos($img_url, '/') + 1);
        
               // copy($img_url, 'pub/media/product/');
                $dir = $directoryList->getPath('media').'/import/';
                $imgpath = $dir.$img_url;
                //echo $imgpath; exit;
                $imgpath1 = $dir.$img_url1;
                
                $imgpath2 = $dir.$img_url2;
               // $imgpath3 = $dir.$img_url3;
			   
                if($img_url){
					if (file_exists($dir.$img_url)) {
					$product->addImageToMediaGallery($imgpath, array('thumbnail','small_image','image'), false, false);
					}
				}
                 
                if($img_url1){
					if (file_exists($dir.$img_url1)) {
						 
					$product->addImageToMediaGallery($imgpath1, array('image'), false, false);
					
					}
				}
                
				if($img_url2){
					if (file_exists($dir.$img_url2)) {
					 
					$product->addImageToMediaGallery($imgpath2, array('additional_image'), false, false);
					
					}
				}				
                $mixedCategories = explode(',',$data['categories']);
                $categoryCollect = array();
                    foreach($mixedCategories as $mixcat){
                    $finalcat = explode('/',$mixcat);
                    $counter = count($finalcat);
                        $childCatId = $counter - 1;
                        $finalCatId = $counter - 2;

                         $categoryTitle = $finalcat[$childCatId];

                         $tableName = $resource->getTableName('catalog_product_relation');
                 $sql1 = "SELECT u.parent_id,u.entity_id FROM catalog_category_entity u JOIN catalog_category_entity_varchar b ON u.entity_id = b.entity_id WHERE b.attribute_id = '45' and b.value = '".$categoryTitle."' ";

                   $result1 = $connection->fetchAll($sql1); 

                   foreach($result1 as $pids){
                    $parentId = $pids['parent_id'];
                    $catId = $pids['entity_id'];
                    $subCategory = $objectManager->create('Magento\Catalog\Model\Category')->load($parentId);
                    $parentCatName = $subCategory->getData('name');
                        if(strtolower($parentCatName) == strtolower($finalcat[$finalCatId])){
                                $categoryCollect[] = $catId;
                        }
                   }

                    }
        $simpleProductId ='';      
        $simpleProductName = $name;
        $url_key=$name.'_'.$sku;
        try {
            $product->setTypeId('simple') // type of product you're importing
                    ->setStatus(1) // 1 = enabled
                    ->setAttributeSetId(4) // In Magento 2.2 attribute set id 4 is the Default attribute set (this may vary in other versions)
                    ->setName($simpleProductName)
                    ->setCollezione($collezione)
                    ->setColorDescription($colordescription)
                    ->setJacketType($jacket_type)
                    ->setBreechType($breech_type)
                    ->setBreechRise($breech_rise)
                    ->setShirtSleeve($shirt_sleeve)
                    ->setGender($gender)
                    ->setSku($sku)
                    ->setPrice($price)
                    ->setTaxClassId(0) // 0 = None
                    ->setWeight(1)
                    ->setColor($color) 
                    ->setSize($size) 
                    //->setBrand($brand) 
                    ->setCategoryIds($categoryCollect) // array of category IDs, 2 = Default Category
                    ->setDescription($description)
                    ->setShortDescription($shortDescription)
                    //->setProductDetails($productDetails)
                    //->setCategories($categories)
                    ->setUrlKey($url_key) // you don't need to set it, because Magento does this by default, but you can if you need to
                    ->setWebsiteIds(array(1)) // Default Website ID
                    ->setStoreId(0) // Default store ID
                    ->setVisibility(1)          // 1 = not visible individually
                    ->save();

        } catch (\Exception $e) {
            $logger->info('Error importing product sku: '.$sku.'. '.$e->getMessage());
            continue;
        }

        try {
            $stockItem = $stockRegistry->getStockItemBySku($sku);

            if ($stockItem->getQty() != $qty) {
                $stockItem->setQty($qty);
                if ($qty > 0) {
                    $stockItem->setIsInStock(1);
                }
                $stockRegistry->updateStockItemBySku($sku, $stockItem);
            }
        } catch (\Exception $e) {
            $logger->info('Error importing stock for product sku: '.$sku.'. '.$e->getMessage());
            continue;
        }
        
        $superAttribute = $data['name'];
        $configablepro = $data['name'];
        
        $simpleProductId = $product->getId();
        $checkProduct = $objectManager->create('Magento\Catalog\Model\Product');
        $productObj = $checkProduct->getIdBySku($superAttribute);
        $tableName = $resource->getTableName('catalog_product_relation');

        if($productObj){
                   $sql = "SELECT child_id FROM " . $tableName . " WHERE parent_id = " . $productObj;
                   $result = $connection->fetchAll($sql); 
                  // echo '<pre>'; print_r($result); echo '</pre>';  
                   //$assocProducts = array($result, $simpleProductId);
                  // $data = $assocProducts;    
                   foreach($result as $childId){
                        $assocIds[] = $childId['child_id'];
                    }
                  
                 $assocIds[] = $simpleProductId;

                 $configProduct = $objectManager->create('Magento\Catalog\Model\Product')->load($productObj); // Load Configurable Product
                 $configProduct->setAssociatedProductIds(array_unique($assocIds)); // Setting Associated Products
                 $configProduct->setCanSaveConfigurableAttributes(true);
                 $configProduct->save();
            //echo "hhhhhhh";
        }else{
        
        $configName = $configablepro;
        $configurable_product = $objectManager->create('\Magento\Catalog\Model\Product');

        $configurable_product->setSku($superAttribute); // set sku
        $configurable_product->setName($configName); // set name
        //$configurable_product->setUrlPath($configUrl);    
        $configurable_product->setAttributeSetId(4);
        $configurable_product->setStatus(1);
        $configurable_product->setTypeId('configurable');
        $configurable_product->setPrice(0);
        $configurable_product->setDescription($data['description']);
        $configurable_product->setShortDescription($data['short description']);
        //$configurable_product->setProductDetails($data['product_details']);
        $configurable_product->setWebsiteIds(array(1)); // set website
        $configurable_product->setCategoryIds($categoryCollect); // set category
        $configurable_product->setStockData(array(
            'use_config_manage_stock' => 0, //'Use config settings' checkbox
            'manage_stock' => 1, //manage stock
            'is_in_stock' => 1, //Stock Availability
                )
        );

        $configimg_url  = trim($data['Image_1st']);
        $configimg_url1 = trim($data['Image_2nd']);
        $configimg_url2 = trim($data['Image_3rd']);
       // $img_url3 = trim($data['Image_4th']);
    
            //    $lastWord = substr($img_url, strrpos($img_url, '/') + 1);
        
            //    copy($img_url, 'pub/media/product/');
                $dir = $directoryList->getPath('media').'/import/';
                $configimgpath = $dir.$configimg_url;
                //echo $imgpath; exit;
                $configimgpath1 = $dir.$configimg_url1;
                
                $configimgpath2 = $dir.$configimg_url2;
               // $imgpath3 = $dir.$img_url3;
			   
			   if($configimg_url){
					if (file_exists($dir.$configimg_url)) {
					$configurable_product->addImageToMediaGallery($configimgpath, array('thumbnail','small_image','image'), false, false);
					}
			   }
                
				if($configimg_url1){
					if (file_exists($dir.$configimg_url1)) {
						 
					$configurable_product->addImageToMediaGallery($configimgpath1, array('image'), false, false);
					
					}
                }
				if($configimg_url2){
					if (file_exists($dir.$configimg_url2)) {
					 
					$configurable_product->addImageToMediaGallery($configimgpath2, array('additional_image'), false, false);
					
					}
				}				
        // super attribute 
        $size_attr_id = $configurable_product->getResource()->getAttribute('size')->getId();
        $color_attr_id = $configurable_product->getResource()->getAttribute('color')->getId();

        $configurable_product->getTypeInstance()->setUsedProductAttributeIds(array($color_attr_id, $size_attr_id), $configurable_product); //attribute ID of attribute 'size_general' in my store

        $configurableAttributesData = $configurable_product->getTypeInstance()->getConfigurableAttributesAsArray($configurable_product);
        $configurable_product->setCanSaveConfigurableAttributes(true);
        $configurable_product->setConfigurableAttributesData($configurableAttributesData);
        $configurableProductsData = array();
        $configurable_product->setConfigurableProductsData($configurableProductsData);
        try {
            $configurable_product->save();
        } catch (Exception $ex) {
            echo '<pre>';
            print_r($ex->getMessage());
            exit;
        }

        $productId = $configurable_product->getId();

        // assign simple product ids
        $assocIds = array($simpleProductId);
       // echo "<pre>"; print_r($assocIds);
        try{
        $configurable_product = $objectManager->create('Magento\Catalog\Model\Product')->load($productId); // Load Configurable Product
            $configurable_product->setAssociatedProductIds($assocIds); // Setting Associated Products
            $configurable_product->setCanSaveConfigurableAttributes(true);
            $configurable_product->save();
        } catch (Exception $e) {
            echo "<pre>";
            print_r($e->getMessage());
            exit;
        }
    
    }   
    unset($product);
    unset($simpleProductId);
    unset($categoryCollect);
   // $i++;
    }
    echo "We have imported products Successfully...";
    fclose($file);
}



