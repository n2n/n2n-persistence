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
namespace n2n\persistence\orm\property\impl;

use n2n\reflection\property\TypeConstraint;
use n2n\reflection\property\AccessProxy;
use n2n\persistence\orm\property\BasicEntityProperty;
use n2n\persistence\orm\query\from\MetaTreePoint;
use n2n\persistence\orm\query\QueryState;
use n2n\persistence\meta\data\QueryItem;
use n2n\reflection\ArgUtils;
use n2n\persistence\orm\query\select\DateTimeSelection;
use n2n\persistence\orm\criteria\compare\DateTimeColumnComparable;
use n2n\persistence\orm\store\action\PersistAction;
use n2n\persistence\orm\store\operation\MergeOperation;
use n2n\persistence\Pdo;
use n2n\persistence\orm\store\action\RemoveAction;
use n2n\persistence\orm\EntityManager;

class DateTimeEntityProperty extends ColumnPropertyAdapter implements BasicEntityProperty {
	public function __construct(AccessProxy $accessProxy, $columnName) {
		$accessProxy->setConstraint(TypeConstraint::createSimple('DateTime', true));

		parent::__construct($accessProxy, $columnName);
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\ColumnComparableEntityProperty::createComparisonStrategy()
	 */
	public function createColumnComparable(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		return new DateTimeColumnComparable($this->createQueryColumn($metaTreePoint->getMeta()), $queryState);
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\BasicEntityProperty::createColumnComparableFromQueryItem()
	 */
	public function createColumnComparableFromQueryItem(QueryItem $queryItem, QueryState $queryState) {
		return new DateTimeColumnComparable($queryItem, $queryState);
	}
	
	public function createSelection(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		return new DateTimeSelection($this->createQueryColumn($metaTreePoint->getMeta()),
				$queryState->getEntityManager()->getPdo()->getMetaData()
						->getDialect()->getOrmDialectConfig());
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\BasicEntityProperty::valueToRep()
	 */
	public function valueToRep($value): string {
		ArgUtils::assertTrue($value instanceof \DateTime);
		return $value->getTimestamp();
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\BasicEntityProperty::repToValue()
	 */
	public function repToValue(string $rep) {		
		ArgUtils::assertTrue(is_numeric($rep));
		$value = new \DateTime();
		$value->setTimestamp($rep);
		return $value;
	}
	
	public function supplyPersistAction($mappedValue, $valueHash, PersistAction $persistAction) {
		$rawValue = $persistAction->getActionQueue()->getEntityManager()->getPdo()->getMetaData()
				->getDialect()->getOrmDialectConfig()->buildDateTimeRawValue($mappedValue);
		
		$persistAction->getMeta()->setRawValue($this->getEntityModel(), $this->getColumnName(), $rawValue);
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\EntityProperty::buildValueHash()
	 */
	public function buildValueHash($value, EntityManager $em) {
		if ($value === null) return null;
		return $this->valueToRep($value);
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\EntityProperty::mergeValue()
	 */
	public function mergeValue($value, $sameEntity, MergeOperation $mergeOperation) {
		if ($sameEntity || $value === null) {
			return $value;
		}
		
		ArgUtils::assertTrue($value instanceof \DateTime);
		return clone $value;
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\EntityProperty::supplyRemoveAction()
	 */
	public function supplyRemoveAction($value, $valueHash, RemoveAction $removeAction) {
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\BasicEntityProperty::parseValue()
	 */
	public function parseValue($raw, Pdo $pdo) {
		return $raw;
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\BasicEntityProperty::buildRaw()
	 */
	public function buildRaw($value, Pdo $pdo) {
		return $value;
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\BasicEntityProperty::createSelectionFromQueryItem()
	 */
	public function createSelectionFromQueryItem(QueryItem $queryItem, QueryState $queryState) {
		return new DateTimeSelection($queryItem, $queryState);
	}
}
