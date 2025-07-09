<?php

namespace n2n\persistence\orm\store\action\mock;

use n2n\persistence\orm\attribute\EntityListeners;

#[EntityListeners(SimpleEntityListener::class)]
class SimpleEntityMock {

	private mixed $id;
	public mixed $name = null;

	function __construct(?string $name = null) {
		$this->name = $name;
	}

	function getId(): ?int {
		return $this->id ?? null;
	}

	function setId(int $id): void {
		$this->id = $id;
	}

//	function getName(): ?string {
//		return $this->name;
//	}
//
//	function setName(string $name): void {
//		$this->name = $name;
//	}
}