<?php

namespace n2n\persistence\orm\store\datadefinition;

use n2n\spec\dbo\meta\structure\Database;

class DdUpdateProcess {

	function __construct(private Database $database) {

	}



	function getTable() {
		$this->database->getMetaEntityByName();
		$this->database->createMetaEntityFactory()->createTable()

	}
}