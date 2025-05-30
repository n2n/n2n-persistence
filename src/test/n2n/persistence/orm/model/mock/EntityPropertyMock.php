<?php

namespace n2n\persistence\orm\model\mock;

use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\model\EntityPropertyCollection;
use n2n\persistence\orm\property\EntityProperty;
use n2n\reflection\property\AccessProxy;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\EntityDataException;
use n2n\util\type\TypeUtils;
use n2n\persistence\orm\property\BasicEntityProperty;
use n2n\spec\dbo\meta\data\QueryItem;
use n2n\persistence\orm\store\operation\MergeOperation;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\query\from\MetaTreePoint;
use n2n\persistence\orm\store\action\PersistAction;
use n2n\persistence\orm\store\ValueHash;
use n2n\persistence\Pdo;
use n2n\persistence\orm\query\from\meta\TreePointMeta;
use n2n\persistence\orm\query\QueryState;
use n2n\persistence\orm\store\action\RemoveAction;
use n2n\reflection\property\PropertyAccessException;
use n2n\util\ex\UnsupportedOperationException;
use n2n\persistence\orm\criteria\compare\ColumnComparable;
use n2n\persistence\orm\query\select\Selection;
use n2n\persistence\orm\store\action\supply\SupplyJob;
use n2n\persistence\orm\store\operation\CascadeOperation;
use n2n\util\magic\MagicContext;

class EntityPropertyMock implements BasicEntityProperty {
	private $entityModel;
	private $parent;

	/**
	 * @param AccessProxy $accessProxy
	 */
	public function __construct(protected AccessProxy $accessProxy) {
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::setEntityModel()
	 */
	public function setEntityModel(EntityModel $entityModel) {
		$this->entityModel = $entityModel;
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::getEntityModel()
	 */
	public function getEntityModel() {
		if ($this->entityModel === null) {
			throw new IllegalStateException('No EntityModel assigned.');
		}

		return $this->entityModel;
	}


	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::setParent()
	 */
	public function setParent(EntityProperty $parent) {
		$this->parent = $parent;
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::getParent()
	 */
	public function getParent() {
		return $this->parent;
	}


	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::getName()
	 */
	public function getName() {
		return $this->accessProxy->getPropertyName();
	}


	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\property\EntityProperty::writeValue()
	 */
	public function writeValue(object $object, mixed $value): void {
		$this->accessProxy->setValue($object, $value);
	}

	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\EntityProperty::readValue()
	 */
	public function readValue(object $object): mixed {
		try {
			return $this->accessProxy->getValue($object);
		} catch (PropertyAccessException $e) {
			throw new EntityDataException('Failed to read value of ' . $this, 0, $e);
		}
	}

	public function toPropertyString() {
		return TypeUtils::prettyReflPropName($this->accessProxy->getProperty());
	}

	public function copy($value) {
		return $value;
	}

	public function equals($obj) {
		return $obj instanceof EntityProperty
				&& $obj->getEntityModel()->equals($this->entityModel)
				&& $obj->getName() == $this->getName();
	}

	public function hasTargetEntityModel(): bool {
		return false;
	}

	public function getTargetEntityModel(): EntityModel {
		throw new IllegalStateException('EntityProperty contains no target EntityModel: ' . $this);
	}

	public function hasEmbeddedEntityPropertyCollection(): bool {
		return false;
	}

	public function getEmbeddedEntityPropertyCollection(): EntityPropertyCollection {
		throw new IllegalStateException('EntityProperty contains no target EntityPropertyCollection: ' . $this);
	}

	public function getEmbeddedCascadeEntityObj(mixed $entityObj): EntityPropertyCollection {
		throw new IllegalStateException('EntityProperty contains no target EntityPropertyCollection: ' . $this);
	}

	public function __toString(): string {
		return (new \ReflectionClass($this))->getShortName() . ' [' . $this->accessProxy . ']';
	}

	public function valueToRep(mixed $value): string {
		throw new UnsupportedOperationException();
	}

	public function repToValue(string $rep): mixed {
		throw new UnsupportedOperationException();
	}

	public function parseValue(mixed $raw, Pdo $pdo): mixed {
		throw new UnsupportedOperationException();
	}

	public function buildRaw(mixed $value, Pdo $pdo): mixed {
		throw new UnsupportedOperationException();
	}

	public function createSelectionFromQueryItem(QueryItem $queryItem, QueryState $queryState): Selection {
		throw new UnsupportedOperationException();
	}

	public function createColumnComparableFromQueryItem(QueryItem $queryItem, QueryState $queryState): ColumnComparable {
		throw new UnsupportedOperationException();
	}

	public function createColumnComparable(MetaTreePoint $metaTreePoint, QueryState $queryState): ColumnComparable {
		throw new UnsupportedOperationException();
	}

	public function getColumnName() {
		throw new UnsupportedOperationException();
	}

	public function createQueryColumn(TreePointMeta $treePointMeta) {
		throw new UnsupportedOperationException();
	}

	public function createSelection(MetaTreePoint $metaTreePoint, QueryState $queryState): Selection {
		throw new UnsupportedOperationException();
	}

	public function mergeValue(mixed $value, bool $sameEntity, MergeOperation $mergeOperation): mixed {
		throw new UnsupportedOperationException();
	}

	public function supplyPersistAction(PersistAction $persistAction, $value, ValueHash $valueHash, ?ValueHash $oldValueHash): void {
		throw new UnsupportedOperationException();
	}

	public function supplyRemoveAction(RemoveAction $removeAction, $value, ValueHash $oldValueHash) {
		throw new UnsupportedOperationException();
	}

	public function createValueHash(mixed $value, MagicContext $magicContext): ValueHash {
		throw new UnsupportedOperationException();
	}

	public function createRepresentingQueryItem(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		throw new UnsupportedOperationException();
	}

	function ensureInit(): void {
		throw new UnsupportedOperationException();
	}

	public function prepareSupplyJob(SupplyJob $supplyJob, mixed $value, ?ValueHash $valueHash, ?ValueHash $oldValueHash): void {
	}

	public function cascade(mixed $value, int $cascadeType, CascadeOperation $cascadeOperation): void {
	}
}