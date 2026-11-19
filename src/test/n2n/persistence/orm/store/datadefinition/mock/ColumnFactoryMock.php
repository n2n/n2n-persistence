<?php

namespace n2n\persistence\orm\store\datadefinition\mock;

use n2n\persistence\meta\structure\common\CommonBinaryColumn;
use n2n\persistence\meta\structure\common\CommonBlobColumn;
use n2n\persistence\meta\structure\common\CommonEnumColumn;
use n2n\persistence\meta\structure\common\CommonFixedPointColumn;
use n2n\persistence\meta\structure\common\CommonFloatingPointColumn;
use n2n\persistence\meta\structure\common\CommonIntegerColumn;
use n2n\persistence\meta\structure\common\CommonStringColumn;
use n2n\persistence\meta\structure\common\CommonTextColumn;
use n2n\spec\dbo\meta\structure\BinaryColumn;
use n2n\spec\dbo\meta\structure\BlobColumn;
use n2n\spec\dbo\meta\structure\ColumnFactory;
use n2n\spec\dbo\meta\structure\DateTimeColumn;
use n2n\spec\dbo\meta\structure\EnumColumn;
use n2n\spec\dbo\meta\structure\FixedPointColumn;
use n2n\spec\dbo\meta\structure\FloatingPointColumn;
use n2n\spec\dbo\meta\structure\IntegerColumn;
use n2n\spec\dbo\meta\structure\StringColumn;
use n2n\spec\dbo\meta\structure\Table;
use n2n\spec\dbo\meta\structure\TextColumn;
use n2n\spec\dbo\meta\structure\Column;

/**
 * Like the real implementations, every created column is directly added to the table.
 */
class ColumnFactoryMock implements ColumnFactory {

	function __construct(private TableMock $table) {
	}

	function getTable(): Table {
		return $this->table;
	}

	private function add(Column $column): Column {
		$this->table->addColumn($column);
		return $column;
	}

	function createIntegerColumn(string $name, int $size, bool $signed = true): IntegerColumn {
		return $this->add(new CommonIntegerColumn($name, $size, $signed));
	}

	function createStringColumn(string $name, int $length, ?string $charset = null): StringColumn {
		return $this->add(new CommonStringColumn($name, $length, $charset));
	}

	function createTextColumn(string $name, int $size, ?string $charset = null): TextColumn {
		return $this->add(new CommonTextColumn($name, $size, $charset));
	}

	function createBinaryColumn(string $name, int $size): BinaryColumn {
		return $this->add(new CommonBinaryColumn($name, $size));
	}

	function createBlobColumn(string $name, int $size): BlobColumn {
		return $this->add(new CommonBlobColumn($name, $size));
	}

	function createDateTimeColumn(string $name, bool $dateAvailable = true, bool $timeAvailable = true): DateTimeColumn {
		return $this->add(new DateTimeColumnMock($name, $dateAvailable, $timeAvailable));
	}

	function createEnumColumn(string $name, array $values): EnumColumn {
		return $this->add(new CommonEnumColumn($name, $values));
	}

	function createFixedPointColumn(string $name, int $numIntegerDigits, int $numDecimalDigits): FixedPointColumn {
		return $this->add(new CommonFixedPointColumn($name, $numIntegerDigits, $numDecimalDigits));
	}

	function createFloatingPointColumn(string $name, int $size): FloatingPointColumn {
		return $this->add(new CommonFloatingPointColumn($name, $size));
	}
}
