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
namespace n2n\persistence;

use n2n\spec\dbo\DboStatement;

class PdoStatement implements DboStatement {
	private ?PdoLogger $logger;
	private $boundValues = array();

	function __construct(private \PDOStatement $stmt) {
	}
	
	public function getBindedValues() {
		return $this->boundValues;
	}

	public function setLogger(?PdoLogger $logger): void {
		$this->logger = $logger;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see PDOStatement::bindValue()
	 */
	public function bindValue($parameter, $value, $dataType = null): bool {
		$this->boundValues[$parameter] = $value;
		if ($dataType !== null) {
			return $this->stmt->bindValue($parameter, $value, $dataType);
		} else {
			return $this->stmt->bindValue($parameter, $value);
		}
	}
	
	public function autoBindValue($parameter, $value) {
		$dataType = null;
		if (is_int($value)) {
			$dataType = \PDO::PARAM_INT;
		} else if (is_bool($value)) {
			$dataType = \PDO::PARAM_BOOL;
		} else {
			$dataType = \PDO::PARAM_STR;
		}
		return $this->bindValue($parameter, $value, $dataType);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see PDOStatement::bindParam()
	 */
	public function bindParam($parameter, &$variable, $data_type = \PDO::PARAM_STR, $length = null, $driver_options = null): bool {
		$this->boundValues[$parameter] = $variable;
		return $this->stmt->bindParam($parameter, $variable, $data_type, $length, $driver_options);
	}
	
	private $boundParams = array();
	private $shareBoundParams = array();
	private $test;
	
	public function shareBindColumn($column, &$param) {
		if (!isset($this->shareBoundParams[$column])) {
			$this->shareBoundParams[$column] = array();
			
			$this->boundParams[$column] = null;
			$this->stmt->bindColumn($column, $this->boundParams[$column]);
		}
		
		$this->shareBoundParams[$column][] = &$param;
	}
	
	private function supplySharedBounds() {
		foreach ($this->boundParams as $columnName => $param) {
			foreach ($this->shareBoundParams[$columnName] as $key => $value) {
				$this->shareBoundParams[$columnName][$key] = $param;
			}
		}
	}
	/**
	 * (non-PHPdoc)
	 * @see PDOStatement::execute()
	 */
	public function execute(?array $params = null): bool {
		if (is_array($params)) $this->boundValues = $params;
		
		try {
			$mtime = microtime(true);
			$return = $this->stmt->execute($params);
			if (isset($this->logger)) {
				$this->logger->addPreparedExecution($this->stmt->queryString, $this->boundValues, (microtime(true) - $mtime));
			}
			
			if (!$return) {
				$err = error_get_last();
				throw new \PDOException($err['message']);
			}
			
			return $return;
		} catch (\PDOException $e) {
			throw new PdoPreparedExecutionException($e, $this->stmt->queryString, $this->boundValues);
		}
	}
	
	public function registerListener(PdoStatementListener $listener) {
		$this->listeners[spl_object_hash($listener)] = $listener;
	}
	
	public function unregisterListener(PdoStatementListener $listener) {
		unset($this->listeners[spl_object_hash($listener)]);
	}


	/**
	 * @param int $fetch_style
	 * @param int $cursor_orientation
	 * @param int $cursor_offset
	 * @return mixed will return null and not false at end to implement {@link DboStatement} correctly.
	 */
	public function fetch(int $fetch_style = \PDO::FETCH_ASSOC, int $cursor_orientation = \PDO::FETCH_ORI_NEXT, int $cursor_offset = 0): mixed {
		$return = $this->stmt->fetch($fetch_style, $cursor_orientation, $cursor_offset);
		
		if ($fetch_style == \PDO::FETCH_BOUND) {
			$this->supplySharedBounds();
		}
		
		return $return === false ? null : $return;
	}

	function fetchAll(int $mode = \PDO::FETCH_ASSOC, ...$args): array {
		return $this->stmt->fetchAll($mode, ...$args);
	}

}
