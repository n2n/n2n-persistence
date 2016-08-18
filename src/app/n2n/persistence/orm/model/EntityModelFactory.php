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
namespace n2n\persistence\orm\model;

use n2n\util\col\ArrayUtils;
use n2n\reflection\ReflectionContext;
use n2n\reflection\ReflectionUtils;
use n2n\persistence\orm\InheritanceType;
use n2n\persistence\orm\property\SetupProcess;
use n2n\persistence\orm\model\UnknownEntityPropertyException;
use n2n\persistence\orm\OrmConfigurationException;
use n2n\persistence\orm\property\BasicEntityProperty;
use n2n\web\dispatch\model\ModelInitializationException;
use n2n\persistence\orm\property\PropertyInitializationException;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\property\ClassSetup;
use n2n\persistence\orm\OrmErrorException;
use n2n\persistence\orm\property\IdDef;
use n2n\persistence\orm\LifecycleUtils;
use n2n\persistence\orm\annotation\AnnoMappedSuperclass;

class EntityModelFactory {
	const DEFAULT_ID_PROPERTY_NAME = 'id';
	const DEFAULT_DISCRIMINATOR_COLUMN = 'discr';
	
	private $entityPropertyProviderClassNames;
	private $entityPropertyProviders;
	private $defaultNamingStrategy;
	private $onFinalizeQueue;
	
	private $annotationSet;
	private $entityModel;
	private $nampingStrategy;
	private $setupProcess;
	/**
	 * @param array $entityPropertyProviderClassNames
	 */
	public function __construct(array $entityPropertyProviderClassNames, 
			$defaultNamingStrategyClassName = null) {
		$this->entityPropertyProviderClassNames = $entityPropertyProviderClassNames;
		$this->onFinalizeQueue = new OnFinalizeQueue();
	
		if ($defaultNamingStrategyClassName === null) {
			$this->defaultNamingStrategy = new HyphenatedNamingStrategy();
			return;
		} 
		
		$class = ReflectionUtils::createReflectionClass($defaultNamingStrategyClassName);
		if (!$class->implementsInterface('n2n\persistence\orm\model\NamingStrategy')) {
			throw new \InvalidArgumentException('Naming strategy class must implement interface'
					. ' n2n\persistence\orm\model\NamingStrategy: ' . $defaultNamingStrategyClassName);
		}
		$this->defaultNamingStrategy = ReflectionUtils::createObject($class);
	}
	/**
	 * @return array
	 */
	public function getEntityPropertyProviderClassNames() {
		return $this->entityPropertyProviderClassNames;
	}
	/**
	 * @throws OrmConfigurationException
	 * @return \n2n\persistence\orm\property\EntityPropertyProvider[]
	 */
	private function getEntityPropertyProviders() {
		if ($this->entityPropertyProviders !== null) {
			return $this->entityPropertyProviders;
		}
		
		$this->entityPropertyProviders = array();
		foreach ($this->entityPropertyProviderClassNames as $entityPropertyProviderClassName) {
			$providerClass = ReflectionUtils::createReflectionClass($entityPropertyProviderClassName);
			if (!$providerClass->isSubclassOf('n2n\persistence\orm\property\EntityPropertyProvider')) {
				throw new OrmConfigurationException('EntityPropertyProvider must implement ' 
						. 'interface n2n\persistence\orm\property\EntityPropertyProvider: ' 
						. $providerClass->getName());
			}	

			$this->entityPropertyProviders[] = $providerClass->newInstance();
		}
		
		return $this->entityPropertyProviders;
	}
	/**
	 * @param \ReflectionClass $entityClass
	 * @param EntityModel $superEntityModel
	 * @return \n2n\persistence\orm\model\EntityModel
	 */
	public function create(\ReflectionClass $entityClass, EntityModel $superEntityModel = null) {
		if ($this->setupProcess !== null) {
			throw new IllegalStateException('SetupProcess not finished.');
		}
		
		$this->annotationSet = ReflectionContext::getAnnotationSet($entityClass);
		
		if (null !== $this->annotationSet->getClassAnnotation(AnnoMappedSuperclass::class)) {
			throw new ModelInitializationException('Could not initialize MappedSuperclass as entity: '
					. $this->entityModel->getClass()->getName());
		}
		
		$this->entityModel = $entityModel = new EntityModel($entityClass, $superEntityModel); 
		$this->setupProcess = new SetupProcess($this->entityModel, 
				new EntityPropertyAnalyzer($this->getEntityPropertyProviders()),
				$this->onFinalizeQueue);
		
		$this->nampingStrategy = $this->defaultNamingStrategy;
		if (null !== ($annoNamingStrategy = $this->annotationSet->getClassAnnotation(
				'n2n\persistence\orm\annotation\AnnoNamingStrategy'))) {
			$this->nampingStrategy = $annoNamingStrategy->getNamingStrategy();
		}
		
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
		
		return $entityModel;
	}
	
	public function cleanUp(EntityModelManager $entityModelManager) {
		if ($this->setupProcess === null) {
			throw new IllegalStateException('No pending SetupProcess');
		}
				
		$this->setupProcess = null;
		$this->annotationSet = null;
		$this->entityModel = null;
		$this->propertiesAnalyzer = null;
		
		$this->onFinalizeQueue->finalize($entityModelManager);
	}
	
	/**
	 * 
	 */
	private function analyzeInheritanceType() {
		$superEntityModel = $this->entityModel->getSuperEntityModel();
		
		if (null !== $superEntityModel && null == $superEntityModel->getInheritanceType()) {
			throw OrmErrorException::create('No inheritance strategy defined in supreme class of'
							.  $this->entityModel->getClass()->getName(),  
					array($this->entityModel->getSupremeEntityModel()->getClass()));
		}
		
		$annoInheritance = $this->annotationSet->getClassAnnotation(
				'n2n\persistence\orm\annotation\AnnoInheritance');
		
		if (null === $annoInheritance) return;
		
		if ($superEntityModel !== null) {
			throw OrmErrorException::create('Inheritance strategy of ' . $this->entityModel->getClass()->getName()
							. 'has to be specified in supreme class', array($annoInheritance));
		}

		$this->entityModel->setInheritanceType($annoInheritance->getStrategy());

		if ($annoInheritance->getStrategy() == InheritanceType::SINGLE_TABLE) {
			$annoDiscriminatorColumn = $this->annotationSet->getClassAnnotation('n2n\persistence\orm\annotation\AnnoDiscriminatorColumn');
			if ($annoDiscriminatorColumn === null) {
				$discriminatorColumnName = self::DEFAULT_DISCRIMINATOR_COLUMN;
			} else {
				$discriminatorColumnName = $annoDiscriminatorColumn->getColumnName(); 
			}
			$this->entityModel->setDiscriminatorColumnName($discriminatorColumnName);
		}
	}
	/**
	 * 
	 */
	private function analyzeDiscriminatorColumn() {
		$annoDiscriminatorValue = $this->annotationSet->getClassAnnotation(
				'n2n\persistence\orm\annotation\AnnoDiscriminatorValue');
		
		if ($annoDiscriminatorValue === null) {
			if ($this->entityModel->getInheritanceType() == InheritanceType::SINGLE_TABLE
					&& !$this->entityModel->getClass()->isAbstract()) {
				throw OrmErrorException::create('No discriminator value defined for entity: '
						. $this->class->getName(), array($annoDiscriminatorValue));
			}
			
			return;
		}
		
		if ($this->entityModel->getInheritanceType() != InheritanceType::SINGLE_TABLE) {
			throw OrmErrorException::create('Discriminator value can only be defined for entities with inheritance type SINGLE_TABLE'
					. $this->entityModel->getClass()->getName(), array($annoDiscriminatorValue));
		}

		if ($this->entityModel->getClass()->isAbstract()) {
			throw OrmErrorException::create('Discriminator value must not be defined for abstract entity: '
					. $this->entityModel->getClass()->getName(), array($annoDiscriminatorValue));
		}
			
		$this->entityModel->setDiscriminatorValue($annoDiscriminatorValue->getValue());
	}
	/**
	 * 
	 */
	private function analyzeDiscriminatorValue() {
		if ($this->entityModel->getInheritanceType() != InheritanceType::SINGLE_TABLE 
				|| $this->entityModel->getClass()->isAbstract()) {
			return;
		}
		
		if (null !== ($annoDiscriminatorValue = $this->annotationSet->getClassAnnotation(
				'n2n\persistence\orm\annotation\AnnoDiscriminatorValue'))) {
			$this->entityModel->setDiscriminatorValue($annoDiscriminatorValue->getValue());
			return;
		}
		
		throw OrmErrorException::create('No discriminator value defined for entity: '
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
		if (null !== ($annoTable = $this->annotationSet->getClassAnnotation(
				'n2n\persistence\orm\annotation\AnnoTable'))) {
			$tableName = $annoTable->getName();
		} 
		
		$this->entityModel->setTableName($this->nampingStrategy->buildTableName(
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
		
		$annoEntityListener = $this->annotationSet->getClassAnnotation(
				'n2n\persistence\orm\annotation\AnnoEntityListeners');
		if ($annoEntityListener !== null) {
			$this->entityModel->setEntityListenerClasses($annoEntityListener->getClasses());
		}
	}
	/**
	 * 
	 */
	private function analyzeProperties() {
		$classSetup = new ClassSetup($this->setupProcess, $this->entityModel->getClass(), 
				$this->nampingStrategy);
		$this->setupProcess->getEntityPropertyAnalyzer()->analyzeClass($classSetup);
			
		foreach ($classSetup->getEntityProperties() as $property) {
			$this->entityModel->addEntityProperty($property);
		}
	}
	/**
	 * 
	 */
	private function analyzeId() {
		$annoIds = $this->annotationSet->getPropertyAnnotationsByName('n2n\persistence\orm\annotation\AnnoId');
		if (count($annoIds) > 1) {
			throw OrmErrorException::create('Multiple ids defined in Entity: ' 
					. $this->entityModel->getClass()->getName(), $annoIds);
		} 
		
		$accessProxy = null;
		$propertyName = self::DEFAULT_ID_PROPERTY_NAME;
		$generatedValue = $this->entityModel->getInheritanceType() != InheritanceType::TABLE_PER_CLASS;
		$sequenceName = null;
		
		$annoId = ArrayUtils::current($annoIds);
		if ($annoId === null) {
			if ($this->entityModel->hasSuperEntityModel()) return;
		} else {
			if ($this->entityModel->hasSuperEntityModel()) {
				throw OrmErrorException::create(
						'Id for ' . $this->class->getName() . ' already defined in super class '
								. $this->entityModel->getSuperEntityModel()->getClass()->getName(),
						array($annoId));
			}
			
			$propertyName = $annoId->getAnnotatedProperty()->getName();
			$generatedValue = $annoId->isGenerated();
			if ($generatedValue && $this->entityModel->getInheritanceType() == InheritanceType::TABLE_PER_CLASS) {
				throw OrmErrorException::create(
						'Ids with generated values are not compatible with inheritance type TABLE_PER_CLASS in ' 
								. $this->entityModel->getClass()->getName() . '.', $annoIds);
			}
			$sequenceName = $annoId->getSequenceName();
		}

		try {
			$idProperty = $this->entityModel->getEntityPropertyByName($propertyName);
			if ($idProperty instanceof BasicEntityProperty) {
				$this->entityModel->setIdDef(new IdDef($idProperty, $generatedValue, $sequenceName));
				return;
			}
			throw $this->setupProcess->createPropertyException('Invalid property type for id.', null, $annoIds);
		} catch (UnknownEntityPropertyException $e) {
			throw $this->setupProcess->createPropertyException('No id property defined.', $e, $annoIds);
		}
	}
}

class OnFinalizeQueue {
	private $onFinalizeClosures = array();
	private $entityModelManager = null;
	
	public function onFinalize(\Closure $closure, $prepend = false) {
		if ($prepend) {
			array_unshift($this->onFinalizeClosures, $closure);
		} else {
			$this->onFinalizeClosures[] = $closure;
		}
	}
	
	public function finalize(EntityModelManager $entityModelManager) {
		if ($this->entityModelManager !== null) return;
		$this->entityModelManager = $entityModelManager;
		while (null !== ($onFinalizeClosure = array_shift($this->onFinalizeClosures))) {
			$onFinalizeClosure($entityModelManager);
		}
		$this->entityModelManager = null;
	}
}  


// class EntityModelFactory2 {
// 	const DEFAULT_ID_PROPERTY_NAME = 'id';
// 	const DEFAULT_DISCRIMINATOR_COLUMN = 'discr';

// 	private $entityModel;
// 	private $class;
// 	private $superEntityModel;
// 	private $inheritanceType;
// 	private $propertyAnnotations;
// 	private $propertiesAnalyzer;
// 	private $accessProxies;
// 	private $columnAnnotations;

// 	public function __construct(\ReflectionClass $class, EntityModel $superEntityModel = null) {
// 		$this->class = $class;
// 		$this->entityModel = new EntityModel($class, $superEntityModel);
// 		$this->superEntityModel = $superEntityModel;
// 		$this->propertiesAnalyzer = new PropertiesAnalyzer($this->class, true);
// 		$this->propertiesAnalyzer->setSuperIgnored(true);
// 	}
	
// 	public function build() {
// 		$annotationSet = ReflectionContext::getAnnotationSet($this->class);
// 		$this->analyzeClass($annotationSet);
// 		$this->analyzeProperties($annotationSet);
// 	}
	
// 	public function getEntityModel() {
// 		return $this->entityModel;
// 	}
	
// 	private function analyzeClass(AnnotationSet $annotationSet = null) {
// 		$this->inheritanceType = null;
// 		if (isset($this->superEntityModel)) {
// 			$this->inheritanceType = $this->superEntityModel->getInheritanceType();
// 			if (is_null($this->inheritanceType)) {
// 				throw $this->createException(
// 						SysTextUtils::get('n2n_error_persistence_orm_no_inheritance_strategy_defined_in_super_class',
// 								array('entity' => $this->class->getName())));
// 			}
				
// 			$this->entityModel->setInheritanceType($this->inheritanceType);
// 			$this->entityModel->setDiscriminatorColumnName($this->superEntityModel->getDiscriminatorColumnName());
// 		}

// 		if (isset($annotationSet) && $inheritanceAnnotation = $annotationSet->getClassAnnotation(EntityAnnotations::INHERITANCE)) {
// 			if (isset($this->superEntityModel)) {
// 				throw $this->createAnnotationsException(
// 						SysTextUtils::get('n2n_error_persistence_orm_inheritance_strategy_has_to_be_specified_in_super_class',
// 								array('entity' => $this->entityModel->getClass()->getName())),
// 						array($inheritanceAnnotation));
// 			}
				
// 			$this->inheritanceType = $inheritanceAnnotation->getStrategy();
// 			$this->entityModel->setInheritanceType($this->inheritanceType);
				
// 			if ($inheritanceAnnotation->getStrategy() == InheritanceType::SINGLE_TABLE) {
// 				$discriminatorColumnName = $inheritanceAnnotation->getDiscriminatorColumn();
// 				if (is_null($discriminatorColumnName)) {
// 					$discriminatorColumnName = self::DEFAULT_DISCRIMINATOR_COLUMN;
// 				}
// 				$this->entityModel->setDiscriminatorColumnName($discriminatorColumnName);
// 			}
// 		}
	
// 		if ($this->inheritanceType == InheritanceType::SINGLE_TABLE && !$this->class->isAbstract()) {
// 			if (isset($annotationSet) && $discrValueAnnotation = $annotationSet->getClassAnnotation(EntityAnnotations::DISCRIMINATOR_VALUE)) {
// 				$this->entityModel->setDiscriminatorValue($discrValueAnnotation->getValue());
// 			} else {
// 				throw $this->createException(SysTextUtils::get('n2n_error_persistence_orm_inheritance_no_discriminator_value_defined',
// 						array('entity' => $this->class->getName())));
// 			}
// 		}
	
// 		if ($this->inheritanceType == InheritanceType::SINGLE_TABLE && isset($this->superEntityModel)) {
// 			$this->entityModel->setTableName($this->superEntityModel->getTableName());
// 		} else if (isset($annotationSet) && $annotationSet->hasClassAnnotation(EntityAnnotations::TABLE)) {
// 			$this->entityModel->setTableName($annotationSet->getClassAnnotation(EntityAnnotations::TABLE)->getName());
// 		} else {
// 			$this->entityModel->setTableName(StringUtils::hyphenated(mb_substr($this->class->getName(),
// 					mb_strlen($this->class->getNamespaceName()) + 1)));
// 		}
// 	}

// 	private function analyzeProperties(AnnotationSet $annotationSet = null) {
// 		$this->accessProxies = array();
// 		$this->columnAnnotations = array();
// 		$this->properties = array();
// 		$this->propertyAnnotations = array();

// 		if (isset($annotationSet) && $annotationSet->hasClassAnnotation(EntityAnnotations::PROPERTIES)) {
// 			$managedPropertiesAnnotation = $annotationSet->getClassAnnotation(EntityAnnotations::PROPERTIES);
			
// 			try {
// 				foreach ((array) $managedPropertiesAnnotation->getNames() as $propertyName) {
// 					$this->accessProxies[$propertyName] = $this->propertiesAnalyzer->analyzeProperty($propertyName);
// 				}
// 			} catch (ReflectionException $e) {
// 				throw $this->createAnnotationsException(null, array($managedPropertiesAnnotation), $e);
// 			}
// 		} else {
// 			try {
// 				$this->accessProxies = $this->propertiesAnalyzer->analyzeProperties(true);
// 			} catch (ReflectionException $e) {
// 				throw $this->createException(null, $e);
// 			}
// 		}

// 		if (isset($annotationSet)) {
// 			foreach ($annotationSet->getPropertyAnnotationsByName(EntityAnnotations::COLUMN) as $columnAnnotation) {
// 				$this->columnAnnotations[$columnAnnotation->getPropertyName()] = $columnAnnotation;
// 			}
// 		}

// 		$this->analyzeId($annotationSet);

// 		if (isset($annotationSet)) {
// 			$this->analyzeDateTimeAnnotations($annotationSet);
// 			$this->analyzeFileAnnotations($annotationSet);
// 			$this->analyzeRelationAnnotations($annotationSet);
// 		}

// 		$this->analyzeRemainingColumnAnnotations();
// 		$this->analyzeRemainingAccessProxies();
// 	}

// 	private function getAccessProxy($propertyName, Annotation $annotation = null) {
// 		if (isset($this->superEntityModel) && $this->superEntityModel->containsPropertyName($propertyName)) {
// 			throw $this->createException(SysTextUtils::get('n2n_error_persistence_orm_property_already_defined_in_super_class',
// 					array('super_class' => $this->entityModel->getClass()->getName(),
// 							'property' => $propertyName)));
// 		}

// 		if (isset($this->accessProxies[$propertyName])) {
// 			$accessProxy = $this->accessProxies[$propertyName];
// 			$accessProxy->setForcePropertyAccess(true);
// 			unset($this->accessProxies[$propertyName]);
// 			return $accessProxy;
// 		}

// 		$accessProxy = $this->propertiesAnalyzer->analyzeProperty($propertyName);
// 		$accessProxy->setForcePropertyAccess(true);
// 		return $accessProxy;
// 	}

// 	private function analyzeId(AnnotationSet $annotationSet = null) {
// 		$propertyName = self::DEFAULT_ID_PROPERTY_NAME;
// 		$generatedValue = $this->inheritanceType != InheritanceType::TABLE_PER_CLASS;
// 		$sequenceName = null;
// 		$idAnnotation = null;

// 		if (isset($annotationSet)) {
// 			$idAnnotations = $annotationSet->getPropertyAnnotationsByName(EntityAnnotations::ID);
// 			if (sizeof($idAnnotations) > 1) {
// 				throw $this->createAnnotationsException(
// 						SysTextUtils::get('n2n_error_persistence_orm_does_not_support_entities_with_multiple_ids',
// 								array('class' => $this->class->getName()),
// 								$idAnnotation));
// 			} else if (isset($this->superEntityModel)) {
// 				if (!sizeof($idAnnotations)) return;

// 				throw $this->createAnnotationsException(
// 						SysTextUtils::get('n2n_error_persistence_orm_id_already_defined_in_super_class',
// 								array('class' => $this->class->getName(), 'super_class' => $this->superEntityModel->getClass()->getName()),
// 								$idAnnotation));
// 			}
				
// 			$idAnnotation = ArrayUtils::current($idAnnotations);
// 			if (isset($idAnnotation)) {
// 				$propertyName = $idAnnotation->getPropertyName();
// 				$generatedValue = $idAnnotation->isGeneratedValue();
// 				if ($generatedValue && $this->inheritanceType == InheritanceType::TABLE_PER_CLASS) {
// 					throw $this->createAnnotationsException(
// 							SysTextUtils::get('n2n_error_persistence_orm_generated_value_not_available_with_inheritance_type',
// 									array('class' => $this->class->getName(), 'inheritance_type' => $this->inheritanceType)),
// 							$idAnnotation);
// 				}
// 				$sequenceName = $idAnnotation->getSequenceName();
// 			}
// 		}

// 		if (isset($this->superEntityModel)) {
// 			return;
// 		}

// 		$accessProxy = null;
// 		try {
// 			$accessProxy = $this->getAccessProxy($propertyName, $idAnnotation);
// 		} catch (ReflectionException $e) {
// 			throw $this->createNestedInitializationException($e, $idAnnotation,
// 					SysTextUtils::get('n2n_error_persistence_orm_could_not_initialize_an_id_property_for_entity',
// 							array('class' => $this->class->getName(), 'reason' => $e->getMessage())));
// 		}
	
// 		$idProperty = new IdProperty($this->entityModel, $accessProxy,
// 				$this->checkForColumn($accessProxy->getPropertyName()), $generatedValue, $sequenceName);
// 		$this->entityModel->setIdProperty($idProperty);
// 		$this->addProperty($idProperty, $idAnnotation);
// 	}

// 	private function analyzeDateTimeAnnotations(AnnotationSet $annotationSet) {
// 		foreach ($annotationSet->getPropertyAnnotationsByName(EntityAnnotations::DATETIME) as $annotation) {
// 			$this->addProperty(new DateTimeProperty($this->entityModel,
// 					$this->getAccessProxy($annotation->getPropertyName(), $annotation),
// 					$this->checkForColumn($annotation->getPropertyName())));
// 		}
// 	}

// 	private function analyzeN2nLocaleAnnotations(AnnotationSet $annotationSet) {
// 		foreach ($annotationSet->getPropertyAnnotationsByName(EntityAnnotations::LOCALE) as $annotation) {
// 			$this->addProperty(new N2nLocaleProperty($this->entityModel,
// 					$this->getAccessProxy($annotation->getPropertyName(), $annotation),
// 					$this->checkForColumn($annotation->getPropertyName())));
// 		}
// 	}

// 	private function analyzeFileAnnotations(AnnotationSet $annotationSet) {
// 		foreach ($annotationSet->getPropertyAnnotationsByName(EntityAnnotations::FILE) as $annotation) {
// 			$this->addProperty(new FileProperty($this->entityModel,
// 					$this->getAccessProxy($annotation->getPropertyName(), $annotation),
// 					$this->checkForColumn($annotation->getPropertyName()), $annotation));
// 		}
// 	}
	
// 	private function analyzeRelationAnnotations(AnnotationSet $annotationSet) {
// 		$joinColumnAnnotations = $annotationSet->getPropertyAnnotationsByName(EntityAnnotations::JOIN_COLUMN);
// 		$joinTableAnnotations = $annotationSet->getPropertyAnnotationsByName(EntityAnnotations::JOIN_TABLE);
		
// 		foreach ($annotationSet->getPropertyAnnotationsByName(EntityAnnotations::MANY_TO_ONE) as $annotation) {
// 			$propertyName = $annotation->getPropertyName();
// 			$joinColumnAnno = isset($joinColumnAnnotations[$propertyName]) ? $joinColumnAnnotations[$propertyName] : null;
// 			$joinTableAnno = isset($joinTableAnnotations[$propertyName]) ? $joinTableAnnotations[$propertyName] : null;
			
// 			$this->addProperty(new ManyToOneEntityProperty($this->entityModel,
// 					$this->getAccessProxy($propertyName, $annotation), 
// 					$this->createToOneRelation($annotation, $joinColumnAnno, $joinTableAnno)));
// 		}

// 		foreach ($annotationSet->getPropertyAnnotationsByName(EntityAnnotations::ONE_TO_ONE) as $annotation) {
// 			$propertyName = $annotation->getPropertyName();
// 			$joinColumnAnno = isset($joinColumnAnnotations[$propertyName]) ? $joinColumnAnnotations[$propertyName] : null;
// 			$joinTableAnno = isset($joinTableAnnotations[$propertyName]) ? $joinTableAnnotations[$propertyName] : null;
			
// 			$this->addProperty(new OneToOneProperty($this->entityModel,
// 					$this->getAccessProxy($propertyName, $annotation), 
// 					$this->createToOneRelation($annotation, $joinColumnAnno, $joinTableAnno)));
// 		}

// 		foreach ($annotationSet->getPropertyAnnotationsByName(EntityAnnotations::MANY_TO_MANY) as $annotation) {
// 			$propertyName = $annotation->getPropertyName();
// 			$joinTableAnno = isset($joinTableAnnotations[$propertyName]) ? $joinTableAnnotations[$propertyName] : null;
			
// 			$this->addProperty(new ManyToManyProperty($this->entityModel,
// 					$this->getAccessProxy($propertyName, $annotation), 
// 					$this->createToManyRelation($annotation, $joinTableAnno)));
// 		}

// 		foreach ($annotationSet->getPropertyAnnotationsByName(EntityAnnotations::ONE_TO_MANY) as $annotation) {
// 			$propertyName = $annotation->getPropertyName();
// 			$joinTableAnno = isset($joinTableAnnotations[$propertyName]) ? $joinTableAnnotations[$propertyName] : null;
			
// 			$this->addProperty(new OneToManyProperty($this->entityModel,
// 					$this->getAccessProxy($propertyName, $annotation), 
// 					$this->createToManyRelation($annotation, $joinTableAnno)));
// 		}
// 	}
	

	
// 	private function createToManyRelation(ToMany $toManyAnno, JoinTable $joinTableAnno = null) {
// 		if (!is_null($toManyAnno->getMappedBy())) {
// 			return new PropertyMappedToManyRelation($this->entityModel, $toManyAnno);
// 		}
				
// 		return new JoinTableToManyRelation($this->entityModel, $toManyAnno, $joinTableAnno);
// 	}

// 	private function analyzeRemainingAccessProxies() {
// 		foreach ($this->accessProxies as $accessProxy) {
// 			$accessProxy = $this->getAccessProxy($accessProxy->getPropertyName());
// 			$setterMethod = null;
// 			$constraints = $this->propertiesAnalyzer->getSetterConstraints($accessProxy->getPropertyName(), $setterMethod);
	
// 			$entityProperty = null;
// 			if (DateTimeProperty::areConstraintsTypical($constraints)) {
// 				$entityProperty = new DateTimeProperty($this->entityModel, $accessProxy, $this->checkForColumn($accessProxy->getPropertyName()));
// 			} else if (N2nLocaleProperty::areConstraintsTypical($constraints)) {
// 				$entityProperty = new N2nLocaleProperty($this->entityModel, $accessProxy, $this->checkForColumn($accessProxy->getPropertyName()));
// 			} else if (FileProperty::areConstraintsTypical($constraints)) {
// 				$entityProperty = new FileProperty($this->entityModel, $accessProxy, $this->checkForColumn($accessProxy->getPropertyName()));
// 			} else {
// 				$entityProperty = new ScalarEntityProperty($this->entityModel, $accessProxy, $this->checkForColumn($accessProxy->getPropertyName()));
// // 				throw new OrmErrorException(
// // 						SysTextUtils::get('n2n_error_persistence_orm_could_not_auto_recognize_entity_property_due_to_an_unknown_setter_method_parameter_type',
// // 								array('class' => $this->class->getName(), 'property' => $accessProxy->getPropertyName())),
// // 						0, E_USER_ERROR, $setterMethod->getFileName(), $setterMethod->getStartLine());
// 			}
				
// 			$this->addProperty($entityProperty);
// 		}

// 		$this->accessProxies = array();
// 	}

// 	private function analyzeRemainingColumnAnnotations() {
// 		foreach ($this->columnAnnotations as $columnAnnotation) {
// 			$this->addProperty(new ScalarEntityProperty($this->entityModel, $this->getAccessProxy($columnAnnotation->getPropertyName(), $columnAnnotation),
// 					$columnAnnotation->getName()));
// 			$this->propertiesAnalyzer->analyzeProperty($columnAnnotation->getPropertyName());
// 		}
// 	}

// 	private function checkForColumn($propertyName) {
// 		if (isset($this->columnAnnotations[$propertyName])) {
// 			$columnName = $this->columnAnnotations[$propertyName]->getName();
// 			unset($this->columnAnnotations[$propertyName]);
// 			return $columnName;
// 		}

// 		return StringUtils::hyphenated($propertyName);
// 	}

// 	private function addProperty(EntityProperty $property, PropertyAnnotation $propertyAnnotation = null) {
// 		if (isset($propertyAnnotation) && isset($this->propertyAnnotations[$property->getName()])) {
// 			throw $this->createAnnotationsException(
// 					SysTextUtils::get('n2n_error_persistence_orm_incompatible_anno_for_entity_property',
// 							array('class' => $this->class->getName(), 'property' => $property->getName())),
// 					array($this->propertyAnnotations[$property->getName()], $propertyAnnotation));
// 		}

// 		if (isset($this->propertyAnnotations[$property->getName()])) {
// 			throw new OrmErrorException(
// 					SysTextUtils::get('n2n_error_persistence_orm_property_was_already_initialized',
// 							array('class' => $property->getDelcaringClass()->getName(), 'property' => $property->getName())),
// 					0, E_USER_ERROR, $property->getDelcaringClass()->getFileName());
// 		}

// 		$this->propertyAnnotations[$property->getName()] = $propertyAnnotation;
// 		$this->entityModel->putProperty($property);
// 	}

// 	private function createNestedInitializationException(\Exception $previous, PropertyAnnotation $annotation = null, $message = null) {
// 		if (is_null($message)) {
// 			$message = SysTextUtils::get('n2n_error_persistence_orm_could_not_initialize_entity_properties',
// 					array('class' => $this->class->getName(), 'reason' => $previous->getMessage()));
// 		}

// 		if ($previous instanceof InvalidPropertyAccessMethodException) {
// 			$method = $previous->getMethod();
// 			if (isset($method)) {
// 				$e = new OrmErrorException($message, 0, E_USER_ERROR, $method->getFileName(), $method->getStartLine(), null, null, $previous);
// 				if (isset($annotation)) {
// 					$e->addAdditionalError($annotation->getFileName(), $annotation->getLine(), null, null,
// 							SysTextUtils::get('n2n_error_persistence_orm_exception_was_caused_by_annotation'));
// 				}
// 				return $e;
// 			}
// 		}

// 		if (isset($annotation)) {
// 			return new OrmErrorException($message, 0, E_USER_ERROR, $annotation->getFileName(), $annotation->getLine(), null, null, $previous);
// 		}

// 		return new OrmErrorException($message, 0, E_USER_ERROR, $this->class->getFileName(), $this->class->getStartLine(),
// 				$this->class->getStartLine(), $this->class->getEndLine(), $previous);
// 	}

// 	private function buildDefaultExceptionMessage(\Exception $previous = null) {
// 		return SysTextUtils::get('n2n_error_persistence_orm_configuration_error_in_entity_class',
// 				array('reason' => isset($previous) ? $previous->getMessage() : null));
// 	}

// 	private function createAnnotationsException($message, array $annotations, \Exception $previous = null) {
// 		if (null === $message) {
// 			$message = $this->builDefaultExceptionMessage($previous);
// 		}
// 		$annotation = array_pop($annotations);

// 		$e = new OrmErrorException($message, null, E_USER_ERROR, $annotation->getFileName(), $annotation->getLine(), null, null, $previous);
// 		foreach ($annotations as $annotation) {
// 			$e->addAdditionalError($annotation->getFileName(), $annotation->getLine());
// 		}
// 		return $e;
// 	}

// 	private function createException($message, \Exception $previous = null) {
// 		if (null === $message) {
// 			$message = $this->builDefaultExceptionMessage($previous);
// 		}
// 		return new OrmErrorException($message, 0, E_USER_ERROR,$this->class->getFileName(), $this->class->getStartLine());
// 	}
	
// 	private function builDefaultExceptionMessage(\Exception $previous) {
// 		return SysTextUtils::get('n2n_error_persistence_orm_entity_model_could_no_be_created',
// 				array('reason' => $previous->getMessage()));
// 	}
	
// 	const AUTO_ID_COLUMN_SUFFIX = '_id';
// 	const AUTO_INTERMEDIATE_TABLE_SEPARATOR = '_';
	
// 	public static function buildJoinTableName(EntityModel $entityModel, $propertyName) {
// 		return $entityModel->getTableName() . self::AUTO_INTERMEDIATE_TABLE_SEPARATOR
// 				. StringUtils::hyphenated($propertyName);
// 	}
	
// 	public static function buildJunctionJoinColumnName(EntityModel $entityModel) {
// 		return StringUtils::hyphenated($entityModel->getClass()->getShortName()) . self::AUTO_ID_COLUMN_SUFFIX;
// 	}
	
// 	public static function buildJoinColumnNameFromPropertyName($propertyName) {
// 		return StringUtils::hyphenated($propertyName) . self::AUTO_ID_COLUMN_SUFFIX;
// 	}
// }
