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
namespace n2n\persistence\orm;

use n2n\persistence\orm\store\action\meta\ActionMeta;
use n2n\util\type\ArgUtils;
use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\criteria\item\CrIt;
use n2n\persistence\orm\property\EntityProperty;
use n2n\persistence\orm\model\UnknownEntityPropertyException;
use n2n\persistence\orm\store\action\meta\ActionMetaUtil;
use n2n\util\ex\IllegalStateException;

class LifecycleEvent {
	const PRE_PERSIST = '_prePersist';
	const POST_PERSIST = '_postPersist';
	const PRE_REMOVE = '_preRemove';
	const POST_REMOVE = '_postRemove';
	const PRE_UPDATE = '_preUpdate';
	const POST_UPDATE = '_postUpdate';
	const POST_LOAD = '_postLoad';
	
	protected $type;
	protected $entity;
	protected $id;
		
	public function __construct($type, $entity, protected readonly EntityModel $entityModel, mixed $id,
			private ?ActionMeta $actionMeta = null) {
		$this->type = $type;
		$this->entity = $entity;
		$this->id = $id;
		ArgUtils::assertTrue(($type === self::POST_LOAD) !== ($actionMeta !== null));
	}
	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}
	/**
	 * @return object
	 */
	public function getEntityObj() {
		return $this->entity;
	}
	/**
	 * @return \n2n\persistence\orm\model\EntityModel
	 */
	public function getEntityModel() {
		return $this->entityModel;
	}
	/**
	 * @return mixed $id
	 */
	public function getId() {
		return $this->id;
	}
	
	public static function getTypes() {
		return array(self::PRE_PERSIST, self::POST_PERSIST, self::PRE_REMOVE, self::POST_REMOVE, 
				self::PRE_UPDATE, self::POST_UPDATE, self::POST_LOAD);
	}

	private function determineEntityProperty(string $propertyExpression): EntityProperty {
		IllegalStateException::assertTrue($this->actionMeta !== null, 'Event of type ' . $this->type
				. ' can not have changes.');

		$criteriaProperty = CrIt::p($propertyExpression);
		$propertyNames = $criteriaProperty->getPropertyNames();

		$entityPropertyCollection = $this->entityModel;
		$entityProperty = null;
		while (null !== ($propertyName = array_shift($propertyNames))) {
			if ($entityPropertyCollection === null) {
				throw new UnknownEntityPropertyException('Unknown entity property "' . $criteriaProperty
						. '". EntityProperty ' . $entityProperty->toPropertyString() . ' has embedded children.');
			}

			$entityProperty = $entityPropertyCollection->getLevelEntityPropertyByName($propertyName);
			if ($entityProperty->hasEmbeddedEntityPropertyCollection()) {
				$entityPropertyCollection = $entityProperty->getEmbeddedEntityPropertyCollection();
			}
		}

		return $entityProperty;
	}

	function containsChangesFor(string $propertyExpression): bool {
		$entityProperty = $this->determineEntityProperty($propertyExpression);

		return (new ActionMetaUtil($this->actionMeta))->containsChangesFor($entityProperty);
	}

	function containsChangesForAnyBut(string $propertyExpression): bool {
		$entityProperty = $this->determineEntityProperty($propertyExpression);

		return (new ActionMetaUtil($this->actionMeta))->containsChangesForAnyBut($entityProperty);
	}
}
