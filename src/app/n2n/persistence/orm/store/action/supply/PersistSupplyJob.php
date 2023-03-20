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
namespace n2n\persistence\orm\store\action\supply;

use n2n\persistence\orm\store\action\PersistAction;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\store\PersistenceOperationException;
use n2n\persistence\orm\property\CascadableEntityProperty;
use n2n\persistence\orm\store\ValueHashCol;
use n2n\persistence\orm\store\ValueHash;
use n2n\persistence\orm\store\ValueHashColFactory;

class PersistSupplyJob extends SupplyJobAdapter {
	private $valueHashCol = null;
	private $prepared = false;

	public function __construct(PersistAction $persistAction, ValueHashCol $oldValueHashCol = null) {
		parent::__construct($persistAction, $oldValueHashCol);
	}

	public function getPersistAction() {
		return $this->entityAction;
	}
	
	public function isInsert() {
		return $this->entityAction->isNew();
	}
	
	public function isUpdate() {
		return !$this->entityAction->isNew();
	}
	
	public function isRemove() {
		return false;
	}

	/**
	 * @param ValueHashCol|null $valueHashCol
	 */
	public function setValueHashCol(?ValueHashCol $valueHashCol) {
		$this->valueHashCol = $valueHashCol;
	}

	/**
	 * @return \n2n\persistence\orm\store\ValueHashCol|null
	 */
	public function getValueHashCol() {
		return $this->valueHashCol;
	}
	
	/**
	 * @param string $propertyString
	 * @return ValueHash
	 */
	private function getValueHash($propertyString) {
		IllegalStateException::assertTrue($this->valueHashCol !== null);
		return $this->valueHashCol->getValueHash($propertyString);
	}

	public function prepare() {
// 		parent::prepare();
		
		if ($this->isDisabled()) return;

		$new = $this->isInsert();
		
		foreach ($this->entityAction->getEntityModel()->getEntityProperties() as $entityProperty) {
			if (!($entityProperty instanceof CascadableEntityProperty)) continue;

			$propertyString = $entityProperty->toPropertyString();
			
			
			$oldValueHash = null;
			if (!$new) {
				$oldValueHash = $this->getOldValueHash($propertyString);
				if ($oldValueHash->matches($this->getValueHash($propertyString))) continue;
			}
		
			$entityProperty->prepareSupplyJob($this, $this->values[$propertyString], $oldValueHash);
		}
	}

	private function validateId() {
		if (null !== $this->entityAction->getId()) return;

		$idDef = $this->entityAction->getEntityModel()->getIdDef();
		if (!$idDef->isGenerated()) {
			throw new PersistenceOperationException('Id property '
					. $idDef->getEntityProperty()->toPropertyString()
					. ' must contain a non-null value because it will not be generated by the database.');
		}
	}

	public function execute() {
		if ($this->isDisabled()) return;

		IllegalStateException::assertTrue($this->init);
		
		$this->validateId();

		$entityModel = $this->entityAction->getEntityModel();
		$idDef = $entityModel->getIdDef();
		$idPropertyString = $idDef->getEntityProperty()->toPropertyString();
		foreach ($entityModel->getEntityProperties() as $property) {
			$propertyString = $property->toPropertyString();
			if ($idPropertyString === $propertyString && ($idDef->isGenerated() || !$this->entityAction->isNew())) {
				continue;
			}

			$oldValueHash = null;
			$valueHash = $this->getValueHash($propertyString);
			if (!$this->entityAction->isNew()) {
				$oldValueHash = $this->getOldValueHash($propertyString);
				if ($oldValueHash->matches($valueHash)) continue;
			}

			$property->supplyPersistAction($this->entityAction, $this->getValue($propertyString), $valueHash, $oldValueHash);
		}

//		foreach ($entityModel->getActionDependencies() as $actionDependency) {
//			$actionDependency->persistActionSupplied($this->entityAction);
//		}
		
		$that = $this;
		$this->entityAction->executeAtEnd(function () use ($that) {
			$em = $that->getActionQueue()->getEntityManager();
			$entityModel = $this->entityAction->getEntityModel();
			
			if ($this->entityAction->isNew() && $entityModel->getIdDef()->isGenerated()) {
				$idEntityProperty = $this->entityAction->getEntityModel()->getIdDef()->getEntityProperty();
				ValueHashColFactory::updateId($idEntityProperty, $this->entityAction->getId(), $that->valueHashCol, $em);
			}
			
			$em->getPersistenceContext()
					->updateValueHashes($that->entityAction->getEntityObj(), $that->valueHashCol);
			
		});
	}
}
