<?php

namespace n2n\persistence\orm\attribute;

use n2n\persistence\orm\CascadeType;
use n2n\persistence\orm\FetchType;
use n2n\util\type\ArgUtils;
use n2n\persistence\orm\property\AttributeWithTarget;

abstract class OrmRelationAttribute implements AttributeWithTarget {
	private $orphanRemoval = false;

	public function __construct(private ?string $targetEntity = null,
			private ?int $cascade = CascadeType::NONE, private ?string $fetch = FetchType::LAZY) {

		ArgUtils::valEnum($this->fetch, FetchType::getValues(), null, true, 'fetchType');
	}

	public function getTargetEntity(): ?string {
		return $this->targetEntity;
	}

	public function getCascade() {
		return $this->cascade;
	}

	public function getFetch() {
		return $this->fetch;
	}
}