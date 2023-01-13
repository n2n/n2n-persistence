<?php

namespace n2n\persistence\orm\model\mock;

use n2n\persistence\orm\property\EntityPropertyProvider;
use n2n\persistence\orm\property\ClassSetup;
use n2n\persistence\orm\attribute\Id;
use n2n\reflection\attribute\PropertyAttribute;
use n2n\reflection\property\PropertyAccessProxy;

class PropertyProviderMock implements EntityPropertyProvider {
	public function setupPropertyIfSuitable(PropertyAccessProxy $propertyAccessProxy, ClassSetup $classSetup) {
		$classSetup->provideEntityProperty(new EntityPropertyMock($propertyAccessProxy),
				array(PropertyAttribute::fromAttribute(
				$propertyAccessProxy->getProperty()->getAttributes(Id::class)[0],
				$propertyAccessProxy->getProperty())));
	}
}