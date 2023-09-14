<?php

namespace n2n\persistence\meta\data;

enum LockMode {
	case PESSIMISTIC_WRITE;
	case PESSIMISTIC_WRITE_NOWAIT;
	case PESSIMISTIC_WRITE_SKIP_LOCKED;
}