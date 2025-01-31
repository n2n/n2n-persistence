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

use n2n\util\col\ArrayUtils;
use n2n\reflection\ReflectionContext;
use n2n\reflection\ReflectionUtils;
use n2n\persistence\orm\InheritanceType;
use n2n\persistence\orm\property\SetupProcess;
use n2n\persistence\orm\OrmConfigurationException;
use n2n\persistence\orm\property\BasicEntityProperty;
use n2n\persistence\orm\property\PropertyInitializationException;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\property\ClassSetup;
use n2n\persistence\orm\OrmError;
use n2n\persistence\orm\property\IdDef;
use n2n\persistence\orm\LifecycleUtils;
use n2n\persistence\orm\attribute\MappedSuperclass;
use n2n\reflection\attribute\AttributeSet;
use n2n\persistence\orm\attribute\DiscriminatorColumn;
use n2n\persistence\orm\attribute\Inheritance;
use n2n\persistence\orm\attribute\DiscriminatorValue;
use n2n\persistence\orm\attribute\Table;
use n2n\persistence\orm\attribute\EntityListeners;
use n2n\persistence\orm\attribute\Id;
use n2n\reflection\attribute\PropertyAttribute;
use n2n\persistence\orm\property\EntityPropertyProvider;
use n2n\reflection\attribute\ClassAttribute;
use n2n\util\ex\err\ConfigurationError;
use n2n\core\TypeNotFoundException;
use ReflectionClass;

class EntityModelFactory {
	const DEFAULT_ID_PROPERTY_NAME = 'id';
	const DEFAULT_DISCRIMINATOR_COLUMN = 'discr';

	private ?array $entityPropertyProviders = null;
	private NamingStrategy $defaultNamingStrategy;
	private ?OnFinalizeQueue $onFinalizeQueue = null;
	private array $entityModelInitializers = [];


	/**
	 * @param string[] $entityPropertyProviderClassNames
	 */
	public function __construct(private readonly array $entityPropertyProviderClassNames,
			$defaultNamingStrategyClassName = null) {

		if ($defaultNamingStrategyClassName === null) {
			$this->defaultNamingStrategy = new HyphenatedNamingStrategy();
			return;
		}

		$class = ReflectionUtils::createReflectionClass($defaultNamingStrategyClassName);
		if (!$class->implementsInterface(NamingStrategy::class)) {
			throw new \InvalidArgumentException('Naming strategy class must implement interface'
					. NamingStrategy::class . ': ' . $defaultNamingStrategyClassName);
		}
		$this->defaultNamingStrategy = ReflectionUtils::createObject($class);
	}

	function setOnFinalizeQueue(OnFinalizeQueue $onFinalizeQueue): void {
		$this->onFinalizeQueue = $onFinalizeQueue;
	}

	/**
	 * @return array
	 */
	public function getEntityPropertyProviderClassNames(): array {
		return $this->entityPropertyProviderClassNames;
	}

	/**
	 * @return \n2n\persistence\orm\property\EntityPropertyProvider[]
	 * @throws OrmConfigurationException
	 */
	private function getEntityPropertyProviders() {
		if ($this->entityPropertyProviders !== null) {
			return $this->entityPropertyProviders;
		}

		$this->entityPropertyProviders = array();
		foreach ($this->entityPropertyProviderClassNames as $entityPropertyProviderClassName) {
			$providerClass = ReflectionUtils::createReflectionClass($entityPropertyProviderClassName);
			if (!$providerClass->isSubclassOf(EntityPropertyProvider::class)) {
				throw new OrmConfigurationException('EntityPropertyProvider must implement '
						. 'interface . ' . EntityPropertyProvider::class . ': '
						. $providerClass->getName());
			}

			$this->entityPropertyProviders[] = $providerClass->newInstance();
		}

		return $this->entityPropertyProviders;
	}

	/**
	 * @param ReflectionClass $entityClass
	 * @param EntityModel|null $superEntityModel
	 * @return EntityModel
	 */
	public function create(ReflectionClass $entityClass, ?EntityModel $superEntityModel = null): EntityModel {
		$attributeSet = ReflectionContext::getAttributeSet($entityClass);

		if (null !== $attributeSet->getClassAttribute(MappedSuperclass::class)) {
			throw new ModelInitializationException('Could not initialize MappedSuperclass as entity: '
					. $entityClass->getName());
		}

		$entityModel = new EntityModel($entityClass, $superEntityModel);

		$superEntityModelInitializer = null;
		if ($superEntityModel !== null) {
			$superEntityClassName = $superEntityModel->getClass()->getName();
			IllegalStateException::assertTrue(isset($this->entityModelInitializers[$superEntityClassName]));
			$superEntityModelInitializer = $this->entityModelInitializers[$superEntityClassName];
		}

		$this->entityModelInitializers[$entityClass->getName()] = $entityModelInitializer
				= new EntityModelInitializer($entityModel, $this->defaultNamingStrategy, $attributeSet,
						$this->getEntityPropertyProviders(), $this->onFinalizeQueue, $superEntityModelInitializer);
		$entityModel->setEntityModelAccessCallback(function () use ($entityModelInitializer) {
			$entityModelInitializer->execIfNecessary();
		});

		return $entityModel;
	}

	public function cleanUp(): void {
		$this->onFinalizeQueue->finalize();
	}

}

class EntityModelInitializer {
	private ?SetupProcess $setupProcess = null;
	private NamingStrategy $namingStrategy;

	function __construct(private EntityModel $entityModel, private NamingStrategy $defaultNamingStrategy,
			private AttributeSet $attributeSet, private array $entityPropertyProviders,
			private ?OnFinalizeQueue $onFinalizeQueue, private ?EntityModelInitializer $superEntityModelInitializer = null) {

	}

	function getSetupProcess(): SetupProcess {
		$this->execIfNecessary();
		return $this->setupProcess;
	}

	private function pre(): void {
		IllegalStateException::assertTrue($this->setupProcess === null, 'Already initialized.');

		$this->setupProcess = new SetupProcess($this->entityModel,
				new EntityPropertyAnalyzer($this->entityPropertyProviders),
				$this->onFinalizeQueue);

		if ($this->superEntityModelInitializer !== null) {
			$this->setupProcess->inherit($this->superEntityModelInitializer->getSetupProcess());
		}

		$this->namingStrategy = $this->defaultNamingStrategy;
		$namingStrategyAttrInstance = $this->attributeSet->getClassAttribute(
				\n2n\persistence\orm\attribute\NamingStrategy::class)?->getInstance();
		if (null !== $namingStrategyAttrInstance) {
			$this->namingStrategy = $namingStrategyAttrInstance->getNamingStrategy();
		}
	}

	function execIfNecessary(): void {
		if ($this->setupProcess !== null) {
			return;
		}

		$this->onFinalizeQueue?->push($this->entityModel);

		$this->pre();

		$this->analyzeInheritanceType();
		$this->analyzeDiscriminatorColumn();
		$this->analyzeDiscriminatorValue();
		$this->analyzeTable();
		$this->analyzeCallbacks();
		try {
			$this->analyzeProperties();
			$this->analyzeId();
		} catch (PropertyInitializationException $e) {
			throw new ModelInitializationException('Could not initialize entity: '
					. $this->entityModel->getClass()->getName(), 0, $e);
		}

		$this->onFinalizeQueue?->pop($this->entityModel);
	}
	
	/**
	 * 
	 */
	private function analyzeInheritanceType(): void {
		$superEntityModel = $this->entityModel->getSuperEntityModel();
		
		if (null !== $superEntityModel && null == $superEntityModel->getInheritanceType()) {
			throw OrmError::create('No inheritance strategy defined in supreme class of'
							.  $this->entityModel->getClass()->getName(),  
					array($this->entityModel->getSupremeEntityModel()->getClass()));
		}

		$inheritanceAttr = $this->attributeSet->getClassAttribute(Inheritance::class);
		if (null === $inheritanceAttr) return;
		
		if ($superEntityModel !== null) {
			throw OrmError::create('Inheritance strategy of ' . $this->entityModel->getClass()->getName()
							. 'has to be specified in supreme class', array($inheritanceAttr));
		}

		$inheritanceAttrInstance = $inheritanceAttr->getInstance();
		$this->entityModel->setInheritanceType($inheritanceAttrInstance->getStrategy());

		if ($inheritanceAttrInstance->getStrategy() == InheritanceType::SINGLE_TABLE) {
			$discriminatorColumnAttr = $this->attributeSet->getClassAttribute(DiscriminatorColumn::class);
			if ($discriminatorColumnAttr === null) {
				$discriminatorColumnName = EntityModelFactory::DEFAULT_DISCRIMINATOR_COLUMN;
			} else {
				$discriminatorColumnName = $discriminatorColumnAttr->getInstance()->getColumnName();
			}
			$this->entityModel->setDiscriminatorColumnName($discriminatorColumnName);
		}
	}
	/**
	 * 
	 */
	private function analyzeDiscriminatorColumn() {
		$discriminatorValueAttr = $this->attributeSet->getClassAttribute(DiscriminatorValue::class);
		
		if ($discriminatorValueAttr === null) {
			if ($this->entityModel->getInheritanceType() == InheritanceType::SINGLE_TABLE
					&& !$this->entityModel->getClass()->isAbstract()) {
				throw OrmError::create('No discriminator value defined for entity: '
						. $this->entityModel->getClass()->getName(), array($discriminatorValueAttr));
			}
			
			return;
		}
		
		if ($this->entityModel->getInheritanceType() != InheritanceType::SINGLE_TABLE) {
			throw OrmError::create('Discriminator value can only be defined for entities with inheritance type SINGLE_TABLE'
					. $this->entityModel->getClass()->getName(), array($discriminatorValueAttr));
		}

		if ($this->entityModel->getClass()->isAbstract()) {
			throw OrmError::create('Discriminator value must not be defined for abstract entity: '
					. $this->entityModel->getClass()->getName(), array($discriminatorValueAttr));
		}
			
		$this->entityModel->setDiscriminatorValue($discriminatorValueAttr->getInstance()->getValue());
	}
	/**
	 * 
	 */
	private function analyzeDiscriminatorValue() {
		if ($this->entityModel->getInheritanceType() != InheritanceType::SINGLE_TABLE 
				|| $this->entityModel->getClass()->isAbstract()) {
			return;
		}

		$discriminatorValueAttr = $this->attributeSet->getClassAttribute(DiscriminatorValue::class);
		if (null !== $discriminatorValueAttr) {
			$this->entityModel->setDiscriminatorValue($discriminatorValueAttr->getInstance()->getValue());
			return;
		}
		
		throw OrmError::create('No discriminator value defined for entity: '
				. $this->entityModel->getClass()->getName(), array($this->entityModel->getClass()));
	}
	/**
	 * 
	 */
	private function analyzeTable() {
		if ($this->entityModel->getInheritanceType() == InheritanceType::SINGLE_TABLE 
				&& $this->entityModel->hasSuperEntityModel()) {
			$this->entityModel->setTableName($this->entityModel->getSuperEntityModel()->getTableName());
			return;
		} 
		
		$tableName = null;
		if (null !== ($tableAttr = $this->attributeSet->getClassAttribute(Table::class))) {
			$tableName = $tableAttr->getInstance()->getName();
		} 
		
		$this->entityModel->setTableName($this->namingStrategy->buildTableName(
				$this->entityModel->getClass(), $tableName));
	}
	/**
	 * 
	 */
	private function analyzeCallbacks() {
		$class = $this->entityModel->getClass();
		foreach ($class->getMethods() as $method) {
			if ($method->getDeclaringClass() != $class) continue;

			$eventType = LifecycleUtils::identifyEvent($method->getName());
			if ($eventType === null) continue;
			
			$this->entityModel->addLifecycleMethod($eventType, $method);
		}
		
		$entityListenerAttribute = $this->attributeSet->getClassAttribute(EntityListeners::class);
		if ($entityListenerAttribute !== null) {
			$this->entityModel->setEntityListenerClasses($this->extractEntityListenerClasses($entityListenerAttribute));
		}
	}

	private function extractEntityListenerClasses(ClassAttribute $classAttribute) {
		try {
			return array_map(
					fn($className) => new ReflectionClass($className),
					$classAttribute->getInstance()->getClasses());
		} catch (\ReflectionException $e) {
			throw new ConfigurationError('Could not load EntityListeners for '
					. $classAttribute->getClass()->getName(), $classAttribute->getFile(), $classAttribute->getLine());
		}
	}
	/**
	 * 
	 */
	private function analyzeProperties() {
		$classSetup = new ClassSetup($this->setupProcess, $this->entityModel->getClass(),
				$this->namingStrategy);
		$this->setupProcess->getEntityPropertyAnalyzer()->analyzeClass($classSetup);
			
		foreach ($classSetup->getEntityProperties() as $property) {
			$this->entityModel->addEntityProperty($property);
		}
	}
	/**
	 * 
	 */
	private function analyzeId() {
		$idAttrs = $this->attributeSet->getPropertyAttributesByName(Id::class);
		if (count($idAttrs) > 1) {
			throw OrmError::create('Multiple ids defined in Entity: '
					. $this->entityModel->getClass()->getName(), $idAttrs);
		} 
		
		$propertyName = EntityModelFactory::DEFAULT_ID_PROPERTY_NAME;
		$generatedValue = $this->entityModel->getInheritanceType() != InheritanceType::TABLE_PER_CLASS;
		$sequenceName = null;
		/**
		 * @var PropertyAttribute $idAttr
		 */
		$idAttr = ArrayUtils::current($idAttrs);

		if ($idAttr === null) {
			if ($this->entityModel->hasSuperEntityModel()) return;
		} else {
			if ($this->entityModel->hasSuperEntityModel()) {
				throw OrmError::create(
						'Id for ' . $this->entityModel->getClass()->getName() . ' already defined in super class '
								. $this->entityModel->getSuperEntityModel()->getClass()->getName(),
						array($idAttr));
			}

			$propertyName = $idAttr->getProperty()->getName();
			$generatedValue = $idAttr->getInstance()->isGenerated();
			if ($generatedValue && $this->entityModel->getInheritanceType() == InheritanceType::TABLE_PER_CLASS) {
				throw OrmError::create(
						'Ids with generated values are not compatible with inheritance type TABLE_PER_CLASS in ' 
								. $this->entityModel->getClass()->getName() . '.', $idAttrs);
			}
			$sequenceName = $idAttr->getInstance()->getSequenceName();
		}

		try {
			$idProperty = $this->entityModel->getEntityPropertyByName($propertyName);
			if ($idProperty instanceof BasicEntityProperty) {
				$this->entityModel->setIdDef(new IdDef($idProperty, $generatedValue, $sequenceName));
				return;
			}

			throw $this->setupProcess->createPropertyException('Invalid property type for id.', null, [$idAttr]);
		} catch (UnknownEntityPropertyException $e) {
			throw $this->setupProcess->createPropertyException('No id property defined.', $e, []);
		}
	}
}

class OnFinalizeQueue {
	private array $onFinalizeClosures = array();
	private array $stackedClassNames = [];

	function __construct(private EntityModelManager $entityModelManager) {

	}
	
	public function onFinalize(\Closure $closure, $prepend = false): void {
		if ($prepend) {
			array_unshift($this->onFinalizeClosures, $closure);
		} else {
			$this->onFinalizeClosures[] = $closure;
		}
	}

	function push(EntityModel $entityModel): void {
		$this->stackedClassNames[] = $entityModel->getClass()->getName();
	}

	function pop(EntityModel $entityModel): void {
		$className = array_pop($this->stackedClassNames);
		IllegalStateException::assertTrue($className === $entityModel->getClass()->getName());

		if (empty($this->stackedClassNames)) {
			$this->finalize();
		}
	}
	
	public function finalize(): void {
		$this->stackedClassNames = [];

		while (null !== ($onFinalizeClosure = array_shift($this->onFinalizeClosures))) {
			$onFinalizeClosure($this->entityModelManager);
		}
	}
} 