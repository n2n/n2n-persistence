<?php

namespace n2n\persistence\orm\store;

class ValueHashCol implements ValueHash {
	private $valueHashes = array();

	public function putValueHash($propertyString, ValueHash $valueHash) {
		$this->valueHashes[$propertyString] = $valueHash;
	}

	public function getValueHashes() {
		return $this->valueHashes;
	}

	public function containsPropertyString($propertyString) {
		return isset($this->valueHashes[$propertyString]);
	}

	public function getValueHash(string $propertyString) {
		if (isset($this->valueHashes[$propertyString])) {
			return $this->valueHashes[$propertyString];
		}

		throw new \InvalidArgumentException('No ValueHash for property \'' . $propertyString . '\' available.');
	}

	public function getSize() {
		return count($this->valueHashes);
	}

	public function matches(ValueHash $otherValueHashCol):bool {
		if (!($otherValueHashCol instanceof ValueHashCol) || $this->getSize() !== $otherValueHashCol->getSize()) {
			throw new \InvalidArgumentException('ValueHash mismatch.');
		}

		$otherValueHashCol = $otherValueHashCol->getValueHashes();
		foreach ($this->valueHashes as $propertyString => $valueHash) {
			if (!isset($otherValueHashCol[$propertyString])) {
				throw new \InvalidArgumentException('No ValueHash for property \'' . $propertyString . '\' found.');
			}

			if (!$valueHash->matches($otherValueHashCol[$propertyString])) {
				return false;
			}
		}

		return true;
	}
}