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
namespace n2n\persistence\orm\store\operation;

use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\property\CascadableEntityProperty;
use n2n\persistence\orm\store\PersistenceOperationException;
use n2n\persistence\orm\CascadeType;
use n2n\util\type\ArgUtils;
use n2n\persistence\orm\store\EntityInfo;
use n2n\persistence\orm\property\EntityProperty;
use n2n\persistence\orm\model\EntityPropertyCollection;

class OperationCascader {
	private $cascadedEntities = array();
	private $cascadeType;
	private $cascadeOperation;

	public function __construct($cascadeType, CascadeOperation $cascadeOperation) {
		$this->cascadeType = $cascadeType;
		$this->cascadeOperation = $cascadeOperation;
	}

	public function markAsCascaded($entity) {
		ArgUtils::valType($entity, 'object');
		$objHash = spl_object_hash($entity);

		if (isset($this->cascadedEntities[$objHash])) {
			return false;
		}

		$this->cascadedEntities[$objHash] = $entity;
		return true;
	}
	/**
	 * @param EntityModel $entityModel
	 * @param object $entity
	 * @throws PersistenceOperationException
	 */
	public function cascadeProperties(EntityModel|EntityPropertyCollection $entityModel, $entityObj,
			EntityProperty &$entityProperty = null) {
		try {
			$this->traverse($entityModel, $entityObj, $entityProperty);
		} catch (PersistenceOperationException $e) {
			throw new PersistenceOperationException('Failed to perform casacde on entity object: '
					. EntityInfo::buildEntityStringFromEntityObj($entityModel, $entityObj)
					. ' Reason: ' . $e->getMessage(), 0, $e);
		}
	}

	private function traverse(EntityPropertyCollection $entityPropertyCollection, $entityObj,
			EntityProperty &$entityProperty = null) {
		foreach ($entityPropertyCollection->getEntityProperties() as $entityProperty) {
			if ($entityProperty instanceof CascadableEntityProperty) {
				$this->cascade($entityProperty, $entityObj);
			}

			if ($entityProperty->hasEmbeddedEntityPropertyCollection()
					&& null !== ($subEntityObj = $entityProperty->getEmbeddedCascadeEntityObj($entityObj))) {
				$this->traverse($entityProperty->getEmbeddedEntityPropertyCollection(), $subEntityObj, $entityProperty);
			}
		}
	}

	private function cascade(CascadableEntityProperty $entityProperty, $entityObj) {
		try {
			$entityProperty->cascade($entityProperty->readValue($entityObj),
					$this->cascadeType, $this->cascadeOperation);
		} catch (PersistenceOperationException $e) {
			throw new PersistenceOperationException('Failed to cascade '
					. CascadeType::buildString($this->cascadeType) . ' to property '
					. $entityProperty->toPropertyString() . '.', 0, $e);
		}
	}
}
