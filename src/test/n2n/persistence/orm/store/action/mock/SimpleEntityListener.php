<?php

namespace n2n\persistence\orm\store\action\mock;

use n2n\context\attribute\ThreadScoped;
use n2n\persistence\orm\LifecycleEvent;

#[ThreadScoped]
class SimpleEntityListener {

	/**
	 * @var LifecycleEvent $events;
	 */
	public array $events = [];
	public ?\Closure $onPrePersist = null;
	public ?\Closure $onPreUpdate = null;

	function _prePersist(LifecycleEvent $event): void {
		$this->events[]	= $event;
		$this->onPrePersist?->__invoke($event);
	}

	function _preUpdate(LifecycleEvent $event): void {
		$this->events[]	= $event;
		$this->onPreUpdate?->__invoke($event);
	}

	function _preRemove(LifecycleEvent $event): void {
		$this->events[]	= $event;
	}
}