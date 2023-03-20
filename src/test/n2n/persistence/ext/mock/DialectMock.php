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

class DialectMock implements Dialect {

	public function __construct() {
	}

	public function getName(): string {
		// TODO: Implement getName() method.
	}

	function createPDO(PersistenceUnitConfig $persistenceUnitConfig): \PDO {
		return new \PDO($persistenceUnitConfig->getDsnUri(), $persistenceUnitConfig->getUser(), $persistenceUnitConfig->getPassword());
	}

	public function createMetaManager(Pdo $dbh): MetaManager {
		// TODO: Implement createMetaManager() method.
	}

	public function quoteField(string $str): string {
		// TODO: Implement quoteField() method.
	}

	public function escapeLikePattern(string $pattern): string {
		// TODO: Implement escapeLikePattern() method.
	}

	public function getLikeEscapeCharacter(): string {
		// TODO: Implement getLikeEscapeCharacter() method.
	}

	public function createSelectStatementBuilder(Pdo $dbh): SelectStatementBuilder {
		// TODO: Implement createSelectStatementBuilder() method.
	}

	public function createUpdateStatementBuilder(Pdo $dbh): UpdateStatementBuilder {
		// TODO: Implement createUpdateStatementBuilder() method.
	}

	public function createInsertStatementBuilder(Pdo $dbh): InsertStatementBuilder {
		// TODO: Implement createInsertStatementBuilder() method.
	}

	public function createDeleteStatementBuilder(Pdo $dbh): DeleteStatementBuilder {
		// TODO: Implement createDeleteStatementBuilder() method.
	}

	public function createImporter(Pdo $dbh, InputStream $inputStream): Importer {
		// TODO: Implement createImporter() method.
	}

	public function getOrmDialectConfig(): OrmDialectConfig {
		// TODO: Implement getOrmDialectConfig() method.
	}

	public function isLastInsertIdSupported(): bool {
		// TODO: Implement isLastInsertIdSupported() method.
	}

	public function generateSequenceValue(Pdo $dbh, string $sequenceName): ?string {
		// TODO: Implement generateSequenceValue() method.
	}

	public function applyIdentifierGeneratorToColumn(Pdo $dbh, Column $column, string $sequenceName) {
		// TODO: Implement applyIdentifierGeneratorToColumn() method.
	}
}