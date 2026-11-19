<?php

namespace n2n\persistence\orm\store\datadefinition\mock;

use n2n\persistence\meta\structure\common\TableAdapter;
use n2n\spec\dbo\meta\structure\ColumnFactory;
use n2n\spec\dbo\meta\structure\Table;

class TableMock extends TableAdapter {

	function copy(?string $newTableName = null): Table {
		$newTable = new TableMock($newTableName ?? $this->getName());
		$newTable->applyColumnsFrom($this);
		$newTable->applyIndexesFrom($this);
		return $newTable;
	}

	function createColumnFactory(): ColumnFactory {
		return new ColumnFactoryMock($this);
	}

	function generatePrimaryKeyName() {
		return 'PRIMARY';
	}
}
