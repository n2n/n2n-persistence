<?php
namespace n2n\persistence\orm\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToMany extends MappableOrmRelationAttribute {
	public function __construct(string $targetEntityClass, string $mappedBy = null,
			int $cascadeType = null, string $fetchType = null) {

		parent::__construct($targetEntityClass, $mappedBy, $cascadeType, $fetchType);
	}
}