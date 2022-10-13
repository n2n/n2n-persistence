<?php

namespace n2n\persistence\orm\model\mock;

use n2n\persistence\orm\attribute\MappedSuperclass;
use n2n\persistence\orm\attribute\Id;

#[MappedSuperclass]
class SuperclassMock {
	#[Id]
	private $id;
}