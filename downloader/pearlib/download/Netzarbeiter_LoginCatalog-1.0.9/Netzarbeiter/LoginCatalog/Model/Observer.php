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
 * @category   Mage
 * @package    Netzarbeiter_LoginCatalog
 * @copyright  Copyright (c) 2008 Vinai Kopp http://netzarbeiter.com/
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Netzarbeiter_LoginCatalog_Model_Observer extends Mage_Core_Model_Abstract
{

	/**
	 * Redirects the customer to the login page
	 */
	protected function _redirectToLoginPage()
	{
		/**
		 * Hack: since 1.1.7 both events are fired, so we need to prevent the message to be added more then once
		 * 
		 * @var boolean $sentry
		 */
		static $sentry = false;
		
		if (! $sentry && ($message = Mage::helper('logincatalog')->getConfig('message'))) {
			Mage::getSingleton('customer/session')->addNotice($message);
			$sentry = true;
		}
		/**
		 * Thanks to kimpecov for this line! (http://www.magentocommerce.com/boards/viewthread/16743/)
		 */
		Mage::getSingleton('customer/session')->setBeforeAuthUrl(Mage::app()->getRequest()->getRequestUri());
		
		Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("customer/account/login")); 
	}

	/**
	 * Is fired on catalog_product_load_after event, i.e. when
	 * a customer views a product page.
	 * If the customer isn't logged in, redirect to account login page.
	 * 
	 * @param Varien_Event_Observer $observer
	 */
	public function loginCatalogProductLoadEvent($observer)
	{
		if (! Mage::helper('logincatalog')->moduleActive()) return;

		if (! Mage::getSingleton('customer/session')->isLoggedIn() && ! $this->_isApiRequest()) {

			// redirect to login page
			$this->_redirectToLoginPage();
		}
	}

	/**
	 * Is fired on catalog_product_collection_load_after event, i.e.
	 * when viewing a catalog page with products, or when viewing
	 * a search page.
	 * If the customer isn't logged in, redirect to account login page.
	 * 
	 * @param Varien_Event_Observer $observer
	 */
	public function loginCatalogProductCollectionLoadEvent($observer)
	{
		if (! Mage::helper('logincatalog')->moduleActive()) return;

		if (! Mage::getSingleton('customer/session')->isLoggedIn() && ! $this->_isApiRequest()) {
			// redirect to login page
			$this->_redirectToLoginPage();
		}
	}
	
	/**
	 * Return true if the reqest is made via the api
	 * 
	 * @return boolean
	 */
	protected function _isApiRequest()
	{
		return Mage::app()->getRequest()->getModuleName() === 'api';
	}
}

