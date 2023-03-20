<?php
namespace n2n\persistence\orm\attribute;

use Attribute;
use n2n\persistence\orm\CascadeType;
use n2n\persistence\orm\FetchType;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToMany extends MappableOrmRelationAttribute {
	public function __construct(string $targetEntity, string $mappedBy = null,
			int $cascade = null, string $fetchType = null, private bool $orphanRemoval = false) {
		parent::__construct($targetEntity, $mappedBy, $cascade ?? CascadeType::NONE,
				$fetchType ?? FetchType::LAZY);
	}

	public function isOrphanRemoval() {
		return $this->orphanRemoval;
	}
}