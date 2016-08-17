<?php
/*
 * Copyright (c) 2012-2016, Hofmänner New Media.
 * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS FILE HEADER.
 *
 * This file is part of the N2N FRAMEWORK.
 *
 * The N2N FRAMEWORK is free software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * N2N is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
 * the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details: http://www.gnu.org/licenses/
 *
 * The following people participated in this project:
 *
 * Andreas von Burg.....: Architect, Lead Developer
 * Bert Hofmänner.......: Idea, Community Leader, Marketing
 * Thomas Günther.......: Developer, Hangar
 */
namespace n2n\persistence\orm\property\impl\relation\compare;

use n2n\persistence\orm\query\from\MetaTreePoint;
use n2n\persistence\orm\query\from\TreePath;
use n2n\persistence\orm\criteria\compare\CriteriaComparator;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\criteria\compare\ComparisonStrategy;
use n2n\persistence\meta\data\QueryPlaceMarker;
use n2n\reflection\ArgUtils;
use n2n\persistence\orm\query\QueryState;
use n2n\reflection\property\TypeConstraint;
use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\criteria\compare\CustomComparable;
use n2n\persistence\meta\data\QueryComparator;
use n2n\persistence\orm\criteria\CriteriaConflictException;
use n2n\reflection\property\ValueIncompatibleWithConstraintsException;
use n2n\persistence\orm\criteria\compare\QueryComparatorBuilder;

class ToManyCustomComparable implements CustomComparable {
	private $metaTreePoint;
	private $targetIdTreePath;
	private $toManyQueryItemFactory;
	private $queryState;
	private $typeConstraint;

	private $entityColumnComparable;
	private $toManyQueryItem;

	public function __construct(MetaTreePoint $metaTreePoint, EntityModel $targetEntityModel, 
			TreePath $targetIdTreePath, ToManyQueryItemFactory $toManyQueryItemFactory, 
			QueryState $queryState) {
		$this->metaTreePoint = $metaTreePoint;
		$this->targetIdTreePath = $targetIdTreePath;
		$this->targetEntityModel = $targetEntityModel;
		$this->toManyQueryItemFactory = $toManyQueryItemFactory;
		$this->queryState = $queryState;
		$this->typeConstraint = TypeConstraint::createSimple($this->targetEntityModel->getClass()->getName(), true);
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\criteria\compare\ColumnComparable::getAvailableOperators()
	*/
	public function getAvailableOperators() {
		return array(CriteriaComparator::OPERATOR_CONTAINS, CriteriaComparator::OPERATOR_CONTAINS_NOT);
	}
		
	private function requestEntityColumnComparable() {
		if ($this->entityColumnComparable !== null) {
			return $this->entityColumnComparable;
		}
		
		$targetIdComparisonStrategy = $this->metaTreePoint->requestPropertyComparisonStrategy(
				$this->targetIdTreePath);
		IllegalStateException::assertTrue($targetIdComparisonStrategy->getType() 
				== ComparisonStrategy::TYPE_COLUMN);
		
		return $this->entityColumnComparable = new IdColumnComparableDecorator(
				$targetIdComparisonStrategy->getColumnComparable(),
				$this->targetEntityModel);
	}
	
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\criteria\compare\ColumnComparable::buildQueryItem($operator)
	*/
	public function buildQueryItem($operator) {
		
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\criteria\compare\ColumnComparable::buildCounterpartQueryItemFromValue()
	 */
	public function buildCounterpartQueryItemFromValue($operator, $value) {
		if ($operator == CriteriaComparator::OPERATOR_CONTAINS) {
			return $this->requestEntityColumnComparable()
					->buildCounterpartQueryItemFromValue($operator, $value);
		}
		
		return new QueryPlaceMarker($this->queryState->registerPlaceholderValue(
				$this->parseFieldValue($value)));
	}
	
	private function validateOperator($operator) {
		if ($operator == CriteriaComparator::OPERATOR_CONTAINS 
				|| $operator == CriteriaComparator::OPERATOR_CONTAINS_NOT) return;
		
		throw new CriteriaConflictException('Invalid operator \'' . $operator 
				. '\' for comparison. Available operators: ' . CriteriaComparator::OPERATOR_CONTAINS 
				. ', ' . CriteriaComparator::OPERATOR_CONTAINS_NOT);
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\criteria\compare\CustomComparable::compareWithValue()
	 */
	public function compareWithValue(QueryComparator $queryComparator, $operator, $value) {
		$this->validateOperator($operator);
		try {
			$this->typeConstraint->validate($value);
		} catch (ValueIncompatibleWithConstraintsException $e) {
			throw new CriteriaConflictException('Value can not be compared with property.', 0, $e);
		}
		
		if ($operator == CriteriaComparator::OPERATOR_CONTAINS) {
			$entityColumnComparable = $this->requestEntityColumnComparable();
			$queryComparator->match(
					$entityColumnComparable->buildQueryItem(CriteriaComparator::OPERATOR_EQUAL), 
					QueryComparator::OPERATOR_EQUAL,
					$entityColumnComparable->buildCounterpartQueryItemFromValue(
							CriteriaComparator::OPERATOR_EQUAL, $value));
			return;
		}
		
		$queryComparator->match(
				new QueryPlaceMarker($this->queryState->registerPlaceholderValue(
						$this->parseFieldValue($value)),
				QueryComparator::OPERATOR_IN, $this->requestToManyQueryItem()));
	}
	/**
	 * @param object $entity
	 * @return string
	 */
	private function parseTargetIdRaw($entity) {
		$targetIdProperty = $this->targetEntityModel->getIdDef()->getEntityProperty();
	
		$id = null;
		if ($entity !== null) {
			ArgUtils::assertTrue(is_object($entity));
			$id = $targetIdProperty->readValue($entity);
		}
	
		return $targetIdProperty->buildRaw($id, $this->queryState->getEntityManager()->getPdo());
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\criteria\compare\CustomComparable::compareWith()
	 */
	public function compareWith(QueryComparator $queryComparator, $operator, ComparisonStrategy $comparisonStrategy) {
		$this->validateOperator($operator);
		
		if ($comparisonStrategy->getType() != ComparisonStrategy::TYPE_COLUMN) {
			throw new CriteriaConflictException('Incompatible comparison');
		}
		
		$columnComparable = $comparisonStrategy->getColumnComparable();
		
		$oppositeOperator = QueryComparatorBuilder::oppositeOperator($operator);
		if (!$this->typeConstraint->isPassableBy($columnComparable->getTypeConstraint($oppositeOperator))) {
			$arrayTypeConstraint = TypeConstraint::createArrayLike(null, false, $this->typeConstraint);
			throw new CriteriaConflictException('Incompatible comparison: ' 
					. $arrayTypeConstraint->__toString() . ' ' . $operator . ' ' 
					. $columnComparable->getTypeConstraint($oppositeOperator) );
		}
		
		if ($operator == CriteriaComparator::OPERATOR_CONTAINS) {
			$entityColumnComparable = $this->requestEntityColumnComparable();
			$queryComparator->match(
					$entityColumnComparable->buildQueryItem(CriteriaComparator::OPERATOR_EQUAL),
					QueryComparator::OPERATOR_EQUAL,
					$columnComparable->buildQueryItem(CriteriaComparator::OPERATOR_EQUAL));
			return;
		}
		
		$queryComparator->match(
				$columnComparable->buildQueryItem(CriteriaComparator::OPERATOR_NOT_IN),
				QueryComparator::OPERATOR_NOT_IN, $this->requestToManyQueryItem());
	}
	
	private function requestToManyQueryItem() {
		if ($this->toManyQueryItem !== null) {
			return $this->toManyQueryItem;
		} 
		
		$entityModel = $this->metaTreePoint->getMeta()->getEntityModel();
		$idComparisonStrategy = $this->metaTreePoint->requestPropertyComparisonStrategy(
				$entityModel->getIdDef()->getPropertyName());
		
		return $this->toManyQueryItem = $this->toManyQueryItemFactory->createQueryItem(
				$idComparisonStrategy->getColumnComparable(), $this->queryState);
	}

}
