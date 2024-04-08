<?php

namespace n2n\persistence\ext\mock;

use n2n\persistence\meta\Dialect;
use n2n\core\config\PersistenceUnitConfig;
use n2n\persistence\meta\MetaManager;
use n2n\persistence\meta\data\UpdateStatementBuilder;
use n2n\persistence\meta\structure\Column;
use n2n\util\io\stream\InputStream;
use n2n\persistence\meta\data\InsertStatementBuilder;
use n2n\persistence\Pdo;
use n2n\persistence\meta\data\SelectStatementBuilder;
use n2n\persistence\meta\data\DeleteStatementBuilder;
use n2n\persistence\meta\data\Importer;
use n2n\persistence\meta\OrmDialectConfig;
use n2n\util\ex\UnsupportedOperationException;
use n2n\persistence\PdoLogger;
use n2n\persistence\PDOOperations;

class DialectMock implements Dialect {


	public function __construct(private PersistenceUnitConfig $persistenceUnitConfig) {
	}

	public function getName(): string {
		throw new UnsupportedOperationException();
	}

	function createPDO(PdoLogger $pdoLogger = null): \PDO {
		return new \PDO($this->persistenceUnitConfig->getDsnUri(), $this->persistenceUnitConfig->getUser(),
				$this->persistenceUnitConfig->getPassword(),
				[\PDO::ATTR_PERSISTENT => $this->persistenceUnitConfig->isPersistent()]);
	}

	public function createMetaManager(Pdo $dbh): MetaManager {
		throw new UnsupportedOperationException();
	}

	public function quoteField(string $str): string {
		throw new UnsupportedOperationException();
	}

	public function escapeLikePattern(string $pattern): string {
		throw new UnsupportedOperationException();
	}

	public function getLikeEscapeCharacter(): string {
		throw new UnsupportedOperationException();
	}

	public function createSelectStatementBuilder(Pdo $dbh): SelectStatementBuilder {
		throw new UnsupportedOperationException();
	}

	public function createUpdateStatementBuilder(Pdo $dbh): UpdateStatementBuilder {
		throw new UnsupportedOperationException();
	}

	public function createInsertStatementBuilder(Pdo $dbh): InsertStatementBuilder {
		throw new UnsupportedOperationException();
	}

	public function createDeleteStatementBuilder(Pdo $dbh): DeleteStatementBuilder {
		throw new UnsupportedOperationException();
	}

	public function createImporter(Pdo $dbh, InputStream $inputStream): Importer {
		throw new UnsupportedOperationException();
	}

	public function getOrmDialectConfig(): OrmDialectConfig {
		throw new UnsupportedOperationException();
	}

	public function isLastInsertIdSupported(): bool {
		throw new UnsupportedOperationException();
	}

	public function generateSequenceValue(Pdo $dbh, string $sequenceName): ?string {
		throw new UnsupportedOperationException();
	}

	public function applyIdentifierGeneratorToColumn(Pdo $dbh, Column $column, string $sequenceName) {
		throw new UnsupportedOperationException();
	}

	public array $beginTransactionCalls = [];

	function beginTransaction(\PDO $pdo, bool $readOnly, PdoLogger $pdoLogger = null): void {
		$this->beginTransactionCalls[] = ['method' => 'beginTransaction', 'readOnly' => $readOnly];

		PDOOperations::beginTransaction($pdoLogger, $pdo);
	}
}