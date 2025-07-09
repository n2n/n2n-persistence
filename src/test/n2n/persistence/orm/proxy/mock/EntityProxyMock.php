<?php

namespace n2n\persistence\orm\proxy\mock;

class EntityProxyMock {

	private int $id = 3;

	public function staticReturnTest(): static {
		return $this;
	}

	function someAccessMethod(): int {
		return $this->id;
	}

	function nonAccessMethod(): void {

	}
}