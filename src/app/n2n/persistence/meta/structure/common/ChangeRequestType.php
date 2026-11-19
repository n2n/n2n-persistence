<?php

namespace n2n\persistence\meta\structure\common;

enum ChangeRequestType {
	case CREATE;
	case ALTER;
	case DROP;
	case RENAME;
}
