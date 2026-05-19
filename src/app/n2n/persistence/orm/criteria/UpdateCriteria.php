<?php

namespace n2n\persistence\orm\criteria;

use n2n\persistence\orm\criteria\item\CrIt;
use n2n\persistence\orm\criteria\item\CriteriaItem;

class UpdateCriteria {
	/**
	 * @var UpdateSetItem[]
	 */
	private array $updateSetItems = [];

	public function set($item, $value): static {
		$criteriaItem = CrIt::pfLenient($item);

		$this->updateSetItems[] = new UpdateSetItem($criteriaItem, $value);

		return $this;
	}
}

class UpdateSetItem {
	function __construct(public CriteriaItem $criteriaItem, public mixed $value) {

	}
}