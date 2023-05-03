<?php

namespace n2n\persistence\orm\model\mock;

use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\model\EntityPropertyCollection;
use n2n\persistence\orm\property\EntityProperty;
use n2n\reflection\property\AccessProxy;
use n2n\util\ex\IllegalStateException;
use n2n\reflection\ReflectionException;
use n2n\persistence\orm\EntityDataException;
use n2n\util\type\TypeUtils;
use n2n\persistence\orm\property\BasicEntityProperty;
use n2n\persistence\meta\data\QueryItem;
use n2n\persistence\orm\store\operation\MergeOperation;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\query\from\MetaTreePoint;
use n2n\persistence\orm\store\action\PersistAction;
use n2n\persistence\orm\store\ValueHash;
use n2n\persistence\Pdo;
use n2n\persistence\orm\query\from\meta\TreePointMeta;
use n2n\persistence\orm\query\QueryState;
use n2n\persistence\orm\store\action\RemoveAction;

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
	public function writeValue($object, $value) {
		$this->accessProxy->setValue($object, $value);
	}

	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\EntityProperty::readValue()
	 */
	public function readValue($object) {
		try {
			return $this->accessProxy->getValue($object);
		} catch (ReflectionException $e) {
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

	public function valueToRep($value): string {
		// TODO: Implement valueToRep() method.
	}

	public function repToValue(string $rep) {
		// TODO: Implement repToValue() method.
	}

	public function parseValue($raw, Pdo $pdo) {
		// TODO: Implement parseValue() method.
	}

	public function buildRaw($value, Pdo $pdo) {
		// TODO: Implement buildRaw() method.
	}

	public function createSelectionFromQueryItem(QueryItem $queryItem, QueryState $queryState) {
		// TODO: Implement createSelectionFromQueryItem() method.
	}

	public function createColumnComparableFromQueryItem(QueryItem $queryItem, QueryState $queryState) {
		// TODO: Implement createColumnComparableFromQueryItem() method.
	}

	public function createColumnComparable(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		// TODO: Implement createColumnComparable() method.
	}

	public function getColumnName() {
		// TODO: Implement getColumnName() method.
	}

	public function createQueryColumn(TreePointMeta $treePointMeta) {
		// TODO: Implement createQueryColumn() method.
	}

	public function createSelection(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		// TODO: Implement createSelection() method.
	}

	public function mergeValue($value, $sameEntity, MergeOperation $mergeOperation) {
		// TODO: Implement mergeValue() method.
	}

	public function supplyPersistAction(PersistAction $persistingJob, $value, ValueHash $valueHash, ?ValueHash $oldValueHash) {
		// TODO: Implement supplyPersistAction() method.
	}

	public function supplyRemoveAction(RemoveAction $removeAction, $value, ValueHash $oldValueHash) {
		// TODO: Implement supplyRemoveAction() method.
	}

	public function createValueHash($value, EntityManager $em): ValueHash {
		// TODO: Implement createValueHash() method.
	}

	public function createRepresentingQueryItem(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		// TODO: Implement createRepresentingQueryItem() method.
	}

	function ensureInit(): void {
		// TODO: Implement ensureInit() method.
	}
}