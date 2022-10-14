<?php
namespace n2n\persistence\orm\attribute;

use Attribute;
use n2n\persistence\orm\property\AttributeWithTarget;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Embedded implements AttributeWithTarget {
	/**
	 * @param \ReflectionClass $targetClass
	 * @param string $columnPrefix
	 * @param string $columnSuffix
	 * @throws \InvalidArgumentException
	 */
	public function __construct(private ?string $targetClass = null, private ?string $columnPrefix = null, private ?string $columnSuffix = null) {
	}

	/**
	 * @return string|null
	 */
	public function getTargetEntity(): ?string {
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