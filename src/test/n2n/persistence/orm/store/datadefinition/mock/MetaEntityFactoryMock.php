<?php

namespace n2n\persistence\orm\store\datadefinition\mock;

use n2n\persistence\meta\structure\common\CommonView;
use n2n\spec\dbo\meta\structure\MetaEntityFactory;
use n2n\spec\dbo\meta\structure\Database;
use n2n\spec\dbo\meta\structure\Table;
use n2n\spec\dbo\meta\structure\View;

class MetaEntityFactoryMock implements MetaEntityFactory {

	function __construct(private DatabaseMock $database) {
	}

	function getDatabase(): Database {
		return $this->database;
	}

	function createTable(string $name): Table {
		$table = new TableMock($name);
		$this->database->addMetaEntity($table);
		return $table;
	}

	function createView(string $name, string $query): View {
		$view = new CommonView($name, $query);
		$this->database->addMetaEntity($view);
		return $view;
	}
}
