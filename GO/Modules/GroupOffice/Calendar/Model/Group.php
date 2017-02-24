<?php

/*
 * @copyright (c) 2016, Intermesh BV http://www.intermesh.nl
 * @author Michael de Hart <mdhart@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */

namespace GO\Modules\GroupOffice\Calendar\Model;

use GO\Core\Users\Model\Group as FWGroup;
use IFW\Orm\Query;
use IFW\Auth\Permissions\OwnerOnly;

/**
 * Make sure the GroupOffice user implements the needed functions for the calendar.
 * 
 * @property string $name;
 */
class Group extends FWGroup { // implements PrincipalInterface {

	public static function defineRelations() {
		self::hasMany('calendars', Calendar::class, ['id' => 'ownedBy']);
		parent::defineRelations();
	}

	/**
	 * @todo: move to FWUser??
	 * @return User
	 */
	public static function current() {
		return self::findByPk(\GO()->getAuth()->user()->group->id);
	}

	protected static function internalGetPermissions() {
		$p = new OwnerOnly();
		$p->userIdField = 'id';
		return $p;
	}

	/**
	 * When the user accepts an invitation the event will be added to this calendar
	 * @todo make this configurable instead of selecting the first one.
	 * @return Calendar the default
	 */
	public function getDefaultCalendar() {
		return Calendar::find((new Query)->select('id')->where(['ownedBy'=>$this->id]))->single();
	}
	
	public function getEmail() {
		if(empty($this->user))
			return null;
		return $this->user->email;
	}

	/**
	 * The keys of the calendars that belong to this user
	 */
//	public function getCalendarIds() {
//		$calendars = Calendar::find((new Query)->select('id')->where(['ownedBy'=>$this->id]))->all();
//		$calendarIds=[];
//		foreach($calendars as $calendar) {
//			$calendarIds[] = ['id'=>$calendar->id];
//		}
//		return $calendarIds;
//	}
	
	public function getName() {
		return $this->name;
	}

}
