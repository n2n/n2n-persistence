<?php
namespace n2n\persistence\orm\attribute;

use Attribute;
use n2n\util\type\ArgUtils;

#[Attribute(Attribute::TARGET_CLASS)]
class EntityListeners {
	/**
	 * @var \ReflectionClass[]
	 */
	private array $classes;

	public function __construct(string ...$classes) {
		$this->classes = array_map(fn ($className) => new \ReflectionClass($className), $classes);
	}

	/**
	 * @return \ReflectionClass[]
	 */
	public function getClasses() {
		return $this->classes;
	}
}