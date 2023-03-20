<?php

namespace n2n\persistence\orm\attribute;

abstract class MappableOrmRelationAttribute extends OrmRelationAttribute {
	private $mappedBy = null;

	public function __construct(string $targetEntity = null, string $mappedBy = null,
			int $cascade = null, string $fetch = null) {
		parent::__construct($targetEntity, $cascade, $fetch);

		if ($mappedBy !== null) {
			if (is_numeric($mappedBy)) {
				throw new \InvalidArgumentException('Numeric value \'' . $mappedBy . '\' passed to Argument 2 ($mappedBy), '
						. 'string expected.');
			}
			$this->mappedBy = $mappedBy;
		}
	}

	public function getMappedBy() {
		return $this->mappedBy;
	}
}