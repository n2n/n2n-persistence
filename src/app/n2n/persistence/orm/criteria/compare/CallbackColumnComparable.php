<?php

namespace n2n\persistence\orm\criteria\compare;

use n2n\spec\dbo\meta\data\QueryItem;
use n2n\persistence\orm\query\QueryState;
use n2n\util\type\TypeConstraint;
use n2n\spec\dbo\meta\data\impl\QueryPlaceMarker;
use n2n\persistence\meta\data\QueryPartGroup;

class CallbackColumnComparable extends ColumnComparableAdapter {

	public function __construct(QueryItem $comparableQueryItem,  private QueryState $queryState,
			TypeConstraint $typeConstraint, private PlaceholderValueMapper $placeholderValueCallback) {
		parent::__construct(CriteriaComparator::getOperators(false),
				$typeConstraint, $comparableQueryItem);
	}

	private function valueToScalar(mixed $value): mixed {
		return ($this->placeholderValueCallback)($value);
	}

	public function buildCounterpartQueryItemFromValue(string $operator, mixed $value): QueryItem {
		if ($operator != CriteriaComparator::OPERATOR_IN && $operator != CriteriaComparator::OPERATOR_NOT_IN) {
			$value = $this->valueToScalar($value);
			return new QueryPlaceMarker($this->queryState->registerPlaceholderValue($value));
		}

		$queryPartGroup = new QueryPartGroup();
		foreach ($value as $fieldValue) {
			$fieldValue = $this->valueToScalar($fieldValue);
			$queryPartGroup->addQueryPart(
					new QueryPlaceMarker($this->queryState->registerPlaceholderValue($fieldValue)));
		}
		return $queryPartGroup;
	}
}