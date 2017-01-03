<?php

namespace GO\Core\Users\Model;

use DateTime;
use Exception;
use GO\Core\Auth\Browser\Model\Token;
use GO\Core\Model\Session;
use GO\Core\Orm\Record;
use GO\Core\Users\Model\UserPermissions;
use GO\Modules\Contacts\Model\Contact;
use IFW;
use IFW\Auth\UserInterface;
use IFW\Orm\Query;
use IFW\Validate\ValidatePassword;

/**
 * User model
 *
 * 
 * @property Contact $contact
 *
 * @property Group[] $groups The groups of the user is a member off.
 * @property Group $group The group of the user. Every user get's it's own group for sharing.
 * @property Session[] $sessions The sessions of the user.
 * @property Token[] $tokens The authentication tokens of the user.
 *
 * @copyright (c) 2014, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */
class User extends Record implements UserInterface {
	
	/**
	 * Primary key of the model.
	 * @var int
	 */							
	public $id;

	/**
	 * 
	 * @var bool
	 */							
	public $deleted = false;

	/**
	 * Disables the user from logging in
	 * @var bool
	 */							
	public $enabled = true;

	/**
	 * 
	 * @var string
	 */							
	public $username;

	/**
	 * If the password hash is set to null it's impossible to login.
	 * @var string
	 */							
	protected $password;

	/**
	 * Digest of the password used for digest auth. (Deprecated?)
	 * @var string
	 */							
	protected $digest;

	/**
	 * 
	 * @var \DateTime
	 */							
	public $createdAt;

	/**
	 * 
	 * @var \DateTime
	 */							
	public $modifiedAt;

	/**
	 * 
	 * @var int
	 */							
	public $loginCount = 0;

	/**
	 * 
	 * @var \DateTime
	 */							
	public $lastLogin;

	/**
	 * E-mail address of the user. The system uses this for notifications.
	 * @var string
	 */							
	public $email;

	/**
	 * E-mail address of the user. The system uses this for password recovery.
	 * @var string
	 */							
	public $emailSecondary;

	/**
	 * 
	 * @var string
	 */							
	public $photoBlobId;

	use \GO\Core\Blob\Model\BlobNotifierTrait;
	
	const LOG_ACTION_LOGIN = 'login';
	
	const LOG_ACTION_LOGOUT = 'logout';
	
	/**
	 * Fires before login
	 * 
	 * @param string $username
	 * @param string $password
	 * @param boolean $count
	 */
	const EVENT_BEFORE_LOGIN = 0;
	
	/**
	 * Fires after successful login
	 * 
	 * @param User $user
	 */
	const EVENT_AFTER_LOGIN = 1;
	
	/**
	 * Non admin users must verify their password before they can set the password.
	 * 
	 * @var boolean 
	 */
	private $passwordVerified;
	
	
	/**
	 * Cache value for isAdmin()
	 * 
	 * @var bool 
	 */
	private $isAdmin;

	/**
	 *
	 * {@inheritdoc}
	 */
	protected static function defineValidationRules() {
		return [
//				new ValidatePassword('password'),
				new \IFW\Validate\ValidateEmail('email'),
				new \IFW\Validate\ValidateEmail('emailSecondary')
		];
	}
	

	public static function getColumns() {
		$columns = parent::getColumns();		
		$columns['password']->trimInput = false;
		
		return $columns;		
	}
	
	protected static function internalGetPermissions() {
		return new UserPermissions();
	}
	
	public function id() {
		return $this->id;
	}
	
	public static function tableName() {
		return 'auth_user';
	}

	/**
	 *
	 * {@inheritdoc}
	 */
	public static function defineRelations() {		
		
		self::hasMany('groups', Group::class, ["id" =>"userId"])
				->via(UserGroup::class, ['groupId' => 'id']);
				
		self::hasMany('userGroup', UserGroup::class, ['id'=>"userId"]);
		self::hasOne('group', Group::class, ['id'=>'userId']);
		self::hasMany('tokens', Token::class, ["id"=>"userId"]);					
		
		parent::defineRelations();
	}

	/**
	 * Logs a user in.
	 *
	 * @param string $username
	 * @param string $password
	 * @return User|bool
	 */
	public static function login($username, $password, $count = true) {
		
		if(self::fireStaticEvent(self::EVENT_BEFORE_LOGIN, $username, $password, $count) === false) {
			return false;
		}
		
		$user = User::find(['username' => $username])->single();
		
		$success = true;

		if (!$user) {
			$success = false;
		} elseif (!$user->enabled) {
			GO()->debug("LOGIN: User " . $username . " is disabled");
			$success = false;
		} elseif (!$user->checkPassword($password)) {
			GO()->debug("LOGIN: Incorrect password for " . $username);
			$success = false;
		}

		$str = "LOGIN ";
		$str .= $success ? "SUCCESS" : "FAILED";
		$str .= " for user: \"" . $username . "\" from IP: ";
		
		if (isset($_SERVER['REMOTE_ADDR'])) {
			$str .= $_SERVER['REMOTE_ADDR'];
		}else {
			$str .= 'unknown';
		}
		
		if (!$success) {
			return false;
		} else {
			GO()->getAuth()->setCurrentUser($user);
			if($count) {
				$user->loginCount++;
				$user->lastLogin = new DateTime();
				if(!$user->save()) {
					throw new Exception("Could not save user in login");
				}
			}
			
			self::fireStaticEvent(self::EVENT_AFTER_LOGIN, $user);
			
			return $user;
		}
	}
	
	public function internalValidate() {
		
		if(!empty($this->password) && $this->isModified('password')){			
			$this->digest = md5($this->username . ":" . GO()->getConfig()->productName . ":" . $this->password);
			$this->password = password_hash($this->password, PASSWORD_DEFAULT);
		}
			
		return parent::internalValidate();
	}

	private function logSave() {
		if($this->isModified('loginCount')) {
			GO()->log(self::LOG_ACTION_LOGIN, $this->username, $this);
		}else if($this->isModified ()){				
			$logAction = $this->isNew() ? self::LOG_ACTION_UPDATE : self::LOG_ACTION_UPDATE;			
			GO()->log($logAction, $this->username, $this);
		}
	}	
	
	protected function internalSave() {
		$wasNew = $this->isNew();
		
		$this->logSave();
		
		$this->saveBlob('photoBlobId');
		
		$success = parent::internalSave();

		if ($success && $wasNew) {

			//Create a group for this user and add the user to this group.
			$group = new Group();
			$group->userId = $this->id;
			$group->name = $this->username;
			$group->save();

			$ur = new UserGroup();
			$ur->userId = $this->id;
			$ur->groupId = $group->id;
			$ur->save();

			//add this user to the everyone group
			$ur = new UserGroup();
			$ur->userId = $this->id;
			$ur->groupId = Group::findEveryoneGroup()->id;
			$ur->save();
		}

		return $success;
	}
	
	
	protected function internalDelete($hard) {
		if ($this->id === 1) {
			$this->setValidationError('id', 'adminDeleteForbidden');
			return false;
		}
		
		$this->freeBlob($this->photoBlobId);
		
		return parent::internalDelete($hard);
	}
	
	
	public function setPassword($password) {
		if(GO()->getAuth()->user()->isAdmin() || $this->passwordVerified) {
			$this->password = $password;
		}  else {
			throw new IFW\Exception\Forbidden();
		}
	}

	/**
	 * Check if the password is correct for this user.
	 *
	 * @param string $password
	 * @return boolean
	 */
	public function checkPassword($password) {
		
		$hash = $this->isModified('password') ? $this->getOldAttributeValue('password') : $this->password;
		
		$this->passwordVerified = password_verify($password, $hash);
		
		if(!$this->passwordVerified) {
			$this->setValidationError('currentPassword', "INCORRECT_PASSWORD");
		}
		
		return $this->passwordVerified;
	}
	

	/**
	 * Check if this user is in the admins group
	 *
	 * @return bool
	 */
	public function isAdmin() {

		if(!isset($this->isAdmin)){
			$ur = UserGroup::findByPk(['userId' => $this->id, 'groupId' => Group::ID_ADMINS]);
			$this->isAdmin = $ur !== false;
		}

		return $this->isAdmin;
	}
	
	//for API
	public function getIsAdmin(){
		return $this->isAdmin();
	}

	/**
	 * Checks if the given user is member of a group this user is also a member of.
	 * 
	 * @param self $user
	 * @return boolean
	 */
	public function isInSameGroup(self $user) {	
						
		return UserGroup::find(
						(new Query())
						->select('1')
						->joinRelation('groupUsers')
						->andWhere(['!=', ['groupId'=>\GO\Core\Users\Model\Group::ID_EVERYONE]])
						->andWhere(['userId'=>$this->id])						
						->andWhere(['groupUsers.userId'=>$user->id])
						)->single();
	}	
}