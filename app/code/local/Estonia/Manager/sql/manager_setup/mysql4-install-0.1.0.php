<?php

$installer = $this;

$installer->startSetup();

$installer->run("

-- DROP TABLE IF EXISTS {$this->getTable('manager')};
CREATE TABLE {$this->getTable('manager')} (
  `manager_id` int(11) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `code` varchar(255) not null default '',
  `tel` varchar(255) NOT NULL default '',
  `email` varchar(255) NOT NULL default '',
  `status` smallint(6) NOT NULL default '0',
  `created_time` datetime NULL,
  `update_time` datetime NULL,
  PRIMARY KEY (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    ");

$installer->endSetup(); 