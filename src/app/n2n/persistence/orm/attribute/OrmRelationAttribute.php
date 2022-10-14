<?php

namespace n2n\persistence\orm\attribute;

use n2n\persistence\orm\CascadeType;
use n2n\persistence\orm\FetchType;
use n2n\util\type\ArgUtils;

abstract class OrmRelationAttribute {
	private $orphanRemoval = false;

	public function __construct(private ?string $targetEntityClass = null,
			private ?int $cascadeType = CascadeType::NONE, $fetchType = FetchType::LAZY) {

		ArgUtils::valEnum($fetchType, FetchType::getValues(), null, true, 'fetchType');
		$this->fetchType = $fetchType;
	}

	public function getTargetEntity() {
		return $this->targetEntityClass;
	}

	public function getCascade() {
		return $this->cascadeType;
	}

	public function getFetch() {
		return $this->fetchType;
	}
}