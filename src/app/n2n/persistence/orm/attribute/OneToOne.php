<?php
namespace n2n\persistence\orm\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToOne extends MappableOrmRelationAttribute {
	public function __construct(\ReflectionClass $targetEntityClass, string $mappedBy = null,
			int $cascadeType = null, string $fetchType = null, private bool $orphanRemoval = false) {
		parent::__construct($targetEntityClass, $mappedBy, $cascadeType, $fetchType);
	}

	public function isOrphanRemoval() {
		return $this->orphanRemoval;
	}
}