<?php
namespace n2n\persistence\orm\attribute;

use Attribute;
use n2n\util\type\ArgUtils;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class AttributeOverrides {
	public function __construct(private array $propertyColumnMap) {
		ArgUtils::valArray($this->propertyColumnMap, 'string');
	}

	public function getPropertyColumnMap() {
		return $this->propertyColumnMap;
	}
}