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
namespace n2n\persistence\ext;

use n2n\core\config\PersistenceUnitConfig;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\LazyEntityManagerFactory;
use n2n\util\type\ArgUtils;
use n2n\persistence\orm\model\EntityModelManager;
use n2n\persistence\orm\proxy\EntityProxyManager;
use n2n\util\magic\MagicContext;
use n2n\context\ThreadScoped;
use n2n\core\config\DbConfig;
use n2n\persistence\orm\model\EntityModelFactory;
use n2n\core\config\OrmConfig;
use n2n\persistence\Pdo;
use n2n\persistence\UnknownPersistenceUnitException;
use n2n\persistence\PdoPoolListener;
use n2n\core\container\TransactionManager;
use n2n\core\container\impl\AppN2nContext;
use n2n\core\ext\N2nMonitor;
use n2n\reflection\ReflectionUtils;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\PdoFactory;

class PdoPool {
	const DEFAULT_DS_NAME = 'default';

	protected array $dbhs = array();

//	private $dbhPoolListeners = array();

	function __construct(private array $persistenceUnitConfigs, private TransactionManager $transactionManager,
			private ?float $slowQueryTime, private ?N2nMonitor $n2nMonitor) {
		ArgUtils::valArray($this->persistenceUnitConfigs, PersistenceUnitConfig::class);
	}


//	/**
//	 * Creates a copy of this PdoPool for another MagicContext and optional another TransactionManager.
//	 *
//	 * @param MagicContext $magicContext
//	 * @param TransactionManager|null $transactionManager if provided already initialized pdos will not be forked.
//	 * @return PdoPool
//	 */
//	function fork(MagicContext $magicContext, TransactionManager $transactionManager = null): PdoPool {
//		$pdoPool = new PdoPool($this->persistenceUnitConfigs, $this->entityModelManager, $magicContext,
//				$transactionManager ?? $this->transactionManager,
//				$this->slowQueryTime, $this->n2nMonitor);
//
//
//		if ($transactionManager === null) {
//			$pdoPool->dbhs = $this->dbhs;
//		}
//
//		return $pdoPool;
//	}

//	private static function createFromConfig(DbConfig $dbConfig, OrmConfig $ormConfig, MagicContext $magicContext,
//			TransactionManager $transactionManager, ?float $slowQueryTime, ?N2nMonitor $n2nMonitor): PdoPool {
//
//
//
//		$entityModelManager = new EntityModelManager($ormConfig->getEntityClassNames(),
//				new EntityModelFactory($ormConfig->getEntityPropertyProviderClassNames(),
//						$ormConfig->getNamingStrategyClassName()));
//
//		return new PdoPool($persistenceUnitConfigs, $entityModelManager, $magicContext, $transactionManager,
//				$slowQueryTime, $n2nMonitor);
//
//	}
//
//	static function createFromAppN2nContext(AppN2nContext $n2nContext): PdoPool {
//		$appConfig = $n2nContext->getAppConfig();
//		return self::createFromConfig($appConfig->db(), $appConfig->orm(), $n2nContext, $n2nContext->getTransactionManager(),
//				$appConfig->error()->getMonitorSlowQueryTime(), $n2nContext->getMonitor());
//	}

	function clear(): void {
		$pdos = $this->dbhs;

		$this->dbhs = [];
//		$this->dbhPoolListeners = []

		foreach ($pdos as $pdo) {
			$pdo->close();
		}
	}

	/**
	 * @return TransactionManager
	 */
	public function getTransactionManager(): TransactionManager {
		return $this->transactionManager;
	}

	/**
	 * @return string[]
	 */
	public function getPersistenceUnitNames(): array {
		return array_keys($this->persistenceUnitConfigs);
	}

	/**
	 * @param ?string $persistenceUnitName
	 * @return Pdo
	 */
	public function getPdo(string $persistenceUnitName = null): Pdo {
		if ($persistenceUnitName === null) {
			$persistenceUnitName = self::DEFAULT_DS_NAME;
		}
		
		if (!isset($this->persistenceUnitConfigs[$persistenceUnitName])) {
			throw new UnknownPersistenceUnitException('Unknown persitence unit: ' . $persistenceUnitName);
		}
		
		if (!isset($this->dbhs[$persistenceUnitName])) {
			$this->dbhs[$persistenceUnitName] = $this->createPdo(
					$this->persistenceUnitConfigs[$persistenceUnitName]);
		}
		
		return $this->dbhs[$persistenceUnitName];
	}
	
	/**
	 * @return Pdo[]
	 */
	function getInitializedPdos() {
		return $this->dbhs;
	}

//	/**
//	 * @param string $persistenceUnitName
//	 * @param Pdo $pdo
//	 * @throws \InvalidArgumentException
//	 */
//	function setPdo(string $persistenceUnitName, Pdo $pdo) {
//		if ($persistenceUnitName === null) {
//			$persistenceUnitName = self::DEFAULT_DS_NAME;
//		}
//
//		if (isset($this->dbhs[$persistenceUnitName])) {
//			throw new \InvalidArgumentException('Pdo for persistence unit already initialized: ' . $persistenceUnitName);
//		}
//
//		$this->dbhs[$persistenceUnitName] = $pdo;
//	}


	/**
	 * @param PersistenceUnitConfig $persistenceUnitConfig
	 * @return Pdo
	 */
	private function createPdo(PersistenceUnitConfig $persistenceUnitConfig): Pdo {
		return PdoFactory::createFromPersistenceUnitConfig($persistenceUnitConfig, $this->transactionManager,
				$this->slowQueryTime, $this->n2nMonitor);
	}
	

	
//	public function registerListener(PdoPoolListener $dbhPoolListener) {
//		$this->dbhPoolListeners[spl_object_hash($dbhPoolListener)] = $dbhPoolListener;
//	}
//
//	public function unregisterListener(PdoPoolListener $dbhPoolListener) {
//		unset($this->dbhPoolListeners[spl_object_hash($dbhPoolListener)]);
//	}

}
