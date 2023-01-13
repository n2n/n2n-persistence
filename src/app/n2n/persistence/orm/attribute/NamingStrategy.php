<?php
namespace n2n\persistence\orm\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class NamingStrategy {
	public function __construct(private string $namingStrategy) {

	}

	public function getNamingStrategy() {
		return new $this->namingStrategy();
	}
}