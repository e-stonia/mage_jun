<?php

class Estonia_Manager_Block_Adminhtml_Manager_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{

  public function __construct()
  {
      parent::__construct();
      $this->setId('manager_tabs');
      $this->setDestElementId('edit_form');
      $this->setTitle(Mage::helper('manager')->__('Item Information'));
  }

  protected function _beforeToHtml()
  {
      $this->addTab('form_section', array(
          'label'     => Mage::helper('manager')->__('Item Information'),
          'title'     => Mage::helper('manager')->__('Item Information'),
          'content'   => $this->getLayout()->createBlock('manager/adminhtml_manager_edit_tab_form')->toHtml(),
      ));
     
      return parent::_beforeToHtml();
  }
}