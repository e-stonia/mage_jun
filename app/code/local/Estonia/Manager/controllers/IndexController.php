<?php
class Estonia_Manager_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
    	
    	/*
    	 * Load an object by id 
    	 * Request looking like:
    	 * http://site.com/manager?id=15 
    	 *  or
    	 * http://site.com/manager/id/15 	
    	 */
    	/* 
		$manager_id = $this->getRequest()->getParam('id');

  		if($manager_id != null && $manager_id != '')	{
			$manager = Mage::getModel('manager/manager')->load($manager_id)->getData();
		} else {
			$manager = null;
		}	
		*/
		
		 /*
    	 * If no param we load a the last created item
    	 */ 
    	/*
    	if($manager == null) {
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$managerTable = $resource->getTableName('manager');
			
			$select = $read->select()
			   ->from($managerTable,array('manager_id','title','content','status'))
			   ->where('status',1)
			   ->order('created_time DESC') ;
			   
			$manager = $read->fetchRow($select);
		}
		Mage::register('manager', $manager);
		*/

			
		$this->loadLayout();     
		$this->renderLayout();
    }
}