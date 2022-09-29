<?php
namespace n2n\persistence\orm\attribute;

use Attribute;
use n2n\util\type\ArgUtils;

#[Attribute(Attribute::TARGET_CLASS)]
class DiscriminatorColumn {
	public function __construct(private string $columnName) {
		ArgUtils::assertTrue(0 < mb_strlen($this->columnName));
	}

	public function getColumnName() {
		return $this->columnName;
	}
}