<?php 
class Lantera_Logincatalog_Model_Observer extends Mage_Core_Model_Abstract
{
    
    
    private function _proccessCategoryLogin()
    {
        if (! Mage::getSingleton('customer/session')->isLoggedIn()) {

            // redirect to login page
            static $sentry = false;
            
            if (! $sentry ) {
                //Mage::getSingleton('customer/session')->addNotice("Please login");
                $sentry = true;
            }
            /**
             * Thanks to kimpecov for this line! (http://www.magentocommerce.com/boards/viewthread/16743/)
             */
            Mage::getSingleton('customer/session')->setBeforeAuthUrl(Mage::app()->getRequest()->getRequestUri());
            
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("customer/account/login")); 
        }
    }
    
    function hookToControllerActionPreDispatch($observer){
         if($observer->getEvent()->getControllerAction()->getFullActionName() == 'catalog_category_view')
        {
            $this->_proccessCategoryLogin();
        }
    }
    
    function hookToControllerActionPostDispatch(){
        
        
    }
}

?>