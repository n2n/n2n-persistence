<?php

namespace n2n\persistence\orm\query\update;

use n2n\spec\dbo\meta\data\QueryItem;

interface Settable {

	public function getQueryItem(): QueryItem;
	/**
	 * @param string $operator
	 * @param mixed $value
	 * @return QueryItem
	 * @throws \n2n\persistence\orm\criteria\CriteriaConflictException
	 */
	public function buildCounterpartQueryItemFromValue(string $operator, mixed $value): QueryItem;
}