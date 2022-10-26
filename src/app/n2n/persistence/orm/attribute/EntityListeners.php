<?php
namespace n2n\persistence\orm\attribute;

use Attribute;
use n2n\util\type\ArgUtils;

#[Attribute(Attribute::TARGET_CLASS)]
class EntityListeners {
	/**
	 * @var string[]
	 */
	private array $classes;

	public function __construct(string ...$classes) {
		$this->classes = $classes;
	}

	/**
	 * @return string[]
	 */
	public function getClasses() {
		return $this->classes;
	}
}