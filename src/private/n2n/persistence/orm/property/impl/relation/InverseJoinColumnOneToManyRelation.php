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

use n2n\persistence\orm\query\QueryState;
use n2n\persistence\orm\query\from\MetaTreePoint;
use n2n\persistence\orm\property\impl\relation\tree\JoinColumnTreePoint;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\criteria\compare\ComparisonStrategy;
use n2n\persistence\orm\store\action\PersistAction;
use n2n\persistence\orm\property\impl\relation\selection\JoinColumnToManyLoader;
use n2n\persistence\orm\property\impl\relation\selection\ToManyRelationSelection;
use n2n\persistence\orm\store\SimpleLoaderUtils;
use n2n\persistence\orm\FetchType;
use n2n\util\ex\UnsupportedOperationException;
use n2n\persistence\orm\query\from\meta\TreePointMeta;
use n2n\persistence\orm\property\impl\relation\util\OrphanRemover;
use n2n\persistence\orm\property\impl\relation\util\ToManyValueHasher;
use n2n\persistence\orm\property\impl\relation\util\ToManyAnalyzer;
use n2n\persistence\orm\property\impl\relation\compare\IdColumnComparableDecorator;
use n2n\util\ex\NotYetImplementedException;
use n2n\persistence\orm\store\action\supply\SupplyJob;
use n2n\reflection\ArgUtils;
use n2n\util\col\ArrayUtils;
use n2n\persistence\orm\store\action\EntityAction;
use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\EntityManager;

class InverseJoinColumnOneToManyRelation extends MasterRelation implements ToManyRelation {
	private $inverseJoinColumnName;
	private $orderDirectives = array();

	public function getInverseJoinColumnName() {
		return $this->inverseJoinColumnName;
	}
	
	public function setInverseJoinColumnName($inverseJoinColumnName) {
		$this->inverseJoinColumnName = $inverseJoinColumnName;
	}
	
	public function getOrderDirectives() {
		return $this->orderDirectives;
	}
	
	public function setOrderDirectives(array $orderDirectives) {
		$this->orderDirectives = $orderDirectives;
	}
	
	public function createJoinTreePoint(TreePointMeta $treePointMeta, QueryState $queryState) {
		$idQueryColumn = $this->entityModel->getIdDef()->getEntityProperty()
				->createQueryColumn($treePointMeta);
	
		$targetTreePointMeta = $this->targetEntityModel->createTreePointMeta($queryState);
		$targetJoinQueryColumn = $targetTreePointMeta->registerColumn($this->targetEntityModel, 
				$this->inverseJoinColumnName);
		
		$treePoint = new JoinColumnTreePoint($queryState, $targetTreePointMeta);
		$treePoint->setJoinColumn($idQueryColumn);
		$treePoint->setTargetJoinColumn($targetJoinQueryColumn);
		return $treePoint;
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\impl\relation\MasterRelation::createInverseJoinTreePoint()
	 */
	public function createInverseJoinTreePoint(EntityModel $entityModel, TreePointMeta $targetTreePointMeta, QueryState $queryState) {
		$targetJoinQueryColumn = $targetTreePointMeta->registerColumn($this->targetEntityModel, 
				$this->inverseJoinColumnName);
		
		$treePointMeta = $entityModel->createTreePointMeta($queryState);
		$idQueryColumn = $this->targetEntityModel->getIdDef()->getEntityProperty()
				->createColumn($treePointMeta);
				
		$treePoint = new JoinColumnTreePoint($queryState, $treePointMeta);
		$treePoint->setJoinColumn($targetJoinQueryColumn);
		$treePoint->setTargetJoinColumn($idQueryColumn);
		return $treePoint;
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\impl\relation\Relation::createColumnComparable()
	 */
	public function createCustomComparable(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		$comparisonStargegy = $metaTreePoint->requestPropertyComparisonStrategy($this->createTargetIdTreePath())
				->getColumnComparable();
		
		IllegalStateException::assertTrue($comparisonStargegy->getType() == ComparisonStrategy::TYPE_COLUMN);
		
		$meta = $metaTreePoint->getMeta();
		return new IdColumnComparableDecorator($comparisonStargegy->getColumnComparable(),
				$this->targetEntityModel);
	}

	public function createInverseJoinTableToManyQueryItemFactory(EntityModel $entityModel) {
		throw new UnsupportedOperationException();
	}
	
	public function prepareSupplyJob($value, $oldValueHash, SupplyJob $supplyJob) {
		if (!$this->orphanRemoval || $oldValueHash === null || $supplyJob->isInsert()) return;

		if (ToManyValueHasher::checkForUntouchedProxy($value, $oldValueHash)) return;
		
		if (ToManyValueHasher::checkForProxy($oldValueHash)) {
			throw new NotYetImplementedException('PersistenceContext must be able to store ArrayObjectProxy by valueHash');
		}
	
		$orphanRemover = new OrphanRemover($supplyJob, $this->targetEntityModel, $this->actionMarker);
		
		if (!$supplyJob->isRemove()) {
			ArgUtils::assertTrue(ArrayUtils::isArrayLike($value));
			foreach ($value as $entity) {
				$orphanRemover->releaseCandiate($entity);
			}
		}
			
		$orphanRemover->removeByIdReps(ToManyValueHasher::extractIdReps($oldValueHash));
	}
	
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\impl\relation\Relation::supplyPersistAction()
	 */
	public function supplyPersistAction($value, $valueHash, PersistAction $persistAction) {
		$hasher = new ToManyValueHasher($this->targetEntityModel->getIdDef()->getEntityProperty());
		
		if (ToManyValueHasher::checkForUntouchedProxy($value, $valueHash)) return;
		
		$toManyAnalyzer = new ToManyAnalyzer($persistAction->getActionQueue());
		$toManyAnalyzer->analyze($value);
		
		if (!$toManyAnalyzer->hasPendingPersistActions()
				&& $hasher->matches($toManyAnalyzer->getEntityIds(), $valueHash)) {
			return;
		}
		
		$targetPersistActions = $toManyAnalyzer->getAllPersistActions();
		
		if ($persistAction->hasId()) {
			$this->applyPersistId($persistAction, $targetPersistActions);
			return;
		}
				
		foreach ($targetPersistActions as $targetPersistAction) {
			$targetPersistAction->addDependent($persistAction);
		}
		
		$persistAction->executeAtEnd(function () use ($persistAction, $targetPersistActions) {
			$this->applyPersistId($persistAction, $targetPersistActions);
		});
	}
	
	private function applyPersistId(PersistAction $persistAction, array $targetPersistActions) {
		$idProperty = $this->entityModel->getIdDef()->getEntityProperty();
		$idRaw = $idProperty->valueToRep($persistAction->getId());
		foreach ($targetPersistActions as $targetPersistAction) {
			$targetPersistAction->getMeta()->setRawValue($this->targetEntityModel,
					$this->inverseJoinColumnName, $idRaw);
		}
	}
// 	/* (non-PHPdoc)
// 	 * @see \n2n\persistence\orm\property\impl\relation\Relation::supplyInverseToManyRemoveAction()
// 	 */
// 	public function supplyInverseToManyRemoveAction($targetValue, $targetValueHash, RemoveAction $targetRemoveAction) {
// 		throw new UnsupportedOperationException();
// 	}
// 	/* (non-PHPdoc)
// 	 * @see \n2n\persistence\orm\property\impl\relation\Relation::supplyInverseToOneRemoveAction()
// 	 */
// 	public function supplyInverseToOneRemoveAction($targetValue, $targetValueHash, RemoveAction $targetRemoveAction) {
// 	}
	
	public function buildValueHash($value, EntityManager $em) {
		$analyzer = new ToManyValueHasher($this->targetEntityModel->getIdDef()
				->getEntityProperty());
		return $analyzer->createValueHash($value);
	}

	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\impl\relation\Relation::createSelection()
	 */
	public function createSelection(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		$idSelection = $metaTreePoint->requestPropertySelection($this->createIdTreePath());
		$idProperty = $this->entityModel->getIdDef()->getEntityProperty();
		
		$toManyLoader = new JoinColumnToManyLoader(
				new SimpleLoaderUtils($queryState->getEntityManager(), $this->targetEntityModel),
				$idProperty, $this->inverseJoinColumnName);
		$toManyLoader->setOrderDirectives($this->orderDirectives);
		
		$toManySelection = new ToManyRelationSelection($idSelection, $toManyLoader, 
				$this->getTargetIdEntityProperty());
		$toManySelection->setLazy($this->fetchType == FetchType::LAZY);
		return $toManySelection;
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\impl\relation\MasterRelation::createInverseToManyLoader()
	 */
	public function createInverseToManyLoader(EntityModel $entityModel, QueryState $queryState) {
		throw new UnsupportedOperationException();
	}
}
