<?php

namespace n2n\persistence\orm\attribute;

abstract class MappableOrmRelationAttribute extends OrmRelationAttribute {
	private $mappedBy = null;

	public function __construct(\ReflectionClass $targetEntityClass, string $mappedBy = null,
			int $cascadeType = null, string $fetchType = null) {
		parent::__construct($targetEntityClass, $cascadeType, $fetchType);

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