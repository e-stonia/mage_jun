<?php

class Estonia_Manager_Model_Mysql4_Manager_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('manager/manager');
    }
}