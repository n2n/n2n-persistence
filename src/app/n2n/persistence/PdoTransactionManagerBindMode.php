<?php

namespace n2n\persistence;

enum PdoTransactionManagerBindMode {
	case RELEASE_ONLY;
	case FULL;

	function isTransactionIncluded(): bool {
		return $this === self::FULL;
	}

	function isReleaseIncluded(): bool {
		return true;
	}
}