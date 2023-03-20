<?php
namespace n2n\persistence\orm\attribute;

use Attribute;
use n2n\persistence\orm\CascadeType;
use n2n\persistence\orm\FetchType;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToOne extends MappableOrmRelationAttribute {
	public function __construct(string $targetEntity = null, int $cascade = null,
			string $fetch = null) {
		if (3 < count(func_get_args())) {
			throw new \InvalidArgumentException('Maximum parameter number for AnnoManyToOne is 3.');
		}

		parent::__construct($targetEntity, null, $cascade ?? CascadeType::NONE,
				$fetch ?? FetchType::LAZY);
	}
}