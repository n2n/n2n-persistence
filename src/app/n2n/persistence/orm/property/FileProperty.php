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
namespace n2n\persistence\orm\property;

use n2n\io\managed\FileManager;
use n2n\util\StringUtils;
use n2n\io\managed\impl\SimpleFileLocator;
use n2n\reflection\property\AccessProxy;
use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\store\MappingJob;
use n2n\reflection\property\TypeConstraint;
use n2n\io\fs\ClonedFile;
use n2n\persistence\orm\store\RemoveAction;
use n2n\core\N2N;
use n2n\persistence\orm\store\action\PersistAction;
use n2n\persistence\orm\annotation\File as FileAnnotation;

class FileProperty extends ColumnPropertyAdapter {
	private $fileManagerType;
	private $fileLocator;
	private $fileManager;
	
	public function __construct(EntityModel $entityModel, AccessProxy $accessProxy, 
			$columnName, FileAnnotation $fileAnnotaiton = null) {
		parent::__construct($entityModel, $accessProxy, $columnName);
		
		if (isset($fileAnnotaiton)) {
			$this->fileManagerType = $fileAnnotaiton->getManager();
			$this->fileLocator = $fileAnnotaiton->getLocator();
		}
		
		if ($this->fileLocator !== null) {
			$this->fileLocator = new SimpleFileLocator(
					StringUtils::hyphenated($entityModel->getTableName(), false));
		}
		
		$accessProxy->setConstraint(TypeConstraint::createSimple('n2n\io\managed\File'));
	}
	
	private function getFileManager() {
		if (isset($this->fileManager)) {
			return $this->fileManager;
		}
		
		if (isset($this->fileManagerType)) {
			return $this->fileManager = N2N::getLookupManager()->lookup($this->fileManagerType);
		}
		
		return $this->fileManager = N2N::getLookupManager()->lookup(FileManager::COMMON_PUBLIC);
	}

	public function mapValue(MappingJob $mappingJob, \ArrayObject $rawDataMap, \ArrayObject $mappedValues) {
		$mappedValues->offsetSet($this->getName(), $this->getFileManager()->getByQualifiedName($rawDataMap[$this->getColumnName()]));
	}

	public function supplyPersistAction($mappedValue, PersistAction $persistingJob) {
		$columnName = $this->getColumnName();

		$fileManager = $this->getFileManager();
		$fileLocator = $this->fileLocator;
		
		$that = $this;
		$persistingJob->executeAtStart(function(PersistAction $persistingJob) use ($that, $fileManager, $fileLocator, $mappedValue, $columnName) {
			$oldDataSet = $persistingJob->getOldRawDataMap();
			
			$oldQualifiedName = null;
			if (isset($oldDataSet)) {
				$oldQualifiedName = $oldDataSet->offsetGet($columnName);
			}
			
			if (isset($oldQualifiedName) && isset($mappedValue) && $mappedValue->isManaged() 
					&& $fileManager->equals($mappedValue->getFileManager())
					&& $oldQualifiedName === $mappedValue->getQualifiedName()) {
				$persistingJob->getMeta()->setRawValue($that->getEntityModel(), $that->getColumnName(), $oldQualifiedName);
				return;
			}
			
			if (isset($oldQualifiedName)) {
				$oldFile = $fileManager->getByQualifiedName($oldQualifiedName);
				if (isset($oldFile)) $oldFile->moveToTmp();
			}
			
			if (isset($mappedValue)) {
				$persistingJob->getMeta()->setRawValue($that->getEntityModel(), $columnName, 
						$fileManager->persist($mappedValue, $fileLocator));
				return;
			}
			
			$persistingJob->getMeta()->setRawValue($that->getEntityModel(), $columnName, null);
		});
	}
	
	public function supplyRemoveAction($mappedValue, RemoveAction $removingJob) {
		if (!isset($mappedValue)) return;
		
		$removingJob->executeAtStart(function(RemoveAction $removingJob) use ($mappedValue) {
			$mappedValue->moveToTmp();
		});
	}
	
// 	public function copy($mappedValue) {
// 		if (isset($mappedValue)) {
// 			return new ClonedFile($mappedValue);
// 		}
// 		return null;
// 	}
	
	public static function areConstraintsTypical(TypeConstraint $constraints = null) {
		return isset($constraints) && !is_null($constraints->getParamClass()) 
				&& $constraints->getParamClass()->getName() == 'n2n\io\managed\File' && !$constraints->isArray();
	}
}
