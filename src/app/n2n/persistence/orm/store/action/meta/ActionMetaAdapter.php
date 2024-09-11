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
namespace n2n\persistence\orm\store\action\meta;

use n2n\persistence\orm\EntityDataException;
use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\property\EntityProperty;
use n2n\util\type\ArgUtils;

abstract class ActionMetaAdapter implements ActionMeta {
	private $entityModel;
	private $idProperty;
	private $idColumnName;
	private $idGenerated;
	private $sequenceName;
	private $idRawValue;
	/**
	 * @var EntityProperty[]
	 */
	private array $changedEntityProperties = [];

	public function __construct(EntityModel $entityModel) {
		$this->entityModel = $entityModel;
		$idDef = $entityModel->getIdDef();
		$this->idProperty = $idDef->getEntityProperty();
		$this->idColumnName = $this->idProperty->getColumnName();
		$this->idGenerated = $idDef->isGenerated();
		$this->sequenceName = $idDef->getSequenceName();
	}
	
	public function setIdGenerated($idGenerated) {
		$this->idGenerated = $idGenerated;
	}
	
	public function isIdGenerated() {
		return $this->idGenerated;
	}
	
	public function setSequenceName($sequenceName) {
		$this->sequenceName = $sequenceName;
	}
	
	public function getSequenceName() {
		return $this->sequenceName;
	}

	public function getEntityModel() {
		return $this->entityModel;
	}

	public function getIdColumnName() {
		return $this->idColumnName;
	}
	
	public function setIdColumnName($idColumnName) {
		$this->idColumnName = $idColumnName;
	}
	
	public function getIdRawValue() {
		return $this->idRawValue;
	}

	public function setIdRawValue($idRawValue, bool $assign = false) {
		$this->idRawValue = $idRawValue;
		if ($assign) {
			$this->assignRawValue($this->entityModel, $this->idColumnName, $idRawValue, true, null, $this->idProperty);
		}
	}

	public function getEntityIdRawValue() {
		return $this->getIdRawValue();
	}
	
	
	
// 	public function removeId() {
// 		unset($this->id);
// 		$this->rawDataMap->offsetUnset($this->idColumnName);
// 		$this->unassignRawValue($entityModel, $this->idColumnName, true);
// 	}
	
// 	public function hasObjectId() {
// 		return $this->hasId();
// 	}

	public function setRawValue(EntityModel $entityModel, string $columnName, $rawValue, ?int $pdoDataType,
			?EntityProperty $entityProperty): void {
		if ($columnName != $this->idColumnName) {
			$this->assignRawValue($entityModel, $columnName, $rawValue, false, $pdoDataType, $entityProperty);
			return;
		}
		
		if ($this->idRawValue !== null && $this->idRawValue !== $rawValue) {
			throw new EntityDataException('Entity id changed for ' . $this->entityModel->getClass()->getName()
					. ' (id: ' . $this->idRawValue . ' new id: ' . $rawValue . ')');
		}

		ArgUtils::assertTrue($this->idProperty === $entityProperty);
		$this->setIdRawValue($rawValue, true);
	}
	
	public function removeRawValue(EntityModel $entityModel, string $columnName): void {
		if ($columnName == $this->idColumnName) {
			$this->removeId();
			return;
		}
		
		$this->unassignRawValue($entityModel, $columnName, false);
	}
	
	public function isEmpty() {
		foreach ($this->getItems() as $item) {
			if (!$item->isEmpty()) return false;
		}
		
		return true;
	}

	protected abstract function assignRawValue(EntityModel $entityModel, $columnName, $rawValue, $isId, ?int $pdoDataType, ?EntityProperty $entityProperty);

	protected abstract function unassignRawValue(EntityModel $entityModel, $columnName, $isId);

	function unmarkAllChanges(): void {
		$this->changedEntityProperties = [];
	}

	function markChange(EntityProperty $entityProperty): void {
		ArgUtils::assertTrue($this->entityModel->containsEntityProperty($entityProperty));

		$this->changedEntityProperties[$entityProperty->toPropertyString()] = $entityProperty;
	}

	function unmarkChange(EntityProperty $entityProperty): void {
		unset($this->changedEntityProperties[$entityProperty->toPropertyString()]);
	}

	function getMarkedEntityProperties(): array {
		return $this->changedEntityProperties;
	}

}
