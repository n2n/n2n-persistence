<?php
namespace n2n\persistence\orm\attribute;

use Attribute;
use n2n\io\managed\FileLocator;
use n2n\io\managed\FileManager;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManagedFile {

	/**
	 * @param string $fileManagerlookupId
	 * @param FileLocator|null $fileLocator
	 * @param bool $cascadeDelete
	 */
	public function __construct(private string $fileManagerlookupId = FileManager::TYPE_PUBLIC,
			private ?FileLocator $fileLocator = null, private bool $cascadeDelete = true) {
	}

	/**
	 * @return string
	 */
	public function getLookupId() {
		return $this->fileManagerlookupId;
	}

	/**
	 * @return FileLocator
	 */
	public function getFileLocator() {
		return $this->fileLocator;
	}

	public function isCascadeDelete() {
		return $this->cascadeDelete;
	}
}