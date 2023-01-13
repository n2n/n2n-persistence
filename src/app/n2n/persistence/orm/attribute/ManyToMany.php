<?php
namespace n2n\persistence\orm\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToMany extends MappableOrmRelationAttribute {
	public function __construct(string $targetEntity, string $mappedBy = null,
			int $cascade = null, string $fetchType = null) {

		parent::__construct($targetEntity, $mappedBy, $cascade, $fetchType);
	}
}