<?php

class Estonia_Manager_Block_Adminhtml_Manager_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
  public function __construct()
  {
      parent::__construct();
      $this->setId('managerGrid');
      $this->setDefaultSort('manager_id');
      $this->setDefaultDir('ASC');
      $this->setSaveParametersInSession(true);
  }

  protected function _prepareCollection()
  {
      $collection = Mage::getModel('manager/manager')->getCollection();
      $this->setCollection($collection);
      return parent::_prepareCollection();
  }

  protected function _prepareColumns()
  {
      $this->addColumn('manager_id', array(
          'header'    => Mage::helper('manager')->__('ID'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'manager_id',
      ));
      $this->addColumn('code', array(
          'header'    => Mage::helper('manager')->__('Agendikood'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'code',
      ));

      $this->addColumn('title', array(
          'header'    => Mage::helper('manager')->__('Name'),
          'align'     =>'left',
          'index'     => 'name',
      ));

	  /*
      $this->addColumn('content', array(
			'header'    => Mage::helper('manager')->__('Item Content'),
			'width'     => '150px',
			'index'     => 'content',
      ));
	  */

      $this->addColumn('status', array(
          'header'    => Mage::helper('manager')->__('Status'),
          'align'     => 'left',
          'width'     => '80px',
          'index'     => 'status',
          'type'      => 'options',
          'options'   => array(
              1 => 'Enabled',
              2 => 'Disabled',
          ),
      ));
	  
        $this->addColumn('action',
            array(
                'header'    =>  Mage::helper('manager')->__('Action'),
                'width'     => '100',
                'type'      => 'action',
                'getter'    => 'getId',
                'actions'   => array(
                    array(
                        'caption'   => Mage::helper('manager')->__('Edit'),
                        'url'       => array('base'=> '*/*/edit'),
                        'field'     => 'id'
                    )
                ),
                'filter'    => false,
                'sortable'  => false,
                'index'     => 'stores',
                'is_system' => true,
        ));
		
		$this->addExportType('*/*/exportCsv', Mage::helper('manager')->__('CSV'));
		$this->addExportType('*/*/exportXml', Mage::helper('manager')->__('XML'));
	  
      return parent::_prepareColumns();
  }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('manager_id');
        $this->getMassactionBlock()->setFormFieldName('manager');

        $this->getMassactionBlock()->addItem('delete', array(
             'label'    => Mage::helper('manager')->__('Delete'),
             'url'      => $this->getUrl('*/*/massDelete'),
             'confirm'  => Mage::helper('manager')->__('Are you sure?')
        ));

        $statuses = Mage::getSingleton('manager/status')->getOptionArray();

        array_unshift($statuses, array('label'=>'', 'value'=>''));
        $this->getMassactionBlock()->addItem('status', array(
             'label'=> Mage::helper('manager')->__('Change status'),
             'url'  => $this->getUrl('*/*/massStatus', array('_current'=>true)),
             'additional' => array(
                    'visibility' => array(
                         'name' => 'status',
                         'type' => 'select',
                         'class' => 'required-entry',
                         'label' => Mage::helper('manager')->__('Status'),
                         'values' => $statuses
                     )
             )
        ));
        return $this;
    }

  public function getRowUrl($row)
  {
      return $this->getUrl('*/*/edit', array('id' => $row->getId()));
  }

}