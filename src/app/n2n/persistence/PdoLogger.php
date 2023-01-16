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

use n2n\core\ext\N2nMonitor;

class PdoLogger {
	private string $dsName;
	private array $log = [];
	private bool $capturing = false;

	public function __construct(string $dataSourceName, private readonly ?float $slowQueryTime = null,
			private readonly ?N2nMonitor $n2nMonitor = null) {
		$this->dsName = $dataSourceName;
	}
	
	public function clear(): void {
		$this->log = array();
	}
	
	public function getLog(): array {
		return $this->log;
	}

	function setCapturing(bool $capturing): void {
		$this->capturing = $capturing;
	}

	public function addQuery(string $sqlStr, float $time = null): void {
		$this->addEntry(['sql' => $sqlStr, 'type' => 'query', 'time' => $time]);
	}

	public function addExecution(string $sqlStr, float $time = null): void {
		$this->addEntry(['sql' => $sqlStr, 'type' => 'execute', 'time' => $time]);
	}

	public function addPreparation(string $sqlStr, float $time = null): void {
		$this->addEntry(['sql' => $sqlStr, 'type' => 'prepare', 'time' => $time]);
	}

	public function addPreparedExecution(string $sqlStr, array $values = null, float $time = null): void {
		$this->addEntry(['sql' => $sqlStr, 'values' => $values, 'type' => 'prepared-execute', 'time' => $time]);
	}

	public function addTransactionBegin(float $time = null): void {
		$this->addEntry(['type' => 'begin transaction', 'time' => $time]);
	}

	public function addTransactionRollBack(float $time = null): void {
		$this->addEntry(['type' => 'rollback', 'time' => $time]);
	}

	public function addTransactionCommit(float $time = null): void {
		$this->addEntry(['type' => 'commit', 'time' => $time]);
	}

	public function getEntries(): array {
		return $this->log;
	}

	public function dump(): void {
		test($this->log);
	}

	private function addEntry(array $logInfo): void {
		if ($this->capturing) {
			$this->log[] = $logInfo;
		}

		if ($this->slowQueryTime === null || $this->n2nMonitor === null || !isset($logInfo['time'])
				|| $this->slowQueryTime > $logInfo['time']) {
			return;
		}

		$text = json_encode($logInfo);
		unset($logInfo['time']);
		unset($logInfo['values']);
		$hash = json_encode($logInfo);

		$this->n2nMonitor->alert(self::class, $hash, $text);

	}
}
