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
namespace n2n\persistence\orm\property\impl\relation\util;

use n2n\persistence\orm\property\BasicEntityProperty;
use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\property\impl\relation\selection\ArrayObjectProxy;
use n2n\reflection\ArgUtils;
use n2n\util\col\ArrayUtils;

class ToManyValueHasher {
	const PROXY_KEYWORD = 'proxy';
	private $targetIdProperty;

	public function __construct(BasicEntityProperty $targetIdProperty) {
		$this->targetIdProperty = $targetIdProperty;
	}

	public static function createFromEntityModel(EntityModel $entityModel) {
		return new ToManyValueHasher($entityModel->getIdDef()->getEntityProperty());
	}

	public static function extractIdReps($valueHash) {
		if (!is_array($valueHash)) return array();
		
		$idReps = array();
		foreach ($valueHash as $idRep) {
			if ($idRep !== null) {
				$idReps[$idRep] = $idRep;
			}
		}
		return $idReps;
	}

	public function createValueHash($value) {
		if ($value === null) return $value;

		if ($value instanceof ArrayObjectProxy && !$value->isInitialized()) {
			return $value->getId();
		}

		$valueHash = self::createValueHashFromEntitis($value);
		if ($value instanceof ArrayObjectProxy && $value->getLoadedValueHash() === $valueHash) {
			return $value->getId();
		}
		
		return $valueHash;
	}
	
	public function createValueHashFromEntitis($entities) {
		ArgUtils::assertTrue(ArrayUtils::isArrayLike($entities));
		$entityIdReps = array();
		foreach ($entities as $key => $entity) {
			$id = $this->targetIdProperty->readValue($entity);
			if ($id === null) {
				$entityIdReps[$key] = null;
			} else {
				$entityIdReps[$key] = $this->targetIdProperty->valueToRep($id);
			}
		}
		return $entityIdReps;
	}


// 	public static function isUntouchedProxy($value, $valueHash) {
// 		return $value instanceof ArrayObjectProxy && !$value->isInitialized()
// 				&& $valueHash === $value->getId();
// 	}

	public static function checkForUntouchedProxy($value, &$valueHash) {
		if (!($value instanceof ArrayObjectProxy) || $valueHash !== $value->getId()) return false;

		if (!$value->isInitialized()) return true;
		
		$valueHash = $value->getLoadedValueHash();
		return false;
	}
	
	public function matches(array $entityIds, $valueHash) {
		// @todo for ArrayObjectProxy valueHahes (which are strings)
		if (!is_array($valueHash)) return false; 
		
		$vhIdReps = self::extractIdReps($valueHash);
		
		foreach ($entityIds as $entityId) {
			$entityIdRep = $this->targetIdProperty->valueToRep($entityId);
			if (!isset($vhIdReps[$entityIdRep])) return false;
			unset($vhIdReps[$entityIdRep]);
		}

		return empty($vhIdReps);
	}

	public function findOrphanIdReps(array $entityIds, $valueHash) {
		$vhIdReps = self::extractIdReps($valueHash);
		
		foreach ($entityIds as $entityId) {
			$entityIdRep = $this->targetIdProperty->valueToRep($entityId);
			if (isset($vhIdReps[$entityIdRep])) { 
				unset($vhIdReps[$entityIdRep]);
			}
		}

		return $vhIdReps;
	}
}
