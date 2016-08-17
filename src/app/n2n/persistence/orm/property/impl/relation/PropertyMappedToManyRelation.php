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
namespace n2n\persistence\orm\property\impl\relation;

use n2n\persistence\orm\query\from\MetaTreePoint;
use n2n\persistence\orm\query\QueryState;
use n2n\persistence\orm\FetchType;
use n2n\persistence\orm\property\impl\relation\selection\ToManyRelationSelection;
use n2n\persistence\orm\query\from\TreePath;
use n2n\persistence\orm\property\impl\relation\util\ToManyValueHasher;
use n2n\persistence\orm\property\impl\relation\compare\ToManyCustomComparable;
use n2n\persistence\orm\property\impl\relation\util\OrphanRemover;
use n2n\persistence\orm\store\action\supply\SupplyJob;
use n2n\util\ex\NotYetImplementedException;
use n2n\reflection\ArgUtils;
use n2n\util\col\ArrayUtils;
use n2n\persistence\orm\property\impl\relation\selection\ArrayObjectProxy;
use n2n\persistence\orm\property\impl\relation\util\ToManyUtils;
use n2n\persistence\orm\property\EntityProperty;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\model\EntityModel;

class PropertyMappedToManyRelation extends MappedRelation implements ToManyRelation {
	private $toManyUtils;
	private $orderDirectives = array();

	public function __construct(EntityProperty $entityProperty, EntityModel $targetEntityModel, 
			EntityProperty $targetEntityProperty) {
		parent::__construct($entityProperty, $targetEntityModel, $targetEntityProperty);
		$this->toManyUtils = new ToManyUtils($this, false);
	}
	
	public function getOrderDirectives() {
		return $this->orderDirectives;
	}
	
	public function setOrderDirectives(array $orderDirectives) {
		$this->orderDirectives = $orderDirectives;
	}
	
	public function createCustomComparable(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		$toManyQueryItemFactory = $this->getMasterRelation()->createInverseJoinTableToManyQueryItemFactory();
		
		return new ToManyCustomComparable($metaTreePoint, $this->targetEntityModel,
				$this->createTargetIdTreePath(), $toManyQueryItemFactory, $queryState);
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\impl\relation\Relation::createSelection()
	 */
	public function createSelection(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		$idSelection = $metaTreePoint->requestPropertySelection(new TreePath(
				array($this->entityModel->getIdDef()->getPropertyName())));
		$toManyLoader = $this->getMasterRelation()->createInverseToManyLoader($this->targetEntityModel, $queryState);
		$toManyLoader->setOrderDirectives($this->orderDirectives);
		
		$toManySelection = new ToManyRelationSelection($idSelection, $toManyLoader, 
				$this->getTargetIdEntityProperty());
		$toManySelection->setLazy($this->fetchType == FetchType::LAZY);
		return $toManySelection;
	}

	public function buildValueHash($value, EntityManager $em) {
		return ToManyValueHasher::createFromEntityModel($this->targetEntityModel)
				->createValueHash($value);
	}

	public function prepareSupplyJob($value, $oldValueHash, SupplyJob $supplyJob) {
		$this->toManyUtils->prepareSupplyJob($value, $oldValueHash, $supplyJob);
// 		if (!$this->orphanRemoval || $supplyJob->isInsert()) return;
	
// 		if (ToManyValueHasher::checkForUntouchedProxy($value, $oldValueHash)) {
// 			if (!$supplyJob->isRemove()) return;
			
// 			ArgUtils::assertTrue($value instanceof ArrayObjectProxy);
// 			$value->initialize();
// 			$oldValueHash = $value->getLoadedValueHash();
// 		}
		
// // 		if (ToManyValueHasher::checkForProxy($oldValueHash)) {
// // 			throw new NotYetImplementedException('PersistenceContext must be able to store ArrayObjectProxy by valueHash');
// // 		}
	
// 		$orphanRemover = new OrphanRemover($supplyJob, $this->targetEntityModel, $this->actionMarker);
		
// 		if (!$supplyJob->isRemove()) {
// 			ArgUtils::assertTrue(ArrayUtils::isArrayLike($value));
// 			foreach ($value as $entity) {
// 				$orphanRemover->releaseCandiate($entity);
// 			}
// 		}
		
// 		$orphanRemover->removeByIdReps(ToManyValueHasher::extractIdReps($oldValueHash));
	}
}
