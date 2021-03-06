<?php
namespace GO\Core\Users\Model;

use IFW\Orm\Record;
use GO\Core\Modules\Model\ModuleGroup;

/**
 * Groups are used for permissions
 * 
 * 
 * @copyright (c) 2014, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */
class UserGroup extends Record{
	
	/**
	 * 
	 * @var int
	 */							
	public $userId;

	/**
	 * 
	 * @var int
	 */							
	public $groupId;

	public static function tableName() {
		return 'auth_user_group';
	}
	
	protected static function defineRelations() {
		
		self::hasOne('user', User::class, ['userId'=>'id']);
		self::hasOne('group', Group::class, ['groupId'=>'id']);
		
		self::hasMany('groupUsers', UserGroup::class, ['groupId' => 'groupId']);
		
		self::hasOne('moduleGroup', ModuleGroup::class, ['groupId' => 'groupId']);
		
		parent::defineRelations();
	}
	
	protected static function internalGetPermissions() {
		return new \IFW\Auth\Permissions\ReadOnly();
	}
	
	protected function internalDelete($hard) {
		
		if($this->groupId == Group::ID_ADMINS) {
			throw new \IFW\Exception\Forbidden("Admins group can't be deleted!");
		}
		
		if($this->groupId == Group::ID_INTERNAL) {
			throw new \IFW\Exception\Forbidden("Internal group can't be deleted!");
		}
		
		if($this->group->userId == $this->userId) {
			throw new \IFW\Exception\Forbidden("User's personal group can't be deleted!");
		}
		
		return parent::internalDelete($hard);
	}
	
}