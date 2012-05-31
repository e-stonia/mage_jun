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
 * @package     Mage_Sales
 * @copyright   Copyright (c) 2010 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Sales order history block
 *
 * @category   Mage
 * @package    Mage_Sales
 * @author      Magento Core Team <core@magentocommerce.com>
 */

class Mage_Sales_Block_Order_Lastitems extends Mage_Core_Block_Template
{

    public function __construct()
    {
        parent::__construct();
        //$this->setTemplate('sales/order/items.phtml');
        
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $orderTable = Mage::getSingleton('core/resource')->getTableName('sales/order');
        $products = Mage::getResourceModel('sales/order_item_collection');
        $products->join('order', 'order_id=entity_id');
        $products->setPageSize(10); 
        $products->addFilter('customer_id',Mage::getSingleton('customer/session')->getCustomerId());
        $this->products = $collection =  $products->getItems();
       
        
        
        
        
        
        return $this;
    }

    
}
