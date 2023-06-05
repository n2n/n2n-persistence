<?php

namespace n2n\persistence\orm\proxy\mock;

class EntityProxyMock {

	private int $id;

	public function staticReturnTest(): static {
		return $this;
	}

}