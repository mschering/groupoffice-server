<?php
/*
 * @copyright (c) 2016, Intermesh BV http://www.intermesh.nl
 * @author Michael de Hart <mdhart@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */

namespace GO\Modules\GroupOffice\Calendar\Model;

use IFW\Orm\Record;
use IFW\Auth\Permissions\ViaRelation;

/**
 * Attendee records hold attendee / guest information.
 * An attendee can be an individual (guest) or a resource (room/equipment)
 * They also hodl the guest's attendens response (RSVP)
 *
 * @property Alarms[] $alarms ringing bells about the event
 * @property Event $event The event the Attendee is attening to
 * @property Calendar $calendar The calendar the attendens to the event is shown in
 * @property User $user When a user in the system is an attendee the id gets saved
 */
class Attendee extends Record {
	
	/**
	 * foreignkey to the event
	 * @var int
	 */							
	public $eventId;

	/**
	 * foreignkey to user
	 * @var string
	 */							
	public $email;

	/**
	 * weither participation is required, option, chairman or not participating (None)
	 * @var int
	 */							
	public $role = 1;

	/**
	 * Whether the attendee has accepted, declined, delegated or is tentative.
	 * @var int
	 */							
	public $responseStatus = 1;

	/**
	 * foreignkey to the calendar
	 * @var int
	 */							
	public $calendarId;

	/**
	 * user linked to this event
	 * @var int
	 */							
	public $userId;

	// DEFINE

	static public function me() {
		$me = new self();
		$me->email = User::current()->email;
		$me->userId = User::current()->id;
		$me->setCalendar(User::current()->getDefaultCalendar());
		$me->responseStatus = ResponseStatus::Accepted;
		$event = new Event();
		$me->event = $event;
		//$me->event->attendees[] = $me;
		$me->event->organizerEmail = $me->email;
		return $me;
	}

	// OVERRIDES

	public static function tableName() {
		return 'calendar_attending_individual';
	}

	public static function internalGetPermissions() {
		return new ViaRelation('event');
	}
	
	protected static function defineRelations() {
		self::hasOne('event', Event::class, ['eventId' => 'id']);
		self::hasOne('calendar', Calendar::class, ['calendarId' => 'id']);
		self::hasMany('alarms', Alarm::class, ['eventId' => 'eventId', 'userId' => 'userId']);
		self::hasOne('user', User::class, ['userId'=> 'id']);
	}

	protected static function defineValidationRules() {
		return [
			new \IFW\Validate\ValidateEmail('email')
		];
	}

	protected function internalSave() {
		GO()->getAuth()->sudo(function(){
			$user = User::find(['email'=>$this->email])->single();
			if(!empty($user)) {
				$this->userId = $user->id;
				$this->calendarId = $user->getDefaultCalendar()->id;
			}
			
		});
		$this->event->save(); // call save to send invites&updates if needed
		
		return parent::internalSave();
	}
	
	// ATTRIBUTES
	/**
	 * True is this attendee is the organizer of the event it attends to.
	 * @return bool
	 */
	public function getIsOrganizer() {
		return $this->event->organizerEmail == $this->email;
	}

	public function addAlarms($defaultAlarms) {
		foreach($defaultAlarms as $defaultAlarm) {
			$defaultAlarm->addTo($this);
		}
	}

	/**
	 * TODO analyse rowCount performance
	 * @return type
	 */
	public function getHasAlarms() {
		if($this->userId == User::current()->id) {
			return $this->alarms->getRowCount() > 0;
		}
		return null;
	}

	public function setCalendar($calendar) {
		if(empty($calendar)) {
			return; // the user has no calendar
		}
		$this->calendarId = $calendar->id;
		if($this->isNew()) {
			$this->addAlarms($calendar->defaultAlarms);
		}
		
	}

	/**
	 * An attendee can be an Individual, Resource (or Group)
	 * @return PrincipalType
	 */
	public function getType() {
		return PrincipalInterface::Individual;
	}

	public function getName() {
		if(empty($this->user))
			return '';
		return $this->user->getName();
	}
	
	// OPERATIONS

	/**
	 * When the organizer deletes the participation, the event itself will be deleted.
	 * @param type $hard
	 * @return boolean
	 */
	protected function internalDelete($hard) {
		if(parent::internalDelete($hard)){
			if($this->getIsOrganizer()) {
				return $this->event->delete();
			}
			return true;
		}

		return false;
	}
}