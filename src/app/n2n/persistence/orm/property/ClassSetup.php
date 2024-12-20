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

use n2n\reflection\ReflectionContext;
use n2n\persistence\orm\model\NamingStrategy;
use n2n\reflection\property\AccessProxy;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\OrmException;
use n2n\persistence\orm\OrmError;
use n2n\persistence\orm\attribute\Column;
use n2n\reflection\attribute\AttributeSet;
use n2n\persistence\orm\attribute\AttributeOverrides;

class ClassSetup {
	private $setupProcess;
	private $class;
	private $attributeSet;
	private $namingStrategy;
	private $parentClassSetup;
	private $attributeOverrides = array();
	private $entityProperties = array();
	private $parentPropertyName;

	public function __construct(SetupProcess $setupProcess, \ReflectionClass $class,
			NamingStrategy $namingStrategy, ?ClassSetup $parentClassSetup = null, $parentPropertyName = null) {
		$this->setupProcess = $setupProcess;
		$this->class = $class;
		$this->attributeSet = ReflectionContext::getAttributeSet($this->class);
		$this->namingStrategy = $namingStrategy;
		$this->parentClassSetup = $parentClassSetup;
		$this->parentPropertyName = $parentPropertyName;
	}
	/**
	 * @return boolean
	 */
	public function isPseudo() {
		return $this->parentClassSetup !== null && $this->parentPropertyName === null;
	}
	/**
	 * @return SetupProcess
	 */
	public function getSetupProcess() {
		return $this->setupProcess;
	}
	/**
	 * @return \ReflectionClass
	 */
	public function getClass() {
		return $this->class;
	}

// 	public function setParentClassSetup(?ClassSetup $parentClassSetup = null) {
// 		$this->parentClassSetup = $parentClassSetup;
// 	}
	/**
	 * @return ClassSetup
	 */
	public function getParentClassSetup() {
		return $this->parentClassSetup;
	}
	/**
	 * @return string
	 */
	public function getParentPropertyName() {
		return $this->parentPropertyName;
	}
	/**
	 * @return AttributeSet
	 */
	public function getAttributeSet() {
		return $this->attributeSet;
	}
	/**
	 * @return NamingStrategy
	 */
	public function getNamingStrategy() {
		return $this->namingStrategy;
	}
	/**
	 * @return EntityModel
	 */
	public function getEntityModel() {
		return $this->setupProcess->getEntityModel();
	}
	/**
	 * @return AttributeOverrides[]
	 */
	public function getAttributeOverrides() {
		return $this->attributeOverrides;
	}
	/**
	 * @param AttributeOverrides $attributeOverrides
	 */
	public function addAttributeOverrides(AttributeOverrides $attributeOverrides) {
		$this->attributeOverrides[] = $attributeOverrides;
	}
	/**
	 * @param \Exception $e
	 * @param AccessProxy $propertyAccessProxy
	 * @param array $causingComponents
	 * @return \Exception
	 */
	public function decorateException(\Exception $e, AccessProxy $propertyAccessProxy,
			array $causingComponents = array()) {
		$property = $propertyAccessProxy->getProperty();
		return $this->createException('Initialization of entity property '
				. $property->getDeclaringClass()->getName() . '::$' . $property->getName() . ' failed. Reason: '
				. $e->getMessage(), $e, $causingComponents);
	}
	/**
	 * @param string $message
	 * @param \Exception $causingE
	 * @param array $causingComponents
	 * @return \Exception
	 */
	public function createException($message, ?\Exception $causingE = null,
			array $causingComponents = array()) {
		return SetupProcess::createPropertyException($message, $causingE, $causingComponents);
	}

	/**
	 * @param string $propertyName
	 * @param bool $overrideAllowed
	 * @param array $relatedComponents
	 * @return string|null
	 */
	private function determineColumnName($propertyName, $overrideAllowed, array &$relatedComponents) {
		foreach ($this->attributeOverrides as $attributeOverrides) {
			$map = $attributeOverrides->getPropertyColumnMap();
			if (!isset($map[$propertyName]))  continue;

			if ($overrideAllowed) {
				$relatedComponents[] = $this->attributeOverrides;
				return $map[$propertyName];
			}

			throw $this->createException('Illegal Column override for property: '
					. $this->class->getName() . '::$' . $propertyName,
					null, array($attributeOverrides));
		}

		$attrColumn = $this->attributeSet->getPropertyAttribute($propertyName, Column::class);
		if ($attrColumn !== null) {
			if ($overrideAllowed) {
				$relatedComponents[] = $attrColumn;
				return $attrColumn->getInstance()->getName();
			}

			throw $this->createException('Illegal Column override for property: '
					. $this->class->getName() . '::$' . $propertyName,
					null, array($attrColumn));
		}

		return null;
	}
	/**
	 * Request a column to write your data.
	 * @param string $propertyName
	 * @param string $columnName
	 * @param string $overrideAllowed
	 * @param array $relatedComponents
	 * @return string
	 */
	public function requestColumn(string $propertyName, ?string $columnName = null, array $relatedComponents = array()) {
		$determineColumnName = $this->determineColumnName($propertyName, $columnName === null, $relatedComponents);
		if ($columnName === null) {
			$columnName = $determineColumnName;
		}

		$columnName = $this->namingStrategy->buildColumnName($propertyName, $columnName);

		$this->setupProcess->registerColumnName($columnName, $this->buildPropertyString($propertyName), $relatedComponents);

		return $columnName;
	}

	public function requestJoinColumn(string $propertyName, string $targetIdPropertyName, ?string $joinColumnName = null, array $relatedComponents = array()) {
		$this->determineColumnName($propertyName, false, $relatedComponents);

		$columnName = $this->namingStrategy->buildJoinColumnName($propertyName, $targetIdPropertyName, $joinColumnName);

		$this->setupProcess->registerColumnName($columnName, $this->buildPropertyString($propertyName), $relatedComponents);

		return $columnName;
	}

	public function buildPropertyString($propertyName) {
		$propertyNames = array($propertyName);

		$classSetup = $this;
		$class = $this->class;
		while (null !== ($classSetup = $classSetup->getParentClassSetup())) {
			$parentPropertyName = $classSetup->getParentPropertyName();
			if ($parentPropertyName === null) continue;

			array_unshift($propertyNames, $parentPropertyName);
			$class = $classSetup->getClass();
		}

		return $class->getName() . '::$' . implode('::$', $propertyNames);
	}

	public function containsEntityPropertyName($name) {
		if ($this->isPseudo()) {
			return $this->parentClassSetup->containsEntityPropertyName($name);
		}

		return isset($this->entityProperties[$name]);
	}
	/**
	 * @param EntityProperty $entityProperty
	 * @param array $relatedComponents
	 * @throws OrmException
	 * @throws OrmError
	 */
	public function provideEntityProperty(EntityProperty $entityProperty,
			array $relatedComponents = array()) {

		if ($this->isPseudo()) {
			$this->parentClassSetup->provideEntityProperty($entityProperty, $relatedComponents);
			return;
		}

		if (!isset($this->entityProperties[$entityProperty->getName()])) {
			$entityProperty->setEntityModel($this->setupProcess->getEntityModel());
			$this->entityProperties[$entityProperty->getName()] = $entityProperty;
			return;
		}

		throw self::createPropertyException('Entity property for '
				. $this->class->getName() . '::$' . $entityProperty->getName()
				. ' already defined.', null, $relatedComponents);
	}
	/**
	 * @return EntityProperty[]
	 */
	public function getEntityProperties() {
		IllegalStateException::assertTrue(!$this->isPseudo());

		return $this->entityProperties;
	}
	/**
	 * @param \Closure $closure
	 * @param bool $prepend
	 */
	public function onFinalize(\Closure $closure, $prepend = false) {
		$this->setupProcess->getOnFinalizeQueue()->onFinalize($closure, $prepend);
	}
}
