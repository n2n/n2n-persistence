<?php

namespace n2n\persistence;

use n2n\core\config\PersistenceUnitConfig;
use n2n\reflection\ReflectionUtils;
use n2n\util\ex\IllegalStateException;
use n2n\core\container\TransactionManager;
use n2n\core\ext\N2nMonitor;

class PdoFactory {

	static function createFromPersistenceUnitConfig(PersistenceUnitConfig $persistenceUnitConfig,
			?TransactionManager $transactionManager = null, ?float $slowQueryTime = null, ?N2nMonitor $n2nMonitor = null,
			PdoBindMode $pdoTransactionManagerBindMode = PdoBindMode::FULL): Pdo {
		$dialectClass = ReflectionUtils::createReflectionClass($persistenceUnitConfig->getDialectClassName());
		if (!$dialectClass->implementsInterface('n2n\\persistence\\meta\\Dialect')) {
			throw new \InvalidArgumentException('Dialect class must implement n2n\\persistence\\meta\\Dialect: '
					. $dialectClass->getName());
		}

		$dialect = IllegalStateException::try(fn () => $dialectClass->newInstance($persistenceUnitConfig));

		return new Pdo($persistenceUnitConfig->getName(), $dialect, $transactionManager, $slowQueryTime,
				$n2nMonitor, $pdoTransactionManagerBindMode);
	}
}