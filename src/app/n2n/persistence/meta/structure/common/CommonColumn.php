<?php
namespace n2n\persistence\meta\structure\common;

use n2n\spec\dbo\meta\structure\Column;
use n2n\spec\dbo\meta\structure\Table;

interface CommonColumn extends Column {
	public function setTable(Table $table);
	public function registerChangeListener(ColumnChangeListener $columnChangeListener);
	public function unregisterChangeListener(ColumnChangeListener $changeListener);
}