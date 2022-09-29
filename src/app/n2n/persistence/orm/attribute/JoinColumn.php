<?php
namespace n2n\persistence\orm\attribute;

use n2n\util\type\ArgUtils;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class JoinColumn {
	public function __construct(private string $name) {
	}

	public function getName() {
		return $this->name;
	}

	public function setName($name) {
		$this->name = $name;
	}
}