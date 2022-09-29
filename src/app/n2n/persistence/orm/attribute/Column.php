<?php
namespace n2n\persistence\orm\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column {
	public function __construct(private ?string $name = null) {
	}

	public function getName() {
		return $this->name;
	}
}