<?php

namespace n2n\persistence;

enum PdoBindMode: string {
	case RELEASE_ONLY = 'release-only';
	case FULL = 'full';

	function isTransactionIncluded(): bool {
		return $this === self::FULL;
	}

	function isReleaseIncluded(): bool {
		return true;
	}

	function isCloseIncluded(): bool {
		return $this === self::FULL;
	}
}