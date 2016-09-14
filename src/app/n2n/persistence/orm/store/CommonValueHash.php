<?php
namespace n2n\persistence\orm\store;

class CommonValueHash implements ValueHash {
	private $hash;
	
	/**
	 * @param mixed $hash
	 */
	public function __construct($hash) {
		$this->hash = $hash;
	}
	
	/**
	 * @return mixed
	 */
	public function getHash() {
		return $this->hash;
	}
	
	/**
	 * @param mixed $hash
	 */
	public function setHash($hash) {
		$this->hash = $hash;
	}
	
	/**
	 * @param ValueHash $valueHash
	 * @return boolean
	 */
	public function matches(ValueHash $valueHash): bool {
		return $this->hash === $valueHash->getHash();
	}
}