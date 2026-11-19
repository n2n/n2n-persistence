<?php

namespace n2n\persistence\orm\store\datadefinition;

use n2n\spec\dbo\meta\structure\Table;
use n2n\spec\dbo\meta\structure\ColumnFactory;
use n2n\spec\dbo\meta\structure\Column;

class DdTableUpdateProcess {
	readonly ColumnFactory $columnFactory;

	function __construct(public Table $table) {
		$this->columnFactory = $this->table->createColumnFactory();
	}

	function putColumn(Column $column): void {
		if ($this->table->containsColumnName($column->getName())) {
			$this->table->removeColumnByName($column->getName());
		}

		$this->table->addColumn($column);
	}

}