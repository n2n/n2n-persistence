<?php

namespace n2n\persistence\orm\model;

interface EntityModelCollection {

	function getEntityModelByEntityObj(object $entityObj): EntityModel;

}