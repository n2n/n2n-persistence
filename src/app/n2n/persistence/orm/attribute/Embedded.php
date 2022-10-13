<?php
namespace n2n\persistence\orm\attribute;

use Attribute;
use n2n\util\type\ArgUtils;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Embedded {
	/**
	 * @param \ReflectionClass $targetClass
	 * @param string $columnPrefix
	 * @param string $columnSuffix
	 * @throws \InvalidArgumentException
	 */
	public function __construct(private string $targetClass, private ?string $columnPrefix = null, private ?string $columnSuffix = null) {
	}

	/**
	 * @return string
	 */
	public function getTargetClass() {
		return $this->targetClass;
	}

	/**
	 * @return string
	 */
	public function getColumnPrefix() {
		return $this->columnPrefix;
	}

	/**
	 * @return string
	 */
	public function getColumnSuffix() {
		return $this->columnSuffix;
	}
}