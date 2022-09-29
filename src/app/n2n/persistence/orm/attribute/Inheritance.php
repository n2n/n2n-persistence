<?php
namespace n2n\persistence\orm\attribute;

use Attribute;
use n2n\persistence\orm\InheritanceType;
use n2n\util\type\ArgUtils;

#[Attribute(Attribute::TARGET_CLASS)]
class Inheritance {
	public function __construct(private string $strategy) {
		ArgUtils::valEnum($strategy, InheritanceType::getValues());
	}

	public function getStrategy() {
		return $this->strategy;
	}
}