<?php

namespace n2n\persistence\orm\model;

use PHPUnit\Framework\TestCase;
use n2n\persistence\orm\model\mock\ClassAttributeTestMock;
use n2n\persistence\orm\model\mock\PropertyProviderMock;
use n2n\persistence\orm\InheritanceType;

class EntityModelFactoryTest extends TestCase {
	private EntityModelFactory $emf;

	public function setUp(): void {
		$this->emf = new EntityModelFactory([PropertyProviderMock::class]);
		$this->emf->setOnFinalizeQueue(new OnFinalizeQueue($this->createMock(EntityModelManager::class)));
	}

	public function testClassAttributes() {
		$entityModel = $this->emf->create(new \ReflectionClass(ClassAttributeTestMock::class));
		// NamingStrategyTest:
		$this->assertEquals('classattributetestmock', $entityModel->getTableName());
		$this->assertEquals('discValue', $entityModel->getDiscriminatorValue());
		$this->assertEquals('discColumn', $entityModel->getDiscriminatorColumnName());
		$this->assertEquals(InheritanceType::SINGLE_TABLE, $entityModel->getInheritanceType());
	}
}