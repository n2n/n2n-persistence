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

use n2n\reflection\property\PropertiesAnalyzer;
use n2n\reflection\ReflectionContext;
use n2n\impl\persistence\orm\property\ScalarEntityProperty;
use n2n\persistence\orm\property\ClassSetup;
use n2n\persistence\orm\attribute\AttributeOverrides;
use n2n\persistence\orm\attribute\Transient;
use n2n\persistence\orm\attribute\MappedSuperclass;

class EntityPropertyAnalyzer {
	private $class;
	private $entityPropertyProviders;
	
	private $currentPropertyAccessProxy;
	
	public function __construct(array $entityPropertyProviders) {
		$this->entityPropertyProviders = $entityPropertyProviders;
	}
	
	public function analyzeClass(ClassSetup $classSetup) {
		$class = $classSetup->getClass();
		$propertiesAnalyzer = new PropertiesAnalyzer($class, true);
		$attributeSet = $classSetup->getAttributeSet();
		
		$attrAttributeOverrides = $attributeSet->getClassAttribute(AttributeOverrides::class);
		if (null !== $attrAttributeOverrides) {
			$classSetup->addAttributeOverrides($attrAttributeOverrides->getInstance());
		}
		
		foreach ($propertiesAnalyzer->analyzeProperties(true, false) as $propertyAccessProxy) {
			$propertyAccessProxy->setForcePropertyAccess(true);
			
			$propertyName = $propertyAccessProxy->getPropertyName();
			if (null !== $attributeSet->getPropertyAttribute($propertyName, Transient::class)) {
				continue;
			}
				
			foreach ($this->entityPropertyProviders as $entityPropertyProvider) {
				if ($classSetup->containsEntityPropertyName($propertyName)) break;
				$entityPropertyProvider->setupPropertyIfSuitable($propertyAccessProxy, $classSetup);
			}
				
			if ($classSetup->containsEntityPropertyName($propertyName)) continue;
			
			$classSetup->provideEntityProperty(new ScalarEntityProperty($propertyAccessProxy,
					$classSetup->requestColumn($propertyName)));
		}
		
		$superClass = $class->getParentClass();
		// stupid return type bool
		if ($superClass === false) return;

		$superAttributeSet = ReflectionContext::getAttributeSet($superClass);
		$attrMappedSuperClass = $superAttributeSet->getClassAttribute(MappedSuperclass::class);
		if (null === $attrMappedSuperClass) return;

		$superClassSetup = new ClassSetup($classSetup->getSetupProcess(), $superClass,
				$classSetup->getNamingStrategy(), $classSetup);

		if (null !== $attrAttributeOverrides) {
			$superClassSetup->addAttributeOverrides($attrAttributeOverrides->getInstance());
		}

		$this->analyzeClass($superClassSetup);
	}
}
