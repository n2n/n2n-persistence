<?php
namespace n2n\persistence\orm\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Embedded {
	/**
	 * @param string|null $targetClass
	 * @param string|null $columnPrefix
	 * @param string|null $columnSuffix
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
	 * @return string|null
	 */
	public function getColumnPrefix() {
		return $this->columnPrefix;
	}

	/**
	 * @return string|null
	 */
	public function getColumnSuffix() {
		return $this->columnSuffix;
	}
}