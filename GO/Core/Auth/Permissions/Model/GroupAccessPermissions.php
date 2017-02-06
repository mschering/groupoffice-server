<?php
namespace GO\Core\Auth\Permissions\Model;

use IFW\Auth\Permissions\Model;
use IFW\Auth\Permissions\ViaRelation;
use IFW\Auth\UserInterface;
use IFW\Orm\Query;

/**
 * GroupAccess permissions model. Used by {@see GroupAccess}
 */
class GroupAccessPermissions extends ViaRelation {
	public function __construct($relationName) {
		parent::__construct($relationName, self::PERMISSION_CHANGE_PERMISSIONS);
	}
	
	protected function internalCan($permissionType, UserInterface $user) {
		
		if(!parent::internalCan($permissionType, $user)) {
			return false;
		}
		
		if($permissionType == self::PERMISSION_READ) {
			return true;
		}
		
		//don't edit owner record
		if(!$this->record->isNew() && $this->record->groupId == $this->record->{$this->relationName}->ownedBy) {
			return false;
		}else
		{
			return true;
		}
		
	}
	
	public function toArray($properties = null) {
		return Model::toArray($properties);
	}
	
	protected function internalApplyToQuery(Query $query, UserInterface $user) {
		
	}
}
