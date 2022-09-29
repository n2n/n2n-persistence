<?php
namespace n2n\persistence\orm\attribute;

use Attribute;
use n2n\util\type\ArgUtils;

#[Attribute(Attribute::TARGET_CLASS)]
class DiscriminatorValue {
	public function __construct(private ?string $value) {

	}

	public function getValue() {
		return $this->value;
	}
}