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

use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\model\EntityPropertyAnalyzer;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\OrmError;
use n2n\persistence\orm\model\NamingStrategy;
use n2n\persistence\orm\model\OnFinalizeQueue;
use n2n\persistence\orm\InheritanceType;
use n2n\reflection\ReflectionUtils;
use n2n\util\ex\err\ConfigurationError;

class SetupProcess {
	private $entityModel;
	private $entityPropertyAnalyzer;
	private $onFinalizeQueue;
	protected $columnDefs = array();
	
	public function __construct(EntityModel $entityModel, EntityPropertyAnalyzer $entityPropertyAnalyzer, 
			OnFinalizeQueue $onFinalizeQueue) {
		$this->entityModel = $entityModel;
		$this->entityPropertyAnalyzer = $entityPropertyAnalyzer;
		$this->onFinalizeQueue = $onFinalizeQueue;
	}
	
	public function inherit(SetupProcess $superSetupProcess) {
		IllegalStateException::assertTrue(empty($this->columnDefs) && $this->entityModel->hasSuperEntityModel());
		
		if ($this->entityModel->getInheritanceType() === InheritanceType::SINGLE_TABLE) {
			$this->columnDefs = $superSetupProcess->columnDefs;
			return;
		}
		
		$idColumnName = $this->entityModel->getIdDef()->getEntityProperty()->getColumnName();
		if (isset($superSetupProcess->columnDefs[$idColumnName])) {
			$this->columnDefs[$idColumnName] = $superSetupProcess->columnDefs[$idColumnName];
			return;
		}
		
		throw new IllegalStateException('EntityProperty '
				. get_class($this->entityModel->getIdDef()->getEntityProperty())
				. ' has\'t registered its column and is therefore wrong implemented.');
	}
	
	/**
	 * @return EntityModel
	 */
	public function getEntityModel() {
		return $this->entityModel;
	}
	/**
	 * @throws IllegalStateException
	 * @return EntityPropertyAnalyzer
	 */
	public function getEntityPropertyAnalyzer() {
		return $this->entityPropertyAnalyzer;
	}
	/**
	 * @param string $columnName
	 * @param string $propertyString
	 * @param array $relatedComponents
	 */
	public function registerColumnName($columnName, $propertyString, array $relatedComponents) {
		if (!isset($this->columnDefs[$columnName])) {
			$this->columnDefs[$columnName] = array('propertyString' => $propertyString,
					'relatedComponents' => $relatedComponents);
			return;
		}
		
		$propertyNames = array_unique(array($this->columnDefs[$columnName]['propertyString'], $propertyString));
		$relatedComponents = array_unique(array_merge($this->columnDefs[$columnName]['relatedComponents'], $relatedComponents));

		throw self::createPropertyException('Column \'' . $columnName . '\' is used multiple times by properties \''
						. $this->columnDefs[$columnName]['propertyString'] . '\' and \'' . $propertyString . '\'', 
				null, $relatedComponents);
	}
//	/**
//	 * @return NamingStrategy
//	 */
//	public function getDefaultNamingStrategy() {
//		return $this->defaultNamingStrategy;
//	}
	/**
	 * @param string $message
	 * @param \Exception|null $causingE
	 * @param array $causingComponents
	 * @return \Error
	 */
	public static function createPropertyException($message, \Exception $causingE = null,
			array $causingComponents = array()) {
		if (0 == count($causingComponents)) {
			return new PropertyInitializationException($message, null, $causingE);
		}

		return self::createError($message, $causingComponents, $causingE);
	}
	
	public function getOnFinalizeQueue() {
		return $this->onFinalizeQueue;
	}

	private static function createError($message, array $causingComponents,
			\Exception $previous = null, $documentId = null) {
		$tps = array();
		foreach ($causingComponents as $causingComponent) {
			if ($causingComponent === null) continue;

			$fileName = $lineNo = null;
			ReflectionUtils::tp($causingComponent, $fileName, $lineNo);
			$tps[$fileName . ':' . $lineNo] = array('fileName' => $fileName,
					'line' => $lineNo);
		}

		$tp = array_shift($tps);
		$e = new ConfigurationError($message, $tp['fileName'], $tp['line'], null, null, $previous);
		foreach ($tps as $tp) {
			$e->addAdditionalError($tp['fileName'], $tp['line']);
		}
		return $e;
	}
}
