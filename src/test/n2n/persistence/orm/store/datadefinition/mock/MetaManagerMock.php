<?php

namespace n2n\persistence\orm\store\datadefinition\mock;

use n2n\persistence\meta\structure\common\MetaManagerAdapter;
use n2n\persistence\meta\structure\common\DatabaseAdapter;
use n2n\persistence\meta\structure\common\ChangeRequest;
use n2n\persistence\Pdo;
use n2n\spec\dbo\meta\structure\MetaEntity;
use n2n\spec\dbo\meta\structure\Backuper;
use n2n\util\ex\UnsupportedOperationException;

/**
 * MetaManager which does not touch any database. All change requests which would have been executed on
 * {@see self::flush()} are logged instead.
 */
class MetaManagerMock extends MetaManagerAdapter {

	/**
	 * @var ChangeRequest[]
	 */
	private array $executedRequests = [];

	/**
	 * @param MetaEntity[] $persistedMetaEntities meta entities the database built by {@see self::createDatabase()}
	 * 		is initialized with.
	 */
	function __construct(Pdo $dbh, private array $persistedMetaEntities = []) {
		parent::__construct($dbh);
	}

	function createBackuper(array $metaEntities): Backuper {
		throw new UnsupportedOperationException();
	}

	protected function createCreateMetaEntityRequest(MetaEntity $metaEntity): ChangeRequest {
		return new CreateMetaEntityRequestMock($metaEntity, $this);
	}

	protected function createAlterMetaEntityRequest(MetaEntity $metaEntity): ChangeRequest {
		return new AlterMetaEntityRequestMock($metaEntity, $this);
	}

	protected function createDropMetaEntityRequest(MetaEntity $metaEntity): ChangeRequest {
		return new DropMetaEntityRequestMock($metaEntity, $this);
	}

	protected function createRenameMetaEntityRequest(MetaEntity $metaEntity, string $oldName, string $newName): ChangeRequest {
		return new RenameMetaEntityRequestMock($metaEntity, $oldName, $newName, $this);
	}

	protected function buildDatabase(): DatabaseAdapter {
		return new DatabaseMock('test_db', 'utf8mb4', $this->persistedMetaEntities, []);
	}

	function logExecutedRequest(ChangeRequest $changeRequest): void {
		$this->executedRequests[] = $changeRequest;
	}

	/**
	 * @return ChangeRequest[]
	 */
	function getExecutedRequests(): array {
		return $this->executedRequests;
	}

	/**
	 * @return string[] e.g. ['DROP some_table', 'CREATE other_table']
	 */
	function getExecutedRequestDescriptions(): array {
		return array_map(fn (ChangeRequest $r) => (string) $r, $this->executedRequests);
	}
}
