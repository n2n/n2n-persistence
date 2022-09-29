<?php
namespace n2n\persistence\orm\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Id {
	public function __construct(private bool $generated = true, private ?string $sequenceName = null) {

	}

	public function isGenerated() {
		return $this->generated;
	}

	public function getSequenceName() {
		return $this->sequenceName;
	}
}