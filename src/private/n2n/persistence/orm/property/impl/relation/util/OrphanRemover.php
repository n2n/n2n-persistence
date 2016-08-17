<?php
/*
 * Copyright (c) 2012-2016, Hofmänner New Media.
 * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS FILE HEADER.
 *
 * This file is part of the N2N FRAMEWORK.
 *
 * The N2N FRAMEWORK is free software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * N2N is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
 * the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details: http://www.gnu.org/licenses/
 *
 * The following people participated in this project:
 *
 * Andreas von Burg.....: Architect, Lead Developer
 * Bert Hofmänner.......: Idea, Community Leader, Marketing
 * Thomas Günther.......: Developer, Hangar
 */
namespace n2n\persistence\orm\property\impl\relation\util;

use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\store\action\supply\SupplyJob;

class OrphanRemover {
	private $supplyJob;
	private $targetEntityModel;
	private $actionMarker;
	
	public function __construct(SupplyJob $supplyJob, EntityModel $targetEntityModel, ActionMarker $actionMarker) {
		$this->supplyJob = $supplyJob;
		$this->targetEntityModel = $targetEntityModel;
		$this->actionMarker = $actionMarker;
	}
	
	
	
	public function reportCandidateByIdReps(array $orphanIdReps) {
		foreach ($orphanIdReps as $orphanIdRep) {
			$this->reportCandidateByIdRep($orphanIdRep);
		}
	}
	/**
	 * @param mixed $orphanIdRep
	 * @return \n2n\persistence\orm\store\action\RemoveAction
	 */
	public function reportCandidateByIdRep($orphanIdRep) {
		$actionQueue = $this->supplyJob->getActionQueue();
		$orphanEntity = $actionQueue->getEntityManager()->getPersistenceContext()
				->getManagedEntityByIdRep($this->targetEntityModel, $orphanIdRep);
		if ($orphanEntity === null) return;
		
		$persistAction = $actionQueue->getPersistAction($orphanEntity);
		if (!$this->actionMarker->reportOrphanCandidate($persistAction)) return;
		
		$that = $this;
		$this->supplyJob->executeWhenInitialized(function () use ($that, $orphanEntity, $persistAction) {
			if ($that->actionMarker->isOrphanUsed($persistAction)) return;
			
			$persistAction->getActionQueue()->getOrCreateRemoveAction($orphanEntity);
		});
		$this->supplyJob->executeOnReset(function () use ($that, $persistAction) {
			$this->actionMarker->resetOrphan($persistAction);
		});
	}

	public function releaseCandiate($entity) {
		$this->actionMarker->useOrphan($this->supplyJob->getActionQueue()
				->getPersistAction($entity));
	}
}
