<?php
class Estonia_Manager_Block_Manager extends Mage_Core_Block_Template
{
	public function _prepareLayout()
    {
		return parent::_prepareLayout();
    }
    
     public function getManager()     
     { 
        if (!$this->hasData('manager')) {
            $this->setData('manager', Mage::registry('manager'));
        }
        return $this->getData('manager');
        
    }
}