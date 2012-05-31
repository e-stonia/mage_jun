<?php

class Estonia_Manager_Block_Adminhtml_Manager_Edit_Tab_Form extends Mage_Adminhtml_Block_Widget_Form
{
  protected function _prepareForm()
  {
      $form = new Varien_Data_Form();
      $this->setForm($form);
      $fieldset = $form->addFieldset('manager_form', array('legend'=>Mage::helper('manager')->__('Manager information')));
     
      $fieldset->addField('name', 'text', array(
          'label'     => Mage::helper('manager')->__('Name'),
          'class'     => 'required-entry',
          'required'  => true,
          'name'      => 'name',
      ));

      $fieldset->addField('code', 'text', array(
          'label'     => Mage::helper('manager')->__('Agendikood'),
          'required'  => false,
          'name'      => 'code',
	  ));
      
      $fieldset->addField('email', 'text', array(
          'label'     => Mage::helper('manager')->__('email'),
          'required'  => false,
          'name'      => 'email',
	  ));
	  $fieldset->addField('tel', 'text', array(
          'label'     => Mage::helper('manager')->__('Phone'),
          'required'  => false,
          'name'      => 'tel',
	  ));
		
      $fieldset->addField('status', 'select', array(
          'label'     => Mage::helper('manager')->__('Status'),
          'name'      => 'status',
          'values'    => array(
              array(
                  'value'     => 1,
                  'label'     => Mage::helper('manager')->__('Enabled'),
              ),

              array(
                  'value'     => 2,
                  'label'     => Mage::helper('manager')->__('Disabled'),
              ),
          ),
      ));
     
//      $fieldset->addField('content', 'editor', array(
//          'name'      => 'content',
//          'label'     => Mage::helper('manager')->__('Content'),
//          'title'     => Mage::helper('manager')->__('Content'),
//          'style'     => 'width:700px; height:500px;',
//          'wysiwyg'   => false,
//          'required'  => true,
//      ));
     
      if ( Mage::getSingleton('adminhtml/session')->getManagerData() )
      {
          $form->setValues(Mage::getSingleton('adminhtml/session')->getManagerData());
          Mage::getSingleton('adminhtml/session')->setManagerData(null);
      } elseif ( Mage::registry('manager_data') ) {
          $form->setValues(Mage::registry('manager_data')->getData());
      }
      return parent::_prepareForm();
  }
}