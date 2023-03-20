<?php
namespace n2n\persistence\orm\attribute;

use Attribute;
use n2n\persistence\orm\CascadeType;
use n2n\persistence\orm\FetchType;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToMany extends MappableOrmRelationAttribute {
	public function __construct(string $targetEntity, string $mappedBy = null,
			int $cascade = null, string $fetch = null) {

		parent::__construct($targetEntity, $mappedBy, $cascade ?? CascadeType::NONE,
				$fetch ?? FetchType::LAZY);
	}
}