<?php

namespace n2n\persistence\orm\query\select;

use n2n\persistence\PdoStatement;
use n2n\spec\dbo\meta\data\QueryItem;
use n2n\util\type\TypeConstraint;

class EagerValueSelection implements Selection {
	private mixed $value = null;

	function __construct(private QueryItem $queryItem, private TypeConstraint $rawTypeConstraint,
			private ?EagerValueMapper $eagerValueMapper = null) {
	}

	public function getSelectQueryItems(): array {
		return [$this->queryItem];
	}

	public function bindColumns(PdoStatement $stmt, array $columnAliases): void {
		$stmt->shareBindColumn($columnAliases[0], $this->value);
	}

	function setValue(mixed $value): void {
		$this->value = $value;
	}

	public function createValueBuilder(): ValueBuilder {
		$value = $this->rawTypeConstraint?->validate($this->value);

		if ($this->eagerValueMapper !== null) {
			$value = ($this->eagerValueMapper)($value);
		}

		return new EagerValueBuilder($value);
	}
}