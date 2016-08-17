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
namespace n2n\persistence\orm\nql;

use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\model\EntityModelManager;
use n2n\util\StringUtils;

class ParsingState {
	
	private $entityModelManager;
	private $expressionParser;
	private $queryString;
	private $params;
	
	private $entityClasses = array();
	private $tokenizerStack = array();
	
	public function __construct(EntityModelManager $entityModelManager, $rootQueryString, array $params) {
		$this->entityModelManager = $entityModelManager;
		$this->expressionParser = new ExpressionParser($this);
		$this->queryString = $rootQueryString;
		$this->params = $params;
	}
	
	public function getParams() {
		return $this->params;
	}
	/**
	 *  
	 * @param string $nql
	 * @return NqlTokenizer
	 */
	public function createTokenizer($nql) {
		$this->tokenizerStack[] = new NqlTokenizer($nql);
		return end($this->tokenizerStack);
	}
	
	public function popTokenizer() {
		if (empty($this->tokenizerStack)) {
			throw new IllegalStateException('Tokenizer stack empty');
		}
		array_pop($this->tokenizerStack);
	}
	
	public function createNqlParseException($message, $donePart = null, \Exception $previous = null) {
		return new NqlParseException($message . '. Position: \'' 
						. $donePart . implode('', array_reverse($this->tokenizerStack)) . '\'', 
				0, $previous, $this->queryString, $this->params);
	}
	
	public function parse($expression) {
		try {
			return $this->expressionParser->parse($expression);
		} catch (\InvalidArgumentException $e) {
			throw $this->createNqlParseException('Invalid expression' . $expression, null, $e);
		}
	}
	
	public function getClassForEntityName($entityName) {
		$entityName = NqlUtils::removeQuotationMarks($entityName);
		
		if (StringUtils::startsWith('\\', $entityName)) {
			$entityName = ltrim($entityName, '\\');
		}
		
		if (isset($this->entityClasses[$entityName])) return $this->entityClasses[$entityName];
	
		$class = null;
		foreach ($this->entityModelManager->getEntityClasses() as $entityClass) {
// 			test($entityName . '==' . $entityClass->getName());
			if (!StringUtils::endsWith($entityName, $entityClass->getName())) continue;

			if ($class === null) {
				$class = $entityClass;
				continue;
			}
			
			throw $this->createNqlParseException('More than one registered Entity with name: ' . $entityName);
		}
	
		if (null === $class) {
			throw $this->createNqlParseException('No registered Entity with name: ' . $entityName);
		}
	
		return $this->entityClasses[$entityName] = $class;
	}
}
