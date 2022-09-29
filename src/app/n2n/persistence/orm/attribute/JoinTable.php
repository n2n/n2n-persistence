<?php
namespace n2n\persistence\orm\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class JoinTable {

	public function __construct(private ?string $name = null, private ?string $joinColumnName = null,
			private ?string $inverseJoinColumnName = null) {
	}

	public function getName() {
		return $this->name;
	}

	public function getJoinColumnName() {
		return $this->joinColumnName;
	}

	public function getInverseJoinColumnName() {
		return $this->inverseJoinColumnName;
	}
}