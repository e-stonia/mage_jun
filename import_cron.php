<?php
//uncomment when moved to server - to ensure this page is not accessed from anywhere else

if ($_SERVER['REMOTE_ADDR'] !== '200.1.1.234') {
  die("You are not a cron job!");
}

function dd($MIXED){
	echo "<pre>";
		var_dump($MIXED);
	echo "</pre>";
	
}
function d ($MIXEd)
{
	dd($MIXEd);
	die("END");
}
require_once 'app/Mage.php';
// wget -O - http://<www.example.com>/Cron_Import.php/?files=3XSEEEE.csv
  umask(0);

  //$_SERVER['SERVER_PORT']='443';
  Mage::app();

  $profileId = 3; //put your profile id here
  $filename = Mage::app()->getRequest()->getParam('files'); // set the filename that is to be imported - file needs to be present in var/import directory
    
  if (!isset($filename))  {
 die("No file has been set!");
  }
  $logFileName= $filename.'.log';  
  $recordCount = 0;

  Mage::log("Import Started",null,$logFileName);  
 
  $profile = Mage::getModel('dataflow/profile');
  
  $userModel = Mage::getModel('admin/user');
  $userModel->setUserId(0);
  Mage::getSingleton('admin/session')->setUser($userModel);
  
  if ($profileId) {
    $profile->load($profileId);
    if (!$profile->getId()) {
      Mage::getSingleton('adminhtml/session')->addError('The profile you are trying to save no longer exists');
    }
  }

  Mage::register('current_convert_profile', $profile);

  
  $profile->run();
  
  $batchModel = Mage::getSingleton('dataflow/batch');
  if ($batchModel->getId()) {
    if ($batchModel->getAdapter()) {
      $batchId = $batchModel->getId(); 
      $batchImportModel = $batchModel->getBatchImportModel();
      $importIds = $batchImportModel->getIdCollection();  

      $batchModel = Mage::getModel('dataflow/batch')->load($batchId);      
      $adapter = Mage::getModel($batchModel->getAdapter());
      foreach ($importIds as $importId) {
      	
        $recordCount++;
        try{
          $batchImportModel->load($importId);
          if (!$batchImportModel->getId()) {
             $errors[] = Mage::helper('dataflow')->__('Skip undefined row');
             continue;
          }

          $importData = $batchImportModel->getBatchData();
          try {
            $adapter->saveRow($importData);
          } catch (Exception $e) {
            Mage::log($e->getMessage(),null,$logFileName);          
            continue;
          }
        
          if ($recordCount%20 == 0) {
            Mage::log($recordCount . ' - Completed!!',null,$logFileName);
          }
        } catch(Exception $ex) {
          Mage::log('Record# ' . $recordCount . ' - SKU = ' . $importData['sku']. ' - Error - ' . $ex->getMessage(),null,$logFileName);        
        }
      } 
      foreach ($profile->getExceptions() as $e) {
        Mage::log($e->getMessage(),null,$logFileName);          
      }
      
    }
  }
  echo 'Import Completed';
  $tstamp = date('Ymd').'_'.strtotime("now"); 
  Mage::log("Import Completed",null,$logFileName);
  
  if (rename('/var/www/var/import/hourly.csv', '/var/www/var/import/hourly'. $tstamp.'.csv'))  {Mage::log("File copied", null,$logFileName);} 
  else {Mage::log("File not copied",null,$logFileName);}





 ?> 
