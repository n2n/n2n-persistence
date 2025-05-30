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
namespace n2n\persistence\orm\property;

use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\query\QueryState;
use n2n\persistence\orm\query\from\MetaTreePoint;
use n2n\persistence\orm\store\action\PersistAction;
use n2n\persistence\orm\store\action\RemoveAction;
use n2n\persistence\orm\store\operation\MergeOperation;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\model\EntityPropertyCollection;
use n2n\persistence\orm\store\ValueHash;
use n2n\util\ex\UnsupportedOperationException;
use n2n\persistence\orm\query\select\Selection;
use n2n\persistence\orm\store\action\supply\SupplyJob;
use n2n\persistence\orm\store\operation\CascadeOperation;
use n2n\util\magic\MagicContext;
use n2n\reflection\property\PropertyAccessException;
use n2n\util\type\ValueIncompatibleWithConstraintsException;
use n2n\persistence\orm\CorruptedDataException;

interface EntityProperty {
	/**
	 * @return string 
	 */
	public function getName();
	
	/**
	 *  @return \n2n\persistence\orm\model\EntityModel
	 *  @throws \n2n\util\ex\IllegalStateException if EntityModel not initialized
	 */
	public function getEntityModel();
	
	/**
	 * @param EntityModel $entityModel
	 */
	public function setEntityModel(EntityModel $entityModel);
	
	/**
	 * @return EntityProperty 
	 */
	public function getParent();
	
	/**
	 * @param EntityProperty $parent
	 */
	public function setParent(EntityProperty $parent);
	
	/**
	 * @param object $object
	 * @return mixed 
	 */
	public function readValue(object $object): mixed;
	
	/**
	 * @param object $object
	 * @param mixed $value
	 * @throws CorruptedDataException
	 */
	public function writeValue(object $object, mixed $value): void;
	
	/**
	 * @param MetaTreePoint $metaTreePoint
	 * @param QueryState $queryState
	 * @return Selection
	 * @throws UnsupportedOperationException if EntityProperty cannot be selected
	 */
	public function createSelection(MetaTreePoint $metaTreePoint, QueryState $queryState): Selection;
	
	/**
	 * @param mixed $value
	 * @param bool $sameEntity
	 * @param MergeOperation $mergeOperation
	 * @return mixed
	 */
	public function mergeValue(mixed $value, bool $sameEntity, MergeOperation $mergeOperation): mixed;

	public function prepareSupplyJob(SupplyJob $supplyJob, mixed $value, ?ValueHash $valueHash, ?ValueHash $oldValueHash): void;

	public function cascade(mixed $value, int $cascadeType, CascadeOperation $cascadeOperation): void;

	/**
	 * @param PersistAction $persistAction
	 * @param $value
	 * @param ValueHash $valueHash
	 * @param ValueHash|null $oldValueHash
	 */
	public function supplyPersistAction(PersistAction $persistAction, $value, ValueHash $valueHash, ?ValueHash $oldValueHash): void;
	
	/**
	 * @param mixed $mappedValue
	 * @param RemoveAction $removingJob
	 */
	public function supplyRemoveAction(RemoveAction $removeAction, $value, ValueHash $oldValueHash);
	
	/**
	 * @param mixed $value
	 * @return mixed
	 */
	public function createValueHash(mixed $value, MagicContext $magicContext): ValueHash;
	
	/**
	 * @param mixed $mappedValue
	 * @return mixed
	 */
	public function copy($value);
	
	/**
	 * @param mixed $obj
	 * @return boolean
	 */
	public function equals($obj);
	
	/**
	 * @return string 
	 */
	public function toPropertyString();
	
	public function hasEmbeddedEntityPropertyCollection(): bool;
	
	public function getEmbeddedEntityPropertyCollection(): EntityPropertyCollection;

	public function getEmbeddedCascadeEntityObj(mixed $entityObj): mixed;
	
	public function hasTargetEntityModel(): bool;
	
	public function getTargetEntityModel(): EntityModel;

	function ensureInit(): void;
	
	public function __toString(): string;
}
