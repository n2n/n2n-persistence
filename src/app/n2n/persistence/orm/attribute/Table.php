<?php
namespace n2n\persistence\orm\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Table {

	public function __construct(private string $name) {

	}

	public function getName() {
		return $this->name;
	}
}