<?php

namespace GO\Core\Modules\Model;

use IFW\Auth\Permissions\Model;
use IFW\Auth\UserInterface;
use IFW\Orm\Query;

/**
 * Module permissions
 * 
 * Modules store their permissions in a separate model {@see ModuleGroup}.
 * 
 * Modules can define differnt actions to grant permissions on with constants 
 * in their manager class. The constants are prefixed with ACTION_. 
 * eg.:
 * 
 * <code>
 * const ACTION_CREATE_CONTACTS = 'createContacts';
 * </code>
 * 
 * These can be checked like this:
 * 
 * <code>
 * $can = \GO\Modules\Contacts\ContactsModule::model()
 *   ->getPermissions()
 *   ->can(\GO\Modules\Contacts\ContactsModule::ACTION_CREATE_CONTACTS);
 * </code>
 */
class ModulePermissions extends Model {
	
	private $managerPermissions;
	
	private function getManagerPermissions() {
		
		if(!isset($this->managerPermissions)) {
			$this->managerPermissions = $this->record->manager()->getPermissions();
//			$this->managerPermissions->setRecord($this->record);
		}
		return $this->managerPermissions;
	}

	protected function internalCan($permissionType, UserInterface $user) {
		return $this->getManagerPermissions()->can($permissionType, $user);
	}
	
	public function toArray($properties = null) {
		return $this->getManagerPermissions()->toArray($properties);
	}
	
	public function getPermissionTypes() {
		return $this->getManagerPermissions()->getPermissionTypes();
	}
	

	protected function internalApplyToQuery(Query $query, UserInterface $user) {
		$query->distinct()
						->joinRelation('groups.userGroup', false)
						->where(['userGroup.userId' => $user->id()]);
	}
	

}