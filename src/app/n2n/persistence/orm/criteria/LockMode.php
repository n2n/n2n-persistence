<?php

namespace n2n\persistence\orm\criteria;


use n2n\spec\dbo\meta\data\QueryLockMode;

enum LockMode {
	case PESSIMISTIC_WRITE;
	case PESSIMISTIC_WRITE_NOWAIT;
	case PESSIMISTIC_WRITE_SKIP_LOCKED;

	function toQueryLockMode(): QueryLockMode {
		return match ($this) {
			self::PESSIMISTIC_WRITE => QueryLockMode::FOR_UPDATE,
			self::PESSIMISTIC_WRITE_NOWAIT => QueryLockMode::FOR_UPDATE_NOWAIT,
			self::PESSIMISTIC_WRITE_SKIP_LOCKED => QueryLockMode::FOR_UPDATE_SKIP_LOCKED,
		};
	}
}