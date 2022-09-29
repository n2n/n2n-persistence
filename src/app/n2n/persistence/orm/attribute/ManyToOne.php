<?php
namespace n2n\persistence\orm\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToOne extends MappableOrmRelationAttribute {
	public function __construct(\ReflectionClass $targetEntityClass, int $cascadeType = null,
			string $fetchType = null) {
		if (3 < count(func_get_args())) {
			throw new \InvalidArgumentException('Maximum parameter number for AnnoManyToOne is 3.');
		}

		parent::__construct($targetEntityClass, $cascadeType, $fetchType);
	}
}