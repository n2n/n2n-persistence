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
namespace n2n\persistence\orm\model;

use n2n\persistence\orm\store\action\meta\SimpleActionMeta;
use n2n\persistence\orm\store\action\meta\TablePerClassActionMeta;
use n2n\persistence\orm\store\action\meta\JoinedTableActionMeta;
use n2n\persistence\orm\store\action\meta\SingleTableActionMeta;
use n2n\persistence\orm\query\from\meta\TablePerClassTreePointMeta;
use n2n\persistence\orm\query\from\meta\SingleTableTreePointMeta;
use n2n\persistence\orm\query\from\meta\JoinedTreePointMeta;
use n2n\persistence\orm\query\from\meta\SimpleTreePointMeta;
use n2n\persistence\orm\property\EntityProperty;
use n2n\persistence\orm\query\QueryState;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\property\IdDef;
use n2n\persistence\orm\InheritanceType;
use n2n\util\type\ArgUtils;
use n2n\util\type\TypeUtils;

class EntityModel implements EntityPropertyCollection {	
	private $class;
	private $tableName;
	private $inheritanceType;
	private $discriminatorColumnName;
	private $discriminatorValue;
	
	private $lifecylceMethods = array();
	private $entityListenerClasses = array();
	
	private $idDef;
	private $properties = array();
	
	private $superEntityModel;
	private $subEntityModels = array();
	private $actionDependencies = array();
	
	public function __construct(\ReflectionClass $class, EntityModel $superEntityModel = null) {
		$this->class = $class;
		$this->superEntityModel = $superEntityModel;
		
		if ($superEntityModel !== null) {
			$superEntityModel->addSubEntityModel($this);
		}
	}
	/**
	 * @return \n2n\persistence\orm\model\EntityModel
	 */
	public function getSupremeEntityModel() {
		$entityModel = $this;
		do {
			$supremeEntityModel = $entityModel;
		} while (null !== ($entityModel = $supremeEntityModel->getSuperEntityModel()));
		
		return $supremeEntityModel;
	}
	/**
	 * @return boolean
	 */
	public function isAbstract() {
		return $this->class->isAbstract();
	}
	/**
	 * @return boolean
	 */
	public function hasSuperEntityModel() {
		return $this->superEntityModel !== null;
	}
	/**
	 * @return EntityModel returns null if EntityModel has no super EntityModel
	 */
	public function getSuperEntityModel() {
		return $this->superEntityModel;
	}
	
	private function ensureNoSuperEntity() {
		if (!$this->hasSuperEntityModel()) return;
		
		throw new IllegalStateException('EntityModel for ' . $this->class->getName() 
				. ' has super EntityModel');
	}
	
	public function setInheritanceType($inheritanceType) {
		$this->ensureNoSuperEntity();
		$this->inheritanceType = $inheritanceType;
	}
	
	public function getInheritanceType() {
		$this->ensureInit();

		if ($this->superEntityModel !== null) {
			return $this->superEntityModel->getInheritanceType();
		}
		return $this->inheritanceType;
	}
	
	public function setDiscriminatorColumnName($discriminatorColumnName) {
		$this->ensureNoSuperEntity();
		$this->discriminatorColumnName = $discriminatorColumnName;
	}
	
	public function getDiscriminatorColumnName() {
		$this->ensureInit();

		if ($this->superEntityModel !== null) {
			return $this->superEntityModel->getDiscriminatorColumnName();
		}
		return $this->discriminatorColumnName;
	}
	
	public function setDiscriminatorValue($discriminatorValue) {
		$this->discriminatorValue = $discriminatorValue;
	}
	
	public function getDiscriminatorValue() {
		$this->ensureInit();

		return $this->discriminatorValue;
	}
	
	/**
	 * @param IdDef $idDef
	 */
	public function setIdDef(IdDef $idDef) {
		$this->ensureNoSuperEntity();
		$this->idDef = $idDef;
	}
	
	/**
	 * @return IdDef
	 */
	public function getIdDef() {
		$this->ensureInit();

		if ($this->superEntityModel !== null) {
			return $this->superEntityModel->getIdDef();
		}
		return $this->idDef;
	}
	/**
	 * @return \ReflectionClass
	 */
	public function getClass(): \ReflectionClass {
		return $this->class;
	}
	
	public function setTableName($tableName) {
		$this->tableName = $tableName;
	}
	
	public function getTableName() {
		$this->ensureInit();

		return $this->tableName;
	}
	
	public function getAllSuperEntityModels($includeSelf = false) {
		$entityModel = $this;
		
		$superEntityModels = array();
		if ($includeSelf) {
			$superEntityModels[$entityModel->getClass()->getName()] = $entityModel;
		}
		
		while (null !== ($entityModel = $entityModel->getSuperEntityModel())) {
			$superEntityModels[$entityModel->getClass()->getName()] = $entityModel;
		}
		return $superEntityModels;
	}
	
	protected function addSubEntityModel(EntityModel $subEntityModel) {
		IllegalStateException::assertTrue($this->subEntityModelsPresent);
		$this->subEntityModels[$subEntityModel->getClass()->getName()] = $subEntityModel;
	}

	private ?\Closure $entityModelAccessCallback = null;

	function setEntityModelAccessCallback(\Closure $closure) {
		$this->entityModelAccessCallback = $closure;
	}

	function ensureInit(): void {
		if ($this->entityModelAccessCallback === null) {
			return;
		}

		$closure = $this->entityModelAccessCallback;
		$this->entityModelAccessCallback = null;
		try {
			$closure();
		} catch (\Throwable $e) {
			$this->entityModelAccessCallback = $closure;
			throw $e;
		}
	}

	private bool $subEntityModelsPresent = false;
	private ?\Closure $subEntityModelsAccessCallback = null;

	function setSubEntityModelsAccessCallback(bool $subEntityModelsPresent, \Closure $closure) {
		$this->subEntityModelsPresent = $subEntityModelsPresent;
		$this->subEntityModelsAccessCallback = $closure;
	}

	function ensureSubEntityModelsInit(): void {
		if ($this->subEntityModelsAccessCallback === null) {
			return;
		}

		$callback = $this->subEntityModelsAccessCallback;
		$this->subEntityModelsAccessCallback = null;

		try {
			$callback();
		} catch (\Throwable $e) {
			$this->subEntityModelsAccessCallback = $callback;
			throw $e;
		}
	}
	
	public function hasSubEntityModels() {
		return $this->subEntityModelsPresent;
	}
	
	/**
	 * @return EntityModel[]
	 */
	public function getSubEntityModels() {
		$this->ensureSubEntityModelsInit();

		return $this->subEntityModels;
	}
	
	public function getAllSubEntityModels() {
		$subEntityModels = $this->getSubEntityModels();
		foreach ($subEntityModels as $subEntityModel) {
			$subEntityModels = array_merge($subEntityModels, $subEntityModel->getAllSubEntityModels()); 
		}
		return $subEntityModels;
	}
	
	public function addEntityProperty(EntityProperty $property) {
		$this->properties[$property->getName()] = $property;
	}
	
	public function containsEntityPropertyName($name) {
		$this->ensureInit();

		if  (isset($this->properties[$name])) return true;
	
		return $this->superEntityModel !== null
				&& $this->superEntityModel->containsEntityPropertyName($name);
	}
	/**
	 * @return EntityProperty[] key is NOT the property name
	 */
	public function getEntityProperties() {
		$this->ensureInit();

		if ($this->superEntityModel === null) {
			return array_values($this->properties);
		}
		
		return array_merge($this->superEntityModel->getEntityProperties(), array_values($this->properties));
	}
	
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\model\EntityPropertyCollection::getEntityPropertyByName()
	 */
	public function getEntityPropertyByName($name) {
		$this->ensureInit();

		if (!$this->containsEntityPropertyName($name)) {
			throw new UnknownEntityPropertyException('Unkown entity property: ' . $this->class->getName() 
					. '::$' . $name);
		}
	
		if (isset($this->properties[$name])) return $this->properties[$name];
	
		return $this->superEntityModel->getEntityPropertyByName($name);
	}
	
	public function containsLevelEntityPropertyName(string $name): bool {
		$this->ensureInit();

		return isset($this->properties[$name]);
	}

	/**
	 * @param string $name
	 * @return EntityProperty
	 * @throws UnknownEntityPropertyException
	 */
	public function getLevelEntityPropertyByName(string $name): EntityProperty {
		$this->ensureInit();

		if (isset($this->properties[$name])) return $this->properties[$name];
		
		$superEntityProperty = $this->getEntityPropertyByName($name);
		
		throw new UnknownEntityPropertyException('Unkown entity property ' 
				. TypeUtils::prettyClassPropName($this->class, $name)
				. '. Requested entity property is defined in super class: ' 
				. TypeUtils::prettyClassPropName($superEntityProperty->getEntityModel()->getClass(), $name));
	}

	/**
	 * @return EntityProperty[]
	 */
	public function getLevelEntityProperties(): array {
		$this->ensureInit();

		return $this->properties;
	}
	
	public function getAllEntityProperties() {
		$properties = $this->getEntityProperties();
		foreach ($this->getSubEntityModels() as $subEntityModel) {
			$properties = array_merge($properties, $subEntityModel->getAllEntityProperties());
		}
		return $properties;
	}	
	
	public function getLifecycleMethodsByEventType($eventType) {
		$this->ensureInit();

		$methods = array();
		if (isset($this->lifecylceMethods[$eventType])) {
			$methods[] = $this->lifecylceMethods[$eventType];
		}
		
		if ($this->superEntityModel === null) {
			return $methods;
		}
		
		return array_merge($this->superEntityModel->getLifecycleMethodsByEventType($eventType), $methods);
	}
	
	public function addLifecycleMethod($eventType, \ReflectionMethod $lifecycleMethod) {
		$this->lifecylceMethods[$eventType] = $lifecycleMethod;
	}
	
	public function setEntityListenerClasses(array $entityListenerClasses) {
		ArgUtils::valArray($entityListenerClasses, '\ReflectionClass');
		$this->entityListenerClasses = $entityListenerClasses;
	}
	
	public function getEntityListenerClasses() {
		$this->ensureInit();

		return $this->entityListenerClasses;
	}
// 	public function removeLifecycleMethodByEventType($eventType) {
// 		unset($this->lifecylceMethods[$eventType]);
		
// 		if ($this->superEntityModel !== null) {
// 			$this->superEntityModel->removeLifecycleMethodByEventType($eventType);
// 		}
// 	}
	
	/**
	 * @param QueryState $queryState
	 * @return \n2n\persistence\orm\query\from\meta\TreePointMeta
	 */
	public function createTreePointMeta(QueryState $queryState) {
		switch ($this->getInheritanceType()) {
			case InheritanceType::SINGLE_TABLE:
				return new SingleTableTreePointMeta($queryState, $this);
			case InheritanceType::JOINED:
				return new JoinedTreePointMeta($queryState, $this);
			case InheritanceType::TABLE_PER_CLASS:
				return new TablePerClassTreePointMeta($queryState, $this);
			default:
				return new SimpleTreePointMeta($queryState, $this);
		}
	}
	
	public function createActionMeta(bool $new) {
		switch ($this->getInheritanceType()) {
			case InheritanceType::SINGLE_TABLE:
				return new SingleTableActionMeta($this, $new);
			case InheritanceType::JOINED:
				return new JoinedTableActionMeta($this);
			case InheritanceType::TABLE_PER_CLASS:
				return new TablePerClassActionMeta($this);
			default:
				return new SimpleActionMeta($this);
		}
	}
	
	public function copy($fromEntity, $toEntity) {
		ArgUtils::valObject($fromEntity, false, 'fromEntity');
		ArgUtils::valObject($toEntity, false, 'toEntity');
		
		foreach ($this->getEntityProperties() as $entityProperty) {
			$entityProperty->writeValue($toEntity, $entityProperty->copy($entityProperty->readValue($fromEntity)));
		}
	}
	
	public function equals($obj) {
		return $obj instanceof EntityModel && $this->getClass()->getName() == $obj->getClass()->getName();
	}

	private ?\Closure $actionDependenciesAccessCallback = null;

	function setActionDependenciesAccessCallback(\Closure $closure) {
		$this->actionDependenciesAccessCallback = $closure;
	}

	function ensureActionDependenciesInit(): void {
		if ($this->actionDependenciesAccessCallback === null) {
			return;
		}

		$closure = $this->actionDependenciesAccessCallback;
		$this->actionDependenciesAccessCallback = null;
		try {
			$closure();
		} catch (\Throwable $e) {
			$this->actionDependenciesAccessCallback = $closure;
			throw $e;
		}
	}

	public function registerActionDependency(ActionDependency $actionDependency) {
		$this->actionDependencies[spl_object_hash($actionDependency)]	= $actionDependency;
	}

	/**
	 * @return ActionDependency[]
	 */
	public function getActionDependencies(): array {
		$this->ensureActionDependenciesInit();
		return $this->actionDependencies;
	}
}
