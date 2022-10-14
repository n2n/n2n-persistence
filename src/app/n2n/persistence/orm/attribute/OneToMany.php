<?php
namespace n2n\persistence\orm\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToMany extends MappableOrmRelationAttribute {
	public function __construct(string $targetEntity, string $mappedBy = null,
			int $cascade = null, string $fetchType = null, private bool $orphanRemoval = false) {
		parent::__construct($targetEntity, $mappedBy, $cascade, $fetchType);
	}

	public function isOrphanRemoval() {
		return $this->orphanRemoval;
	}
}