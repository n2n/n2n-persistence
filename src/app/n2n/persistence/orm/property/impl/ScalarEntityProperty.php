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
 * Bert Hofmänner.......: Idea, Frontend UI, Community Leader, Marketing
 * Thomas Günther.......: Developer, Hangar
 */
namespace n2n\persistence\orm\property\impl;

use n2n\reflection\property\AccessProxy;
use n2n\util\type\TypeConstraint;
use n2n\persistence\orm\property\BasicEntityProperty;
use n2n\persistence\orm\query\from\MetaTreePoint;
use n2n\persistence\orm\query\QueryState;
use n2n\persistence\orm\criteria\compare\ScalarColumnComparable;
use n2n\persistence\orm\query\select\SimpleSelection;
use n2n\persistence\orm\store\action\PersistAction;
use n2n\persistence\orm\store\operation\MergeOperation;
use n2n\persistence\orm\store\action\RemoveAction;
use n2n\spec\dbo\meta\data\QueryItem;
use n2n\persistence\Pdo;
use n2n\util\type\ArgUtils;
use n2n\persistence\orm\store\ValueHash;
use n2n\persistence\orm\store\CommonValueHash;
use n2n\persistence\orm\criteria\compare\ColumnComparable;
use n2n\persistence\orm\query\select\Selection;
use n2n\persistence\orm\property\ColumnEntityProperty;
use n2n\persistence\orm\property\EntityProperty;
use n2n\persistence\orm\store\action\supply\SupplyJob;
use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\model\EntityPropertyCollection;
use n2n\persistence\orm\query\from\meta\TreePointMeta;
use n2n\persistence\orm\store\operation\CascadeOperation;
use n2n\persistence\orm\query\select\EagerValueSelection;
use n2n\reflection\property\PropertyAccessException;
use n2n\persistence\orm\EntityDataException;
use n2n\util\ex\IllegalStateException;
use n2n\util\type\TypeUtils;
use n2n\util\magic\MagicContext;
use n2n\util\type\TypeConstraints;

class ScalarEntityProperty implements ColumnEntityProperty, BasicEntityProperty {
	private EntityModel $entityModel;

	private ?EntityProperty $parent = null;

	/**
	 * @param AccessProxy $accessProxy
	 * @param string $columnName
	 */
	public function __construct(private AccessProxy $accessProxy, private string $columnName) {
		$accessProxy->setConstraint(TypeConstraint::createSimple('scalar', true));
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\ColumnComparableEntityProperty::createComparisonStrategy()
	 */
	public function createColumnComparable(MetaTreePoint $metaTreePoint, QueryState $queryState): ColumnComparable {
		return new ScalarColumnComparable($this->createQueryColumn($metaTreePoint->getMeta()), $queryState);
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\BasicEntityProperty::createColumnComparableFromQueryItem()
	 */
	public function createColumnComparableFromQueryItem(QueryItem $queryItem, QueryState $queryState): ColumnComparable {
		return new ScalarColumnComparable($queryItem, $queryState);
	}
	
	public function createSelection(MetaTreePoint $metaTreePoint, QueryState $queryState): Selection {
//		return new SimpleSelection($this->createQueryColumn($metaTreePoint->getMeta()));
		return new EagerValueSelection($this->createQueryColumn($metaTreePoint->getMeta()), TypeConstraints::scalar(true));
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\BasicEntityProperty::valueToRep()
	 */
	public function valueToRep(mixed $value): string {
		ArgUtils::valScalar($value);
		return (string) $value;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\BasicEntityProperty::repToValue()
	 */
	public function repToValue(string $rep): mixed {
		return $rep;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::supplyPersistAction()
	 */
	public function supplyPersistAction(PersistAction $persistAction, $value, ValueHash $valueHash, ?ValueHash $oldValueHash): void {
		$persistAction->getMeta()->setRawValue($this->getEntityModel(), $this->getColumnName(), $value, /*$pdoDataType*/null, $this);
	}

	
	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::createValueHash()
	 */
	public function createValueHash(mixed $value, MagicContext $magicContext): ValueHash {
		return new CommonValueHash($value);
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::mergeValue()
	 */
	public function mergeValue(mixed $value, bool $sameEntity, MergeOperation $mergeOperation): mixed {
		return $value;
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::supplyRemoveAction()
	 */
	public function supplyRemoveAction(RemoveAction $removeAction, $value, ValueHash $oldValueHash) {
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\BasicEntityProperty::parseValue()
	 */
	public function parseValue(mixed $raw, Pdo $pdo): mixed {
		return $raw;
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\BasicEntityProperty::buildRaw()
	 */
	public function buildRaw(mixed $value, Pdo $pdo): mixed {
		return $value;
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\BasicEntityProperty::createSelectionFromQueryItem()
	 */
	public function createSelectionFromQueryItem(QueryItem $queryItem, QueryState $queryState): Selection {
		return new EagerValueSelection($queryItem,  TypeConstraints::scalar(true),null);
	}

	public function getColumnName() {
		return $this->columnName;
	}

	public function createQueryColumn(TreePointMeta $treePointMeta) {
		return $treePointMeta->registerColumn($this->getEntityModel(), $this->columnName);
	}

	public function createRepresentingQueryItem(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		return $this->createQueryColumn($metaTreePoint->getMeta());
	}

//	function containsChanges(PersistAction $action) {
//		$actionMeta = $action->getMeta();
//
//		foreach ($actionMeta->getItems() as $item) {
//			if ($item->getTableName() === $this->getEntityModel()->getTableName()
//					&& $item->containsColumnName($this->columnName)) {
//				return true;
//			}
//		}
//
//		return false;
//	}

	public function equals($obj) {
		return $obj instanceof EntityProperty
				&& $obj->getEntityModel()->equals($this->entityModel)
				&& $obj->getName() == $this->getName();
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::setEntityModel()
	 */
	public function setEntityModel(EntityModel $entityModel) {
		$this->entityModel = $entityModel;
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::getEntityModel()
	 */
	public function getEntityModel() {
		if ($this->entityModel === null) {
			throw new IllegalStateException('No EntityModel assigned.');
		}

		return $this->entityModel;
	}


	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::setParent()
	 */
	public function setParent(EntityProperty $parent) {
		$this->parent = $parent;
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::getParent()
	 */
	public function getParent() {
		return $this->parent;
	}


	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::getName()
	 */
	public function getName() {
		return $this->accessProxy->getPropertyName();
	}


	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::writeValue()
	 */
	public function writeValue(object $object, mixed $value): void {
		$this->accessProxy->setValue($object, $value);
	}

	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\EntityProperty::readValue()
	 */
	public function readValue(object $object): mixed {
		try {
			return $this->accessProxy->getValue($object);
		} catch (PropertyAccessException $e) {
			throw new EntityDataException('Failed to read value of ' . $this, 0, $e);
		}
	}

	public function toPropertyString() {
		return TypeUtils::prettyReflPropName($this->accessProxy->getProperty());
	}

	public function copy($value) {
		return $value;
	}

//	public function equals($obj) {
//		return $obj instanceof EntityProperty
//				&& $obj->getEntityModel()->equals($this->entityModel)
//				&& $obj->getName() == $this->getName();
//	}

	public function hasTargetEntityModel(): bool {
		return false;
	}

	public function getTargetEntityModel(): EntityModel {
		throw new IllegalStateException('EntityProperty contains no target EntityModel: ' . $this);
	}

	public function hasEmbeddedEntityPropertyCollection(): bool {
		return false;
	}

	public function getEmbeddedEntityPropertyCollection(): EntityPropertyCollection {
		throw new IllegalStateException('EntityProperty contains no target EntityPropertyCollection: ' . $this);
	}

	function getEmbeddedCascadeEntityObj(mixed $entityObj): mixed {
		return null;
	}

	function ensureInit(): void {

	}

	function prepareSupplyJob(SupplyJob $supplyJob, mixed $value, ?ValueHash $valueHash, ?ValueHash $oldValueHash): void {
	}

	function cascade(mixed $value, int $cascadeType, CascadeOperation $cascadeOperation): void {
	}

	public function __toString(): string {
		return (new \ReflectionClass($this))->getShortName() . ' [' . $this->accessProxy . ']';
	}
}
