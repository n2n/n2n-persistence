<?php

namespace n2n\persistence\orm\model\mock;

use n2n\persistence\orm\attribute\DiscriminatorColumn;
use n2n\persistence\orm\attribute\DiscriminatorValue;
use n2n\persistence\orm\attribute\Inheritance;
use n2n\persistence\orm\attribute\NamingStrategy;
use n2n\persistence\orm\InheritanceType;
use n2n\persistence\orm\attribute\EntityListeners;
use n2n\persistence\orm\model\LowercasedNamingStrategy;

#[DiscriminatorColumn('discColumn')]
#[DiscriminatorValue('discValue')]
#[Inheritance(InheritanceType::SINGLE_TABLE), NamingStrategy(LowercasedNamingStrategy::class)]
#[EntityListeners(EntityListenerMock::class)]
class ClassAttributeTestMock extends SuperclassMock {

}