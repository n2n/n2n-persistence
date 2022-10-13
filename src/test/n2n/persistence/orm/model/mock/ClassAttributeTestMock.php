<?php

namespace n2n\persistence\orm\model\mock;

use n2n\persistence\orm\attribute\Table;
use n2n\persistence\orm\attribute\DiscriminatorColumn;
use n2n\persistence\orm\attribute\DiscriminatorValue;
use n2n\persistence\orm\attribute\Inheritance;
use n2n\persistence\orm\attribute\NamingStrategy;
use n2n\persistence\orm\InheritanceType;
use n2n\persistence\orm\model\HyphenatedNamingStrategy;

#[Table('test_table_name')]
#[DiscriminatorColumn('discColumn')]
#[DiscriminatorValue('discValue')]
#[Inheritance(InheritanceType::SINGLE_TABLE), NamingStrategy(HyphenatedNamingStrategy::class)]
class ClassAttributeTestMock extends SuperclassMock {

}