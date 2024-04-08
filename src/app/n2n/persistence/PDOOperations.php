<?php

namespace n2n\persistence;

class PDOOperations {

	static function beginTransaction(?PdoLogger $pdoLogger, \PDO $pdo): void {
		$mtime = microtime(true);
		$pdo->beginTransaction();
		$pdoLogger->addTransactionBegin(microtime(true) - $mtime);
	}

	/**
	 * @param PdoLogger|null $pdoLogger
	 * @param \PDO $pdo
	 * @param string $statement
	 * @return false|int
	 */
	static function exec(?PdoLogger $pdoLogger, \PDO $pdo, string $statement): false|int {
		try {
			$mtime = microtime(true);
			$stmt = $pdo->exec($statement);
			$pdoLogger->addExecution($statement, (microtime(true) - $mtime));
			return $stmt;
		} catch (\PDOException $e) {
			throw new PdoStatementException($e, $statement);
		}
	}
}
