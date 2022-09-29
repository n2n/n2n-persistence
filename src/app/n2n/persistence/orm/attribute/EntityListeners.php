<?php
namespace n2n\persistence\orm\attribute;

use Attribute;
use n2n\util\type\ArgUtils;

#[Attribute(Attribute::TARGET_CLASS)]
class EntityListeners {
	private $classes;

	public function __construct(array ...$classes) {
		ArgUtils::valArray($classes, \ReflectionClass::class);
		$this->classes = $classes;
	}

	public function getClasses() {
		return $this->classes;
	}
}