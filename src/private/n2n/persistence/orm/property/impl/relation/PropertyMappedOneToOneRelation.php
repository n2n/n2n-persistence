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
use n2n\persistence\orm\FetchType;
use n2n\persistence\orm\property\impl\relation\selection\ToOneRelationSelection;
use n2n\persistence\orm\property\impl\relation\util\ToOneValueHasher;
use n2n\persistence\orm\property\impl\relation\compare\IdColumnComparableDecorator;
use n2n\persistence\orm\store\action\supply\SupplyJob;
use n2n\persistence\orm\property\impl\relation\util\ToOneUtils;
use n2n\persistence\orm\property\EntityProperty;
use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\query\from\TreePath;

class PropertyMappedOneToOneRelation extends MappedRelation implements ToOneRelation  {
	private $toOneUtils;
	
	public function __construct(EntityProperty $entityProperty, EntityModel $targetEntityModel, 
			EntityProperty $targetEntityProperty) {
		parent::__construct($entityProperty, $targetEntityModel, $targetEntityProperty);
		$this->toOneUtils = new ToOneUtils($this, false);
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\impl\relation\ToOneRelation::createRepresentingQueryItem()
	 */
	public function createRepresentingQueryItem(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		return $metaTreePoint->requestPropertyRepresentableQueryItem($this->createTargetIdTreePath());
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\impl\relation\ToOneRelation::createColumnComparable()
	 */
	public function createColumnComparable(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		return new IdColumnComparableDecorator(
				$metaTreePoint->requestPropertyComparisonStrategy($this->createTargetIdTreePath()), 
				$this->targetEntityModel);
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\impl\relation\Relation::createSelection()
	 */
	public function createSelection(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		$targetTreePath = $this->createTargetIdTreePath();
		
		$idSelection = $metaTreePoint->requestCustomPropertyJoinTreePoint($this->entityProperty, false)
				->requestPropertySelection(new TreePath(array($this->targetEntityModel->getIdDef()
						->getPropertyName())));
	
		$entitySelection = new ToOneRelationSelection($this->targetEntityModel, $idSelection, $queryState);
		$entitySelection->setLazy($this->fetchType == FetchType::LAZY);
		return $entitySelection;
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\impl\relation\Relation::buildValueHash()
	 */
	public function buildValueHash($value, EntityManager $em) {
		return ToOneValueHasher::createFromEntityModel($this->targetEntityModel)
				->createValueHash($value);
	}
	/**
	 * @param unknown $value
	 * @param unknown $valueHash
	 * @param SupplyJob $supplyJob
	 */
	public function prepareSupplyJob($value, $valueHash, SupplyJob $supplyJob) {
		$this->toOneUtils->prepareSupplyJob($value, $valueHash, $supplyJob);
	}
}
