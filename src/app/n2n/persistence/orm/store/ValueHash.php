<?php
namespace n2n\persistence\orm\store;

interface ValueHash {
	/**
	 * @param ValueHash $valueHash
	 * @return bool
	 * @throws \InvalidArgumentException if passed ValueHash is not compatible (e. g. if it is from an other 
	 * EntityProperty).
	 */
	public function matches(ValueHash $valueHash): bool;
}

