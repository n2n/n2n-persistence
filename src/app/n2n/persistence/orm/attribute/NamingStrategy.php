<?php
namespace n2n\persistence\orm\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class NamingStrategy {
	public function __construct(private \n2n\persistence\orm\model\NamingStrategy $namingStrategy) {

	}

	public function getNamingStrategy() {
		return $this->namingStrategy;
	}
}