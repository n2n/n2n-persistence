<?php

namespace n2n\persistence\orm\store\action\meta;

use n2n\persistence\orm\property\EntityProperty;

class ActionMetaUtil {

	function __construct(private ActionMeta $actionMeta) {

	}

	/**
	 * @param EntityProperty $entityProperty
	 * @return EntityProperty[]
	 */
	private function flattenEntityProperties(EntityProperty $entityProperty): array {
		$entityProperties = [$entityProperty];
		if (!$entityProperty->hasEmbeddedEntityPropertyCollection()) {
			return $entityProperties;
		}

		foreach ($entityProperty->getEmbeddedEntityPropertyCollection()->getEntityProperties()
				 as $entityProperty) {
			array_push($entityProperties, ...$this->flattenEntityProperties($entityProperty));
		}

		return $entityProperties;
	}

	function containsChangesFor(EntityProperty $entityProperty): bool {
		$entityProperties = $this->flattenEntityProperties($entityProperty);

		$markedEntityProperties = $this->actionMeta->getMarkedEntityProperties();
		$items = $this->actionMeta->getItems();
		foreach ($entityProperties as $entityProperty) {
			if (in_array($entityProperty, $markedEntityProperties)) {
				return true;
			}

			// probably no longer necessary to check items because SupplyJob already marks all changed EntityProperties
			foreach ($items as $item) {
				if (in_array($entityProperty, $item->getEntityProperties())) {
					return true;
				}
			}
		}

		return false;
	}

	private function isEntityPropertyPartOf(EntityProperty $entityProperty, EntityProperty $partOfEntityProperty): bool {
		do {
			if ($entityProperty === $partOfEntityProperty) {
				return true;
			}
		} while (null !== $partOfEntityProperty = $partOfEntityProperty->getParent());

		return false;
	}

	function containsChangesForAnyBut(EntityProperty $entityProperty): bool {
		foreach ($this->actionMeta->getMarkedEntityProperties() as $markedEntityProperty) {
			if (!$this->isEntityPropertyPartOf($entityProperty, $markedEntityProperty)
					&& !$this->isEntityPropertyPartOf($markedEntityProperty, $entityProperty)) {
				return true;
			}
		}

		// probably no longer necessary to check items because SupplyJob already marks all changed EntityProperties
		foreach ($this->actionMeta->getItems() as $item) {
			foreach ($item->getEntityProperties() as $changedEntityProperty) {
				if (!$this->isEntityPropertyPartOf($entityProperty, $changedEntityProperty)
						&& !$this->isEntityPropertyPartOf($changedEntityProperty, $entityProperty)) {
					return true;
				}
			}
		}

		return false;
	}
}