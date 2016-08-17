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

use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\criteria\compare\ColumnComparable;
use n2n\persistence\orm\query\QueryState;
use n2n\persistence\orm\query\from\Tree;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\criteria\compare\CriteriaComparator;
use n2n\persistence\meta\data\QueryPartGroup;
use n2n\persistence\orm\criteria\compare\ComparisonStrategy;

class InverseJoinColumnToManyQueryItemFactory implements ToManyQueryItemFactory {
	private $targetEntityModel;
	private $inverseJoinColumnName;
	
	public function ___construct(EntityModel $targetEntityModel, $inverseJoinColumnName) {
		$this->targetEntityModel = $targetEntityModel;
		$this->inverseJoinColumnName = $inverseJoinColumnName;
	}
	
	public function createQueryItem(ColumnComparable $idColumnComparable, QueryState $queryState) {
		$subTree = new Tree($queryState);
		$subMetaTreePoint = $subTree->createBaseTreePoint($this->targetEntityModel, 'e');
		$inverseJoinQueryColumn = $subMetaTreePoint->getMeta()->registerColumn(
				$this->targetEntityModel, $this->inverseJoinColumnName);

		$comparationStrategy = $subMetaTreePoint->requestComparationStrategy();
		IllegalStateException::assertTrue($comparationStrategy->getType() == ComparisonStrategy::TYPE_COLUMN);

		$subSelectBuilder = $this->em->getPdo()->getMetaData()->createSelectStatementBuilder();
		
		$subSelectBuilder->addSelectColumn($comparationStrategy->getColumnComparable()
				->buildQueryItem(CriteriaComparator::OPERATOR_EQUAL));
		$subSelectBuilder->getWhereComparator()->match($inverseJoinQueryColumn,
				CriteriaComparator::OPERATOR_EQUAL,
				$idColumnComparable->buildQueryItem(CriteriaComparator::OPERATOR_EQUAL));

		return new QueryPartGroup($subSelectBuilder->toQueryResult());
	}
}
