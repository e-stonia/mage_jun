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

class Mage_Sales_Block_Order_Historyitems extends Mage_Core_Block_Template
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
        $products->addFilter('customer_id',Mage::getSingleton('customer/session')->getCustomerId());
        $this->products = $collection =  $products->getItems();
       
        
        
        $prevYear = $currentYear = date("Y");
        $currentMonth = date("m");
        
        
        
        //$monthes = array(1,2,3,4,5,6,7,8,9,10,11,12,1,2,3,4,5,6,7,8,9,10,11,12);
        
        
        
        $data = array();
        
        foreach($collection as $product)
        {
          $product = $product->toArray();
            //dd($product['name']."-".$product['created_at']);
            //dd(date("Y-m-d",strtotime($product['created_at'])));
            
            $timestamp = strtotime($product['created_at']);
            
            
            
            
            
            //$data[$product['product_id']] = array('name'=>$product['name'],
//                'dates'=>
//                    
//            );
    
            if (@!$data[$product['product_id']])
            {
                $data[$product['product_id']] = array('name'=>$product['name'],'total'=>0.00,'qty'=>0,'dates'=>array());
            }
            //$data[$product['product_id']]['name'] = $product['name'];
            
           
             
             if(@!$data[$product['product_id']]['dates'][date('m.Y',$timestamp)])   
             {
                 $data[$product['product_id']]['dates'][date('m.Y',$timestamp)] = array('price'=>0.00,'qty'=>0);
             }
                
            @$data[$product['product_id']]['dates'][date('m.Y',$timestamp)]['price'] +=$product['row_total'];
            @$data[$product['product_id']]['dates'][date('m.Y',$timestamp)]['qty'] +=$product['qty_ordered'];
            @$data[$product['product_id']]['total'] +=$product['row_total'];
            @$data[$product['product_id']]['qty'] +=$product['qty_ordered'];
                        //$data[date('m/Y',$timestamp)][$product['product_id']] =  array ($product['name'],
//                        $product['row_total']);
            
        }
        
        

        
        
        $this->data = $data;
        
        return $this;
    }

    
}
