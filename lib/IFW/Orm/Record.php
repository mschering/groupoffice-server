<?php

namespace IFW\Orm;

use IFW\Util\DateTime;
use Exception;
use IFW;
use IFW\Auth\Permissions\AdminsOnly;
use IFW\Auth\Permissions\Model as PermissionsModel;
use IFW\Data\Model as DataModel;
use IFW\Db\Column;
use IFW\Db\Table;
use IFW\Db\Exception\DeleteRestrict;
use IFW\Db\PDO;
use IFW\Exception\Forbidden;
use IFW\Db\Connection;
use IFW\Util\ClassFinder;
use IFW\Util\StringUtil;

/**
 * Record model.
 * 
 * Records are models that are stored in the database. Database columns are 
 * automatically converted into properties and relational data can be accessed
 * easily.
 *
 * Special columns
 * ---------------
 * 
 * 1. createdAt and modifiedAt and Datetime columns
 * 
 * Database columns "createdAt" and "modifiedAt" are automatically filled. They
 * should be of type "DATETIME" in MySQL. 
 *
 * All times should will stored in UTC. They are returned and set in the ISO 8601
 * Standard. Eg. 2014-07-22T16:10:15Z but {@see \DateTime} objects should be 
 * used to set dates.
 * 
 * 2. createdBy and modifiedBy
 *
 * Automatically set with the current userId.
 * 
 * 3. deleted
 * 
 * Enables soft delete functionality
 * 
 * 4. Date and date time columns
 * 
 * Columns of type DATE and DATETIME are automatically converted into {@see \DateTime} objects.
 * 
 * Note: When using DATE columns use an exclamation mark to remove time so comparisons work:
 * ```
 * $record->dateCol = DateTime::createFromFormat('!Ymd', '20170101')
 * ```
 *
 * Basic usage
 * -----------
 *
 * Create a new model:
 * 
 * ```
 * $user = new User();
 * $user->username="merijn";
 * $user->email="merijn@intermesh.nl";
 * $user->modifiedAt='2014-07-22T16:10:15Z'; //makes no sense but just for showing how to format the time.
 * $user->createdAt = new \DateTime(); //makes no sense but just for showing how to set dates.
 * $user->save();
 * ```
 *
 * <p>Updating a model:</p>
 * ```````````````````````````````````````````````````````````````````````````
 * $user = User::find(['username' => 'merijn'])->single();
 *
 * if($user){
 *    $user->email="merijn@intermesh.nl";
 *    $user->save();
 * }
 * ```````````````````````````````````````````````````````````````````````````
 *
 * <p>Find all users ({@see find()}):</p>
 * ```````````````````````````````````````````````````````````````````````````
 * $users = User::find();
 * foreach($users as $user){
 *     echo $user->username.'<br />';
 * }
 * ```````````````````````````````````````````````````````````````````````````
 * 
 * 
 * Relations
 * ---------
 * 
 * The Record supports relational data. See the {@see defineRelations()} 
 * function on how to define them.
 * 
 * To get the "groups" relation of a user simply do:
 * 
 * ```````````````````````````````````````````````````````````````````````````
 * 
 * //$user->groups returns a RelationStore because it's a has many relation
 * foreach($user->groups as $group){
 *    echo $group->name;
 * }
 * ```````````````````````````````````````````````````````````````````````````
 * 
 * 
 * If you'd like to query a subset of the relation you can adjust the relation 
 * store's query object. You should clone the relation store because otherwise
 * you are adjusting the actual relation of the model that might be needed in 
 * other parts of the code:
 * 
 * ```````````````````````````````````````````````````````````````````````````
 * $attachments = clone $message->attachments;
 * $attachments->getQuery()->where(['AND','!=', ['contentId' => null]]);
 * ```````````````````````````````````````````````````````````````````````````
 * 
 * You can also set relations:
 * 
 * With models:
 * ```````````````````````````````````````````````````````````````````````````
 * $user->groups = [$groupModel1, $groupModel2]; 
 * $user->save();
 * ```````````````````````````````````````````````````````````````````````````
 * 
 * Or with arrays of attributes. (This is the API way when  posting JSON):
 * ```````````````````````````````````````````````````````````````````````````
 * $user->groups = [['groupId' => 1]), ['groupId' => 2]]; 
 * $user->save();
 * ```````````````````````````````````````````````````````````````````````````
 * 
 * Or modify relations directly:
 * ```````````````````````````````````````````````````````````````````````````
 * $contact = Contact::findByPk($id);
 * $contact->emailAddresses[0]->type = 'work';
 * $contact->save();
 * ```````````````````````````````````````````````````````````````````````````
 *
 * 
 * See also {@see RelationStore} for more information about how the has many relation collection works.
 *
 * See the {@see Query} object for available options for the find functions and the User object for an example implementation.
 * 
 * @param DataModel $permissions {@see getPermissions()}
 * 
 * @method static single() {@see Store::single()} For IDE autocomplete reasons this method is defined
 * @method static all() {@see Store::all()} For IDE autocomplete reasons this method is defined
 *
 * 
 * @copyright (c) 2014, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */
abstract class Record extends DataModel {
	
	use \IFW\Event\EventEmitterTrait;
	
	use \IFW\Validate\ValidationTrait;
	
	/**
	 * Event fired in the save function.
	 * 
	 * Save can be canceled by returning false or setting validation errors.
	 * 
	 * @param self $record
	 */
	const EVENT_BEFORE_SAVE = 1;	
	
	/**
	 * Fired after save
	 * 
	 * Also look at EVENT_COMMIT
	 * 
	 * @param self $record
	 * @param boolean $success
	 */
	const EVENT_AFTER_SAVE = 2;	
	
	/**
	 * Event fired in the delete function.
	 * 
	 * Delete can be cancelled by returning false.
	 * 
	 * @param self $record
	 */
	const EVENT_BEFORE_DELETE = 3;	
	
	/**
	 * Fired after delete
	 * 
	 * @param self $record
	 */
	const EVENT_AFTER_DELETE = 4;
	
	
	/**
	 * Fired on object construct 
	 * 
	 * @param self $record
	 */
	const EVENT_CONSTRUCT = 5;
	
	/**
	 * Fired when finding records
	 */
	const EVENT_FIND = 6;
	
	/**
	 * Fired when relations are defined
	 */
	const EVENT_DEFINE_RELATIONS = 7;
	
	/**
	 * Fires before validation
	 */
	const EVENT_BEFORE_VALIDATE = 8;
	
	/**
	 * Fires after validation
	 */
	const EVENT_AFTER_VALIDATE = 9;
	
	/**
	 * Fires when this record is converted into an array for the API
	 */
	const EVENT_TO_ARRAY = 10;
	
	
	/**
	 * Fired after commit
	 * 
	 * Commit is fired after the entire entity with relations has been saved.
	 * Unlike EVENT_SAVE the record is completely in it's new state so you don't 
	 * have access to the modified properties anymore.
	 * 
	 * @param self $record
	 */
	const EVENT_COMMIT = 11;	
	

	/**
	 * All relations are only fetched once per request and stored in this static
	 * array
	 *
	 * @var array
	 */
	public static $relationDefs;
	
	/**
	 * When this is set to true the model will be deleted on save.
	 * Useful for saving relations.
	 * 
	 * @var boolean
	 */
	public $markDeleted = false;

	/**
	 * Indicates that the ActiveRecord is being constructed by PDO.
	 * Used in setAttribute so it skips fancy features that we know will only
	 * cause overhead.
	 *
	 * @var boolean
	 */
	private $loadingFromDatabase = true;

	/**
	 * True if this record doesn't exit in the database yet
	 * @var boolean
	 */
	protected $isNew = true;

	/**
	 * Holds the accessed relations
	 * 
	 * Relations are accessed via __set and __get the values set or fetched there
	 * are stored in this array.
	 * 
	 * @var RelationStore[]
	 */
	private $relations = [];
	

	/**
	 * Tells us if this record is deleted from the database.
	 * 
	 * @var boolean 
	 */
	private $isDeleted = false;
	
	
	/**
	 * The permissions model
	 * 
	 * @var PermissionsModel 
	 */
	private static $permissions;	
	
	/**
	 * When saving relational this is set on the children of the parent object 
	 * that is  being saved. All objects in the save will not reset their 
	 * modifications until the parent save operation has been completed successfully.
	 * 
	 * Only then will resetModified() be called and it will bubble down the tree.
	 * 
	 * @var Record 
	 */
	private $savedBy = null;
	
	/**
	 * When the record is saved this is set to true.
	 * Relational saves will prevent identical relations from being saved twice.
	 * 
	 * When a record is saved by a relation this flag will be true until commit or
	 * rollback is called.
	 * 
	 * @var boolean 
	 */
	private $isSaving = false;
	
	/**
	 * Keeps track if the save method started the transaction
	 * 
	 * @var boolean 
	 */
	private $saveStartedTransaction = false;
	
	
	/**
	 * Holds a copy of the attributes that were loaded from the database.
	 * 
	 * Used for modified checks.
	 * 
	 * @var array 
	 */
	private $oldAttributes = [];


	private $allowedPermissionTypes = [];
	
	/**
	 * Constructor
	 * 
	 * It checks if the record is new or existing in the database. It also sets
	 * default attributes and casts mysql values to int, floats or booleans as 
	 * mysql values from PDO are always strings.
	 * 
	 * @param bool $isNew Set to false by PDO
	 * @param array $allowPermissionTypes Set by the permissions object when permissions are already checked by the find() query. See {@see Query::allowPermissionTypes()}
	 * @param array $values These values are set before checking permissions. Used by PDO to set the parent relations when fetching child relations. For example 'contact' is set on $contact->emailAddresses
	 */
	public function __construct($isNew = true, $allowPermissionTypes = [], $values = []) {
		
		parent::__construct();
		
		$this->allowedPermissionTypes = $allowPermissionTypes;

		$this->isNew = $isNew;

		if (!$this->isNew) {
			$this->castDatabaseAttributes();	//Will also call setOldAttributes()		
		} else {
			$this->setDefaultAttributes();
			$this->setOldAttributes();
		}
		
		$this->loadingFromDatabase = false;
		
		$this->init();
		
		$this->setValues($values);
		
		if($this->isNew) {
//			Removed this check becuase it caused a problem with join relation. It creates a new object but it's a read action.
//			Permissions are checked on save anyway so it's not really a problem
//			
//			if(!$this->getPermissions()->can(PermissionsModel::ACTION_CREATE)) {
//				throw new Forbidden("You're not permitted to create a ".$this->getClassName());
//			}
		}else
		{
			//skipReadPermission is selected if you use IFW\Auth\Permissions\Model::query() so permissions have already been checked
			if(!PermissionsModel::isCheckingPermissions() && !$this->getPermissions()->can(PermissionsModel::PERMISSION_READ)) {
				throw new Forbidden("You're not permitted to read ".$this->getClassName()." ".var_export($this->pk(), true));
			}
		}
		
		$this->fireEvent(self::EVENT_CONSTRUCT, $this);
	}
	
	/**
	 * {@see Query::allowPermissionTypes()}
	 * 
	 * @return string[]
	 */
	public function allowedPermissionTypes() {
		return $this->allowedPermissionTypes;
	}
	
	/**
	 * This function is called at the end of the constructor.
	 * 
	 * You can set default attributes on new models here for example.
	 */
	protected function init() {
		
	}

	/**
	 * Mysql always returns strings. We want strict types in our model to clearly
	 * detect modifications
	 *
	 * @param array $columns
	 * @return void
	 */
	private function castDatabaseAttributes() {
		foreach ($this->getTable()->getColumns() as $colName => $column) {			
			if(isset($this->$colName)) {
				$this->$colName = $column->dbToRecord($this->$colName);				
			}
		}		
		
		//filled by joined relations in __set
		foreach($this->relations as $relationName => $relationStore) {

			//check loading from database boolean to prevent infinite loop because 
			//the reverse / parent relations are set automatically.
			if($relationStore[0]->loadingFromDatabase) {
				$relationStore[0]->loadingFromDatabase = false;
				$relationStore[0]->castDatabaseAttributes();
				$relationStore[0]->isNew = false;
			}			
		}
		
		$this->setOldAttributes();
	}
	
	/**
	 * Set's current column values in the oldAttributes array
	 */
	private function setOldAttributes() {
		foreach ($this->getTable()->getColumns() as $colName => $column) {			
			if(isset($this->$colName)) {
				$this->$colName = $this->oldAttributes[$colName] = $this->$colName;				
			}else
			{
				$this->oldAttributes[$colName] = null;
			}
		}
	}

	/**
	 * Clears all modified attributes
	 */
	public function clearModified() {
		foreach ($this->getTable()->getColumns() as $colName => $column)
			$this->oldAttributes[$colName] = null;
	}

	/**
	 * Set's the default values from the database definitions
	 * 
	 * Also sets the 'createdBy' column to the current logged in user id.
	 */
	protected function setDefaultAttributes() {
		
		foreach ($this->getTable()->getColumns() as $colName => $column) {			
			$this->$colName = $column->default;			
		}
		
		if (property_exists($this, 'createdBy')) {
			$this->createdBy = IFW::app()->getAuth()->user() ? IFW::app()->getAuth()->user()->id() : 1;
		}
	}



	/**
	 * Return the table name to store these records in.
	 *
	 * By default it removes the first two parts ofr the namespace and the second last namespace which is "DataModel".
	 *
	 * @return string
	 */
	public static function tableName() {
		return self::classToTableName(get_called_class());
	}	

	/**
	 * Get the table name from a record class name
	 * 
	 * @param string $class
	 * @return string
	 */
	protected static function classToTableName($class) {
		
		$cacheKey = 'tableName-'.$class;
		if(($tableName = IFW::app()->getCache()->get($cacheKey))) {
			return $tableName;
		}
		
		$parts = explode("\\", $class);		
		//remove GO\Core or GO\Modules
		if($parts[1] == 'Modules') {
			$parts = array_slice($parts, 3); //Strip GO\Modules\VendorName		
		}else
		{
			$parts = array_slice($parts, 2);		
		}
		//remove "Model" part
		array_splice($parts, -2, 1);
		
		$tableName = StringUtil::camelCaseToUnderscore(implode('', $parts));
		
		IFW::app()->getCache()->set($cacheKey, $tableName);
		
		return $tableName;
	}
	
	static private $table;
	

	/**
	 * Get the database columns
	 *
	 * <p>Example:</p>
	 * ```````````````````````````````````````````````````````````````````````````
	 * $columns = User::getTable();
	 * ```````````````````````````````````````````````````````````````````````````
	 *
	 * @return Table
	 */
	public static function getTable() {		
		$cls = static::class;
		if(!isset(self::$table[$cls])) {
			self::$table[$cls] = Table::getInstance(static::tableName());		
		}
		
		return self::$table[$cls];
	}

	/**
	 * Get the database column definition
	 *
	 * <p>Example:</p>
	 * ```````````````````````````````````````````````````````````````````````````
	 * $column = User::getColumn('username);
	 * echo $column->length;
	 * ```````````````````````````````````````````````````````````````````````````
	 *
	 * @param string $name
	 * @return Column
	 */
	public static function getColumn($name) {
		return self::getTable()->getColumn($name);
	}
	
	/**
	 * Checks if a column exists
	 * 
	 * @param string $name
	 * @return boolean
	 */
	public static function hasColumn($name) {
		return static::getColumn($name) !== null;
	}

	/**
	 * The primary key columns
	 * 
	 * This value is auto detected from the database. 
	 *
	 * @return string[] eg. ['id']
	 */
	public static function getPrimaryKey() {		
		return static::getTable()->getPrimaryKey();
	}
	
	/**
	 * Get the primary key values.
	 * 
	 * ``````````````
	 * ['id' => 1]
	 * ``````````````
	 * 
	 * @return string[] Key value array
	 */
	public function pk() {
		$primaryCols = $this->getPrimaryKey();
		
		$pk = [];
		
		foreach($primaryCols as $colName) {
			$pk[$colName] = $this->getColumn($colName)->recordToDb($this->$colName);
		}
		
		return $pk;		
	}

	/**
	 * Returns true if this is a new record and does not exist in the database yet.
	 *
	 * @return boolean
	 */
	public function isNew() {

		return $this->isNew;
	}


	/**
	 * The special magic getter
	 *
	 * This function finds database values and relations
	 * 
	 * Avoid naming conflicts. The set order is:
	 * 
	 * 1. get function
	 * 2. column
	 * 3. extra selected value in sql query
	 * 4. relation
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name) {	
		$getter = 'get'.$name;

		if(method_exists($this,$getter)){
			return $this->$getter();
		} elseif (($relation = $this->getRelation($name))) {
			return $this->getRelated($name);
		} else {
			throw new Exception("Can't get not existing property '$name' in '".static::class."'");
		}
	}

	/**
	 * Get's a relation from cache or from the database
	 * 
	 * Can be used in overrides with a getter. For example a company employees relation:
	 * 
	 * ```````````````````````````````````````````````````````````````````````````
	 * public function getEmployees() {
	 *		if(!$this->isOrganization) {
	 *			return null;
	 *		}else
	 *		{
	 *			return $this->getRelated('employees');
	 *		}
	 *	}
	 * ```````````````````````````````````````````````````````````````````````````
	 * 
	 *
	 * @param string $name Name of the relation
	 * @param Query $query
	 * @return \IFW\Orm\RelationStore | self | null Returns null if not found
	 */
	protected function getRelated($name) {

		$relation = $this->getRelation($name);

		if (!$relation) {
			throw new Exception($name . ' is not a relation of ' . static::class);
		}
		
		if(!isset($this->relations[$name])){			
			
			//Get RelationStore
			$store = $relation->get($this);			
			$this->applyRelationPermissions($relation, $store);			
			$this->relations[$name] = $store;		
		}
		
		if($relation->hasMany()) {
			return $this->relations[$name];
		}else
		{
			$record = $this->relations[$name]->single();
			if($record) {
				return $record;
			}else
			{
				return null;
			}
		}		
	}	
	
	/**
	 * Apply permissions to relational query
	 */
	private function applyRelationPermissions(Relation $relation, RelationStore $store) {
		$allowedPermissionTypes = static::relationIsAllowed($relation->getName());
		if($allowedPermissionTypes) {
			$store->getQuery()->allowPermissionTypes($allowedPermissionTypes);
		}else if($relation->hasMany ()) {
			//only apply query to has many. On single relational records it's better 
			//to get the permission denied error when the read permissions are checked 
			//in the constructor.
			$toRecordName = $relation->getToRecordName();
			$permissions = $toRecordName::internalGetPermissions();
			$permissions->setRecordClassName($toRecordName);
			$permissions->applyToQuery($store->getQuery());
		}
	}
	
	/**
	 *
	 * {@inheritdoc}
	 * 
	 */
	public function __isset($name) {
		return ($this->getRelation($name) && $this->getRelated($name)) ||
						parent::__isset($name);
	}
	
	/**
	 * Checks if a relation has already been fetched or set in the record.
	 * 
	 * @param string $name Relation name
	 * @return bool
	 */
	public function relationIsFetched($name) {
		return array_key_exists($name, $this->relations);
	}
	
	/**
	 * Check if a readable propery exists
	 * 
	 * public properties, getter methods, columns and relations are checked
	 * 
	 * @param string $name
	 * @return boolean
	 */
	public function hasReadableProperty($name) {
		if($this->getRelation($name)) {
			return true;
		}
		
		return parent::hasReadableProperty($name);
	}
	
	/**
	 * Check if a writable propery exists
	 * 
	 * public properties, setter methods, columns and relations are checked
	 * 
	 * @param string $name
	 * @return boolean
	 */
	public function hasWritableProperty($name) {
		
		if($this->getRelation($name)) {
			return true;
		}
		return parent::hasWritableProperty($name);
	}
	
	public function setValues(array $properties) {
		
		//convert client input. For example date string to Datetime object.
		foreach(self::getTable()->getColumns() as $name => $column) {
			if(isset($properties[$name])){
				$properties[$name]=$column->normalizeInput($properties[$name]);
			}
		}
		
		return parent::setValues($properties);
	}

	/**
	 * Magic setter. Set's database columns or setter functions.
	 * 
	 * Avoid naming conflicts. The set order is:
	 * 
	 * 1. set function
	 * 2. column
	 * 3. extra selected value in sql query
	 * 4. relation
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set($name, $value) {	
		
		if($this->setJoinedRelationAttribute($name, $value)) {
			return;
		}
			
		$setter = 'set'.$name;

		if(method_exists($this,$setter)){
			$this->$setter($value);
		} elseif (($relation = $this->getRelation($name))) {												
			$this->setRelated($name, $value);
		} else {
			$getter = 'get' . $name;
			if(method_exists($this, $getter)){
				
				//Allow to set read only properties with their original value.
				//http://stackoverflow.com/questions/20533712/how-should-a-restful-service-expose-read-only-properties-on-mutable-resources								
//				$errorMsg = "Can't set read only property '$name' in '".static::class."'";
				//for performance reasons we simply ignore it.
				\IFW::app()->getDebugger()->debug("Discarding read only property '$name' in '".static::class."'");
			}else {
				$errorMsg = "Can't set not existing property '$name' in '".static::class."'";
				throw new Exception($errorMsg);
			}
		}

	}
	
	private function setJoinedRelationAttribute($name, $value) {
		if(!$this->loadingFromDatabase || !strpos($name, '@')) {
			return false;
		}
		
		if(isset($value)) {
			$propPathParts = explode('@', $name);		
			$propName = array_pop($propPathParts);

			$currentRecord = &$this;				
			foreach ($propPathParts as $part) {		
				$relation = $currentRecord::getRelation($part);
				if($relation && !$currentRecord->relationIsFetched($part)) {
					$cls = $relation->getToRecordName();
					$record = new $cls(false, ['*']);
					$currentRecord->setRelated($part, $record);
				}

				$currentRecord = $currentRecord->getRelated($part);			

			}
			$currentRecord->loadingFromDatabase = true; //swichted back in castDatabaseAttributes()
			$currentRecord->$propName = $value;			
		}
	
		return true;
	}
	
	/**
	 * Set's a relation
	 * 
	 * Useful when you want to do something extra when setting a relation. Override
	 * it with a setter function and use this function inside.
	 * 
	 * @example
	 * ```````````````````````````````````````````````````````````````````````````
	 * public function setExampleRelation($value) {
	 *		$this->vatRate = $vatCode->rate;
	 *		$store = $this->setRelated('exampleRelation', $value);
	 * 
	 *		$this->somePropToCopy = $store[0]->theCopiedProp;
	 *	}
	 * ```````````````````````````````````````````````````````````````````````````
	 * 
	 * @param string $name
	 * @param mixed $value
	 * @return RelationStore
	 */
	protected function setRelated($name, $value) {
		
		$relation = $this->getRelation($name);
		//set to null to prevent loops when setting parent relations. 
		//The relationIsFetched will work within the __set operation with array_key_exists this way.
		if(!isset($this->relations[$name])) {
			//$this->relations[$name] = null; 
			$this->relations[$name] = $relation->get($this);
		} 
		
		if($relation->hasMany()) {			
			if(isset($value)) {
				foreach($value as $record) {
					$this->relations[$name][] = $record;
				}			
			}
		}else
		{
			$this->relations[$name][0] = $value;
		}	
		
		return $this->relations[$name];
	}
	

	/**
	 * To prevent infinite loops with relations
	 * 
	 * @var bool 
	 */
	private $isCheckingModified = false;
	
	/**
	 * Check if this record or record attribute has modifications not saved to
	 * the database yet.
	 * 
	 * ```````````````````````````````````````````````````````````````````````````
	 * 
	 * if($record->isModified()) {
	 *	//the record has at least one modified attribute
	 * }
	 * 
	 * if($record->isModified('foo')) {
	 *	//the attribute foo has been modified
	 * }
	 * 
	 * if($record->isModified(['foo','bar'])) {
	 *	//foo or bar is modified
	 * }
	 * 
	 * ```````````````````````````````````````````````````````````````````````````
	 *
	 * @param string|array $attributeOrRelationName If you pass an array then they are all checked
	 * @return boolean
	 */
	public function isModified($attributeOrRelationName = null, $withRelations = true) {
		
		//prevent infinite loop
		if($withRelations) {
			if($this->isCheckingModified) {
				return false;
			}
			$this->isCheckingModified = $withRelations;
		}
		
		try {
			$ret = $this->internalIsModified($attributeOrRelationName, $withRelations);
		} finally {
			if($withRelations) {
				$this->isCheckingModified = false;
			}
		}		
		
		return $ret;
	}
	
	private function internalIsModified($attributeOrRelationName, $withRelations) {
		if (!isset($attributeOrRelationName)) {
			
			foreach($this->oldAttributes as $colName => $loadedValue) {
				//do not check stict here as it leads to date problems.
				if($this->$colName != $loadedValue)
				{
					return true;
				}
			}
			
			if($withRelations) {
				foreach($this->relations as $store) {
					if($store->isModified()) {
						return true;
					}
				}
			}
			
			return false;
		}
		
		if (!is_array($attributeOrRelationName)) {
			$attributeOrRelationName = [$attributeOrRelationName];
		}
		foreach ($attributeOrRelationName as $a) {						
			
			if($this->getColumn($a)) {				
				if(!isset($this->oldAttributes[$a]) && isset($this->a)) {
					return true;
				}
				
				if($this->oldAttributes[$a] != $this->$a) {
					return true;
				}
			}elseif($this->getRelation($a)) {
				if(isset($this->relations[$a]) && $this->relations[$a]->isModified()) {
					return true;
				}
			} else
			{
				throw new \Exception("Not an attribute or relation '$a'");
			}
		}
		return false;
	}
	
	/**
	 * Get the modified attributes and relation names.
	 * 
	 * See also {@see getModifiedAttributes()}
	 * 
	 * @return string[]
	 */
	public function getModified() {
		$props = array_keys($this->getModifiedAttributes());
	
		foreach($this->relations as $r) {
			if($r->isModified()){
				$props[] = $r->getRelation()->getName();
			}
		}
		
		return $props;
	}
	

	/**
	 * Reset record or specific attribute(s) or relation to it's original value and 
	 * clear the modified attribute(s).
	 *
	 * @param string|array|null $attributeName
	 */
	public function reset($attributeName = null) {
		
		if(!isset($attributeName)) {
			$attributeName = array_keys($this->oldAttributes);
			
			$this->relations = [];
			
		}else if(!is_array($attributeName)) {
			$attributeName = [$attributeName];
		}
		
		foreach($attributeName as $a) {
			if(array_key_exists($a, $this->oldAttributes)) {
				$this->$a = $this->oldAttributes[$a];
			} else if (isset($this->relations[$a])) {
				unset($this->relations[$a]);
			} else
			{
				throw new Exception("Attribute or relation '$a' not found!");
			}
		}
	}

	/**
	 * Get the old value for a modified attribute.
	 *
	 * <p>Example:</p>
	 * ```````````````````````````````````````````````````````````````````````````
	 *
	 * $model = User::findByPk(1);
	 * $model->username='newValue':
	 *
	 * $oldValue = $model->getOldAttributeValue('username');
	 *
	 * ```````````````````````````````````````````````````````````````````````````
	 * @param string $attributeName
	 * @return mixed
	 */
	public function getOldAttributeValue($attributeName) {
		
//		if(!$this->isModified($attributeName)) {
//			throw new \Exception("Can't get old attribute value because '$attributeName' is not modified");
//		}
		return $this->oldAttributes[$attributeName];
	}

	/**
	 * Get modified attributes
	 *
	 * Get a key value array of modified attribute names with their old values
	 * that are not saved to the database yet.
	 *
	 * <p>Example:</p>
	 * ```````````````````````````````````````````````````````````````````````````
	 *
	 * $model = User::findByPk(1);
	 * $model->username='newValue':
	 *
	 * $modifiedAttributes = $model->getModifiedAttributes();
	 * 
	 * $modifiedAtttibutes = ['username' => 'oldusername'];
	 *
	 * ```````````````````````````````````````````````````````````````````````````
	 *
	 * @return array eg. ['attributeName' => 'oldValue]
	 */
	public function getModifiedAttributes() {
		$modified = [];
		
		foreach($this->oldAttributes as $colName => $loadedValue) {
			if($this->$colName != $loadedValue) {
				$modified[$colName] = $loadedValue;
			}
		}
		return $modified;
	}

	/**
	 * Define relations for this or other models.
	 * 
	 * You can use the following functions:
	 * 
	 * * {@see hasOne()}
	 * * {@see hasMany()}
	 *
	 * <p>Example:</p>
	 * ```````````````````````````````````````````````````````````````````````````
	 * public static function defineRelations(){
	 *	
	 *  self::hasOne('owner', User::class, ['ownerUserId' => 'id]);
	 
	 *	self::hasMany('emailAddresses', ContactEmailAddress::class, ['id' => 'contactId']);
	 *	
	 *	self::hasMany('tags', Tag::class, ['id' => 'contactId'])
	 *			->via(ContactTag::class, ['tagId' => 'id']);
	 *	
	 *	self::hasOne('customfields', ContactCustomFields::class, ['id' => 'id']);
	 * }
	 * ```````````````````````````````````````````````````````````````````````````
	 * 
	 * It's also possible to add relations to other models:
	 * 
	 * ```````````````````````````````````````````````````````````````````````````
	 * public static function defineRelations(){
	 *  
	 *	GO\Core\Auth\DataModel\User::hasOne('contact', Contact::class, ['id' => 'userId']);
	 *	
	 * }
	 * ```````````````````````````````````````````````````````````````````````````
	 */
	protected static function defineRelations() {
		
//		self::fireStaticEvent(self::EVENT_DEFINE_RELATIONS);
	}
	
	
	/**
	 * When a relation is set we attempt to set the parent relation. in
	 * {@see RelationStore::setParentRelation()}
	 * 
	 * @example 
	 * 
	 * ```````````````````````````````````````````````````````````````````````````
	 * $contact = new Contact();
	 *		
	 *		$emailAddress = new EmailAddress();
	 *		$emailAddress->email = 'test@intermesh.nl';
	 *		$emailAddress->type = 'work';
	 *		
	 *		$contact->emailAddresses[] = $emailAddress;
	 *		
	 * //these are equal because of this functionality
	 *		$this->assertEquals($emailAddress->contact, $contact);
	 * ```````````````````````````````````````````````````````````````````````````
	 * 
	 * @param Relation $childRelation
	 * @return Relation
	 */
	
	public static function findParentRelation(Relation $childRelation) {
		if($childRelation->getViaRecordName()) {
			//not supported
			return null;
		}
		
		foreach(static::getRelations() as $parentRelation) {
			if(
							!$parentRelation->hasMany() && 
							$parentRelation->getToRecordName() == $childRelation->getFromRecordName() && 
							static::keysMatch($childRelation, $parentRelation)
				) {				
				return $parentRelation;
			}							
		}
		
		return null;
		
	}
	
	
	public static function keysMatch(Relation $parentRelation, Relation $childRelation) {
		
		$childKeys = $childRelation->getKeys();
		
		if(count($parentRelation->getKeys()) != count($childKeys)) {
			return false;
		}
				
		//check if keys are reversed
		foreach($parentRelation->getKeys() as $from => $to) {
			if(!isset($childKeys[$to])) {
				return false;
			}
			
			if($childKeys[$to] != $from) {
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Get's the relation definition
	 *
	 * @param string $name
	 * @return Relation
	 */
	public static function getRelation($name) {
		
		$map = explode('.', $name);
		
		$modelName = static::class;
		
		foreach($map as $name) {
			$relations = $modelName::getRelations();
			if(!isset($relations[$name])) {
				return false;
			}
			$modelName = $relations[$name]->getToRecordName();
		}
		
		return $relations[$name];
	}

	/**
	 * Get all relations names for this model
	 *
	 * @return Relation[]
	 */
	public static function getRelations() {		
		$calledClass = static::class;
		
		if(!isset(self::$relationDefs[$calledClass])){
			self::$relationDefs[$calledClass] = IFW::app()->getCache()->get($calledClass.'-relations');
		}

		return self::$relationDefs[$calledClass];
	}
	
	
	/**
	 * Called from {@see \IFW\App::init()}
	 * 
	 * Calls defineRelations on all models
	 */
	public static function initRelations() {
		
		if(IFW::app()->getCache()->get('initRelations')) {
			return true;
		}
		
		IFW::app()->debug("Initializing Record relations");
			
		self::$relationDefs = [];

		foreach(\IFW::app()->getModules() as $module) {

			$classFinder = new ClassFinder();		
			$classFinder->setNamespace($module::getNamespace());

			$classes = $classFinder->findByParent(self::class);
			
			foreach($classes as $className) {	
				
				if(!isset(self::$relationDefs[$className])) {
					self::$relationDefs[$className] = [];
				}

				$className::defineRelations();
			}
		}
		
		foreach(self::$relationDefs as $className => $defs) {
			IFW::app()->getCache()->set($className.'-relations', $defs);
		}
		
		IFW::app()->getCache()->set('initRelations', true);
	}
	
	
	private $objectId;
	/**
	 * Get an ID of the object for debugging
	 * 
	 * @return string
	 */
	public function objectId() {
		if(!isset($this->objectId)) {
			$this->objectId = $this->getClassName().', pk:' . implode('-',$this->pk());// . ', #'.md5(spl_object_hash($this));
		}
		return $this->objectId;
	}

	/**
	 * Save changes to database
	 *
	 * <p>Example:</p>
	 * ```````````````````````````````````````````````````````````````````````````
	 * $model = User::findByPk(1);
	 * $model->setAttibutes(['username'=>'admin']);
	 * if(!$model->save())	{
	 *  //oops, validation must have failed
	 *   var_dump($model->getValidationErrors();
	 * }
	 * ```````````````````````````````````````````````````````````````````````````
	 * 
	 * Don't override this method. Override {@see internalSave()} instead.
	 *
	 * @return bool
	 */
	public final function save() {	
		
		if($this->isSaving) {
			return true;
		}
		
//		\IFW::app()->debug("Save ".$this->objectId());
							
		if($this->markDeleted) {
			return $this->delete();
		}

		$action = $this->isNew() ? PermissionsModel::PERMISSION_CREATE : PermissionsModel::PERMISSION_WRITE;

		if(!$this->getPermissions()->can($action)) {
			throw new Forbidden("You're (user ID: ".IFW::app()->getAuth()->user()->id().") not permitted to ".$action." ".$this->getClassName()." ".var_export($this->pk(), true));
		}

		$this->checkRelationPermissions();

		if (!$this->validate()) {
			\IFW::app()->debug("Validation of ".$this->getClassName()." failed. Validation errors: ".var_export($this->getValidationErrors(), true));
			return false;
		}
			
		$success = false;
		$this->isSaving = true;		
		try {
			//don't start new transaction if we're already in one
			//we start it before validation because you might want to override 
			//internalValidate() to create some required relations for example.
			$this->saveStartedTransaction = !$this->getDbConnection()->inTransaction();
			if($this->saveStartedTransaction) {
				$this->getDbConnection()->beginTransaction();
			}	
			
			if($this->isNew()) {
				$this->getPermissions()->beforeCreate($this);
			}

			//save modified attributes for after save event
			$success = $this->internalSave();			
			if(!$success) {
				\IFW::app()->debug(static::class.'::internalSave returned '.var_export($success, true));
			}

			if(!$this->fireEvent(self::EVENT_AFTER_SAVE, $this, $success)){			
				\IFW::app()->debug(static::class.'::fireEvent after save returned '.var_export($success, true));
				$success = false;
			}			
						
			return $success;			
			
		} finally {
			//only commit or rollback when we're the record that started the save
			if(!$this->savedBy) {
				if(!$success) {				
					$this->rollBack();				
				}else {			
					$this->commit();				
				}
			}
		}
	}

	
	/**
	 * Rollback changes and database transaction after failed save operation
	 */
	protected function rollBack() {
//		\IFW::app()->debug("Rollback ".$this->objectId());		
		
		if($this->saveStartedTransaction) {
			$this->getDbConnection()->rollBack();
			$this->saveStartedTransaction = false;
		}
		if($this->isNew()) {
			//rollback auto increment ID too
			$aiCol = $this->findAutoIncrementColumn();
			if($aiCol) {
				$this->{$aiCol->name} = null;
			}
		}		
		$this->savedBy = null;
		$this->isSaving = false;
		
		foreach($this->savedRelations as $relationStore) {
			foreach($relationStore as $record) {
				//only commit if this record initated the save of this relation
				if($record->isSaving && $record->savedBy == $this) {
					$record->rollBack();
				}else
				{						
					//might have beeen set but save never started because it wasn't modified
					if($record->savedBy == $this) {
						$record->savedBy = null;
					}
				}
			}
		}
		
		$this->savedRelations = [];
	}
	
	/**
	 * Clears the modified state of the object and commits database transaction 
	 * after successful save operation.
	 */
	private function commit() {		
//		\IFW::app()->debug("commit ".$this->objectId());
		
		
		if($this->saveStartedTransaction) {
			$this->getDbConnection()->commit();
			$this->saveStartedTransaction = false;
		}
		
		$this->isNew = false;
		$this->setOldAttributes();
		$this->savedBy = null;
		$this->isSaving = false;
		
		//Unset the accessed relations so user set relations are queried from the db after save.
		foreach($this->savedRelations as $relationStore) {
			
			foreach($relationStore as $record) {
				//only commit if this record initated the save of this relation
				if($record->isSaving && $record->savedBy == $this) {
					$record->commit();
				}else
				{						
					//might have beeen set but save never started because it wasn't modified
					if($record->savedBy == $this) {
						$record->savedBy = null;
					}
				}

			}
			$relationStore->reset();
		}
		$this->relations = [];
		$this->savedRelations = [];		
		
		
		$this->fireEvent(self::EVENT_COMMIT, $this);
	}	
	
	/**
	 * Performs the save to database after validation and permission checks.
	 * 
	 * After this function the modified attributes are reset and the isNew() function 
	 * will return false.
	 * 
	 * If you want to add functionality before or after save then override this 
	 * method. This method is executed within a database transaction and after 
	 * validation and permission checks so you don't have to worry about that.
	 * 
	 * @return boolean
	 * @throws Exception
	 */
	protected function internalSave() {
		
		
		if(!$this->fireEvent(self::EVENT_BEFORE_SAVE, $this)){
			return false;
		}
		
		if(!$this->saveBelongsToRelations()) {
			return false;
		}
		
		if ($this->isNew) {
			if(!$this->insert()){
				throw new \Exception("Could not insert record into database!");
			}
		} else {
			if(!$this->update()){
				throw new \Exception("Could not update record into database!");
			}
		}
		
		if(!$this->saveRelations()) {		
			return false;
		}
		
		
		return true;
	}
	
	/**
	 * Relations that are saved after this record
	 * 
	 * @return boolean
	 */
	private function saveRelations() {
		foreach($this->relations as $relationName => $relationStore) {			
			if(!isset($relationStore)) {
				continue;
			}
			
			$relation = $this->getRelation($relationName);
			if($relation->isBelongsTo()) {
				continue;
			}
			
			if(!$relationStore->isModified()) {					
				continue;
			}
			
			//this will prevent modifications to be cleared
			foreach($relationStore as $record) {
				//don't set this if the record was already saving. Loops.
				$record->setIsSavedBy($this);				
			}
			
			$this->savedRelations[$relationName] = $relationStore;

			if(!$relationStore->save()) {				
				$this->setValidationError($relationName, \IFW\Validate\ErrorCode::RELATIONAL);				
				return false;
			}
		}
		
		return true;
	}	
	
	private $savedRelations = [];
	
	private function setIsSavedBy($record) {
		if(!$this->isSaving) {
//			\IFW::app()->debug($this->objectId().' is saved by '.$record->objectId(), \IFW\Debugger::TYPE_GENERAL, 1);
			$this->savedBy = $record;
		}
	}
	
	/**
	 * When belongs to relations are updated by setting the keys directly.
	 * For example set $invoice->contactId, we must check if the user is allowed 
	 * to read this contact
	 */
		
	private function checkRelationPermissions() {
		foreach($this->getRelations() as $relation) {
			if($relation->isBelongsTo()) {
				foreach($relation->getKeys() as $from => $to) {
					if(!empty($this->{$from}) && $this->isModified($from)) {						
						$record = $this->{$relation->getName()};						
						if($record && !$record->isNew() &&  !$record->getPermissions()->can(PermissionsModel::PERMISSION_READ)){
							throw new Forbidden("You've set a key for ".$this->getClassName().'::'.$from.' (pk: '.var_export($this->pk(), true).') that you are not allowed to read');
						}						
					}
				}
			}
		}
	}
	
	/**
	 * Belongs to relations that have been set must be saved before saving this record.
	 * 
	 * @return boolean
	 */
	protected function saveBelongsToRelations() {
		
		foreach($this->relations as $relationName => $relationStore) {
			
			if(!isset($relationStore)) {
				continue;
			}
			
			$relation = $this->getRelation($relationName);
			
			if(!$relation->isBelongsTo()) {
				continue;
			}
			
			if(!$relationStore->isModified()) {
				/*
				 * Update keys. Because the relation might not be modified anymore but could have been new at the time the relation was set.
				 * 
				 * eg.
				 * 
				 * $record = new A();
				 * 
				 * $belongsTo = new B();
				 * $record->belongsTo = $belongsTo; //$record can not get key of $belongsTo yet.
				 * 
				 * $belongsTo->save(); //Now it gets a key but $record is not aware yet.
				 * 
				 * $record->save(); //Now we get into this code block here and keys are set
				 */
				
				$relationStore->setNewKeys();
				continue;
			}
//			
//			IFW::app()->debug("Saving belongs to relation ".$this->getClassName().'::'.$relationName);
			
			//Modifications are not cleared directly.
			foreach($relationStore as $record) {
				//don't set this if the record was already saving. Loops.
				$record->setIsSavedBy($this);
			}
			
			$this->savedRelations[$relationName] = $relationStore;
			
			if(!$relationStore->save()) {						
				$this->setValidationError($relationName, \IFW\Validate\ErrorCode::RELATIONAL);
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Can be used to check if this record is saved directly or by a parent relation.
	 * 
	 * @return Record
	 */
	protected function getSavedBy() {
		return $this->savedBy;
	}

	/**
	 * Inserts the model into the database
	 *
	 * @return boolean
	 */
	private function insert() {

		if ($this->hasColumn('createdAt') && empty($this->createdAt)) {
			$this->createdAt = new DateTime();
		}

		if ($this->hasColumn('modifiedAt') && !$this->isModified('modifiedAt')) {
			$this->modifiedAt = new DateTime();
		}
		
		if ($this->hasColumn('modifiedBy') && !$this->isModified('modifiedBy')) {
			$this->modifiedBy = IFW::app()->getAuth()->user() ? IFW::app()->getAuth()->user()->id() : 1;
		}
		
		$data = [];
		
		foreach ($this->getTable()->getColumns() as $colName => $col) {
			$data[$colName] = $this->$colName;		
		}
		
		//find auto increment column first because it might do a show tables query
		$aiCol = $this->findAutoIncrementColumn();
		
		$stmt = $this->getDbConnection()
						->createCommand()
						->insert($this->tableName(), $data)
						->execute();
		if(!$stmt) {
			return false;
		}

		if($aiCol) {
			$lastInsertId = intval($this->getDbConnection()->getPDO()->lastInsertId());			
			
			if(empty($lastInsertId)) {
				throw new \Exception("Auto increment column didn't increment!");
			}
			$this->{$aiCol->name} = $lastInsertId;
		}

		return $stmt;
	}
	
	/**
	 * 
	 * @return Column
	 */
	private function findAutoIncrementColumn() {
		foreach($this->getTable()->getColumns() as $col) {
			if($col->autoIncrement) {
				return $col;
			}
		}
		
		return false;
	}

	/**
	 * Updates the database with modified attributes.
	 * 
	 * You generally don't use this function yourself. The only case it might be
	 * useful if you want to generate some attribute based on the auto incremented
	 * primary key value. For an order number for example.
	 * 
	 * @return boolean
	 * @throws Exception
	 */
	protected function update() {		

		//commented out the modifiedAt existance check. If we do this then we'll log to much when importing and applying no changes.
//		if (!$this->isModified() && !$this->hasColumn('modifiedAt') && !$this->hasColumn('modifiedBy')) {			
		if (!$this->isModified()) {			
			return true;
		}
		
		if ($this->getColumn('modifiedAt') && !$this->isModified('modifiedAt')) {
			$this->modifiedAt = new \DateTime();
		}
		
		if ($this->getColumn('modifiedBy') && !$this->isModified('modifiedBy')) {
			$this->modifiedBy = IFW::app()->getAuth()->user() ? IFW::app()->getAuth()->user()->id() : 1;
		}		
		
		$modifiedAttributeNames = array_keys($this->getModifiedAttributes());
		
		if(empty($modifiedAttributeNames))
		{
			return true;
		}		
		
		$data = [];		
		foreach ($modifiedAttributeNames as $colName) {
			$data[$colName] = $this->$colName;
		}
		
		$where = [];
		$pks = $this->getPrimaryKey();
		
		foreach($pks as $colName) {			
			//if it's a primary key and it's modified we must bind the old value here
			$where[$colName] = !$this->isNew && $this->isModified($colName) ? $this->getOldAttributeValue($colName) : $this->{$colName};
		}
		
		return $this->getDbConnection()->createCommand()->update($this->tableName(), $data, $where)->execute();
		
	}

	/**
	 * Find a Record by primary key
	 *
	 * <p>Example:</p>
	 * ```````````````````````````````````````````````````````````````````````````
	 * $user = User::findByPk(1);
	 * ```````````````````````````````````````````````````````````````````````````
	 * 
	 * The primary key can also be an array:
	 * 
	 * ```````````````````````````````````````````````````````````````````````````
	 * $user = User::find(['groupId'=>1,'userId'=>2])->single();
	 * ```````````````````````````````````````````````````````````````````````````
	 *
	 * @param int|array $pk
	 * @return static
	 */
	public static function findByPk($pk) {
		if (!is_array($pk)) {
			$pk = [static::getPrimaryKey()[0] => $pk];
		}
		
		$query = new Query();
		$query->where($pk)->withDeleted();

		if(!(IFW::app()->getCache() instanceof \IFW\Cache\None)) {
			$query->enableCache($pk);
		}
		
		return self::find($query)->single();
	}

	/**
	 * Find records.
	 * 
	 * Finds records based on the {@see Query} Object you pass. It returns a
	 * {@see Store} object. The documentation tells that it returns an instance
	 * of this model but that's just to enable autocompletion.
	 * 
	 * Basic usage
	 * -----------
	 *
	 * ```php
	 * 
	 * //Single user by attributes.
	 * $user = User::find(['username' => 'admin'])->single(); 
	 * 
	 * //Multiple users with search query.
	 * $users = User::find(
	 *         (new Query())
	 *           ->orderBy([$orderColumn => $orderDirection])
	 *           ->limit($limit)
	 *           ->offset($offset)
	 *           ->searchQuery($searchQuery, ['t.username','t.email'])
	 *         );
	 * 
	 * foreach ($users as $user) {
	 *   echo $user->username."<br />";
	 * }
	 * 
	 * ```
	 * 
	 * Join relations
	 * --------------
	 * 
	 * With {@see Query::joinRelation()} it's possible to join a relation so that later calls to that relation don't need to be fetched from the database separately.
	 * 
	 * ```````````````````````````````````````````````````````````````````````````
	 * $contacts = Contact::find(
	 *         (new Query())
	 *           ->joinRelation('addressbook', true)
	 *         );
	 * 
	 * foreach ($contacts as $contact) {
	 *   echo $contact->addressbook->name."<br />"; //no query needed for the addressbook relation.
	 * }
	 * ```````````````````````````````````````````````````````````````````````````
	 * 
	 * Complex join {@see Query::join()}
	 * ------------
	 * ```````````````````````````````````````````````````````````````````````````
	 * 
	 * $groups = Group::find((new Query())
	 *         ->orderBy([$orderColumn => $orderDirection])
   *         ->limit($limit)
   *         ->offset($offset)
   *         ->search($searchQuery, ['t.name'])
   *         ->join(
   *              UserGroup::class,
	 *              'userGroup',
   *              (new Criteria())
   *                  ->where('t.id = userGroup.groupId')
   *                  ->andWhere(["userGroup.userId", $userId])
   *              ,    
   *              'LEFT')
   *          ->where(['userGroup.groupId'=>null])
   *          );
	 * ```````````````````````````````````````````````````````````````````````````
	 * 
	 * More features
	 * -------------
	 * 
	 * ```````````````````````````````````````````````````````````````````````````
	 * $finder = Contact::find(
	 * 						(new Query())
	 * 								->select('t.*, count(emailAddresses.id)')
	 * 								->joinRelation('emailAddresses', false)								
	 * 								->groupBy(['t.id'])
	 * 								->having("count(emailAddresses.id) > 0")
	 * 						->where(['!=',['lastName'=>null]])
	 * 						->andWhere((new Criteria())
	 * 							->where(['firstName', => ['Merijn', 'Wesley']]) //IN condition with array
	 * 							->orWhere(['emailAddresses.email'=>'test@intermesh.nl'])
	 * 						)
	 * 		);
	 * ```````````````````````````````````````````````````````````````````````````
	 * 
	 * <p>Produces:</p>
	 * 
	 * ```````````````````````````````````````````````````````````````````````````
	 * SELECT t.*, count(emailAddresses.id) FROM `contactsContact` t
	 * INNER JOIN `contactsContactEmailAddress` emailAddresses ON (`t`.`id` = `emailAddresses`.`contactId`)
	 * WHERE
	 * (
	 * 	`t`.`lastName` IS NOT NULL
	 * )
	 * AND
	 * (
	 * 	(
	 * 		`t`.`firstName` IN ("Merijn", "Wesley")
	 * 	)
	 * 	OR
	 * 	(
	 * 		`emailAddresses`.`email` = "test@intermesh.nl"
	 * 	)
	 * )
	 * AND
	 * (
	 * 	`t`.`deleted` != "1"
	 * )
	 * GROUP BY `t`.`id`
	 * HAVING
	 * (
	 *		count(emailAddresses.id) > 0
	 * )
	 * ```````````````````````````````````````````````````````````````````````````
	 *
	 * @param Query|array|StringUtil $query Query object. When you pass an array a new 
	 * Query object will be autocreated and the array will be passed to 
	 * {@see Query::where()}.
	 * 
	 * @return static This is actually a {@see Store} object but to enable 
	 * autocomplete on the result we've set this to static.
	 * 
	 * You can also convert a store to a string to see the sql query;
	 */
	public static function find($query = null) {
		
		$query = Query::normalize($query);
		
		$calledClassName = get_called_class();
		
		$query->setRecordClassName($calledClassName);
		
		$permissions = static::internalGetPermissions();
		$permissions->setRecordClassName($calledClassName);
		$permissions->applyToQuery($query);
		
		static::fireStaticEvent(self::EVENT_FIND, $calledClassName, $query);
		
		$store = new Store($query);

		return $store;
	}

	/**
	 * Validates all attributes of this model
	 *
	 * You do not need to call this function. It's automatically called in the
	 * save function. Validators can be defined in defineValidationRules().
	 * 
	 * Don't override this function. Override {@see internalValidate()} instead.
	 * 
	 * @see defineValidationRules()
	 * @return boolean
	 */
	public final function validate() {
		
		if($this->fireEvent(self::EVENT_BEFORE_VALIDATE, $this) === false){			
			return false;
		}
		
		$success = $this->internalValidate();
		
		if(!$success) {
			\IFW::app()->debug(static::class.'::internalValidate returned '.var_export($success, true));
		}
		
		return $success;
	}	
	
	protected function internalValidate() {
		
		
		if ($this->isNew()) {
			//validate all columns
			$fieldsToCheck = $this->getTable()->getColumnNames();
		} else {
			//validate modified columns
			$fieldsToCheck = array_keys($this->getModifiedAttributes());
		}
		
		$uniqueKeysToCheck = [];

		foreach ($fieldsToCheck as $colName) {
			$column = $this->getColumn($colName);
			if(!$this->validateRequired($column)){
				//only one error per column
				continue;
			}
			
			if (!empty($column->length) && !empty($this->$colName) && StringUtil::length($this->$colName) > $column->length) {
				$this->setValidationError($colName, \IFW\Validate\ErrorCode::MALFORMED, 'Length can\'t be greater than '.$column->length);
			}
			
			if($column->unique && isset($this->$colName)){
				//set imploded key so no duplicates will be checked
				$uniqueKeysToCheck[implode(':', $column->unique)] = $column->unique;
			}
		}
		
		$validators = [];
		
		foreach(self::getValidationRules() as $validator){
			if(in_array($validator->getId(), $fieldsToCheck)){
				$validators[]=$validator;
			}			 
		}		
		
		
		//Disabled because it's better for performance to let mysql handle this.
//		foreach($uniqueKeysToCheck as $uniqueKeyToCheck){
//			$validator = new ValidateUnique($uniqueKeyToCheck[0]);
//			$validator->setRelatedColumns($uniqueKeyToCheck);
//			$validators[] = $validator;
//		}
		
		foreach ($validators as $validator) {
			if (!$validator->validate($this)) {

				$this->setValidationError(
								$validator->getId(), 
								$validator->getErrorCode(), 
								$validator->getErrorDescription(),
								$validator->getErrorData()
								);
			}
		}
		
		if($this->fireEvent(self::EVENT_AFTER_VALIDATE, $this) === false){			
			return false;
		}
		
		return !$this->hasValidationErrors();
	}	
	
	
	/**
	 * Find all keys that will be set by a relational save.
	 * 
	 * For example when saving a Car that has a required Dashboard. dashboardId 
	 * will be set to the id of the Dashboard after the relational save.	 
	 * 
	 * $car = new Car();	 
	 * $car->dashboard = new Dashboard();
	 * 
	 * $car->dashboardId is null but will be set to the ID of the Dashboard after 
	 * save. This has to be taken into account when saving.
	 */
	
	private function findKeysToBeSetByARelation() {
		
		$keysToBeSet = [];
		//loop through already set relations. $this->relations hold those
		foreach($this->relations as $relationName => $relationStore) {
			
			$relation = $this->getRelation($relationName);
			
			if($relation->hasMany()) {
				continue;
			}
			
			if(!$relationStore->isModified()) {
				continue;
			}
			
			$record = $relationStore[0];
			
			if(!($record instanceof self)) {				
				continue;
			}
			
			$keys = $relation->getKeys();
						
			$toRecordName = $relation->getToRecordName();
			$toPks = $toRecordName::getPrimaryKey();		
						
			foreach($keys as $fromField => $toField) {
				//from field will be set by primary key of relation
				if(in_array($toField, $toPks)) {
					$keysToBeSet[] = $fromField;
				}
			}
		}
		
		return $keysToBeSet;
	}
	
	
	private function validateRequired(Column $column) {
		
		$ignore = $this->findKeysToBeSetByARelation();
		
		if ($column->required && !in_array($column->name, $ignore)) {

			switch ($column->dbType) {

				case 'int':
				case 'tinyint':
				case 'bigint':

				case 'float':
				case 'double':
				case 'decimal':

				case 'datetime':

				case 'date':

				case 'binary':
					if (!isset($this->{$column->name})) {
						$this->setValidationError($column->name, \IFW\Validate\ErrorCode::REQUIRED);
						return false;
					}
				break;
				default:				
					if (empty($this->{$column->name})) {
						$this->setValidationError($column->name, \IFW\Validate\ErrorCode::REQUIRED);
						return false;
					}
				break;
			}
		}
		
		return true;
	}
	
	private function deleteCheckRestrictions(){
		$r = $this->getRelations();

		foreach ($r as $name => $relation) {
			if($relation->deleteAction === Relation::DELETE_RESTRICT) {
				if (!$relation->hasMany()){
					$result = $this->$name;
				}else{
					$result = $this->$name->single();
				}

				if ($result) {
					throw new DeleteRestrict($this, $relation);
				}
			}
		}
	}
	
	private function deleteCascade(){
		$r = $this->getRelations();

		foreach ($r as $name => $relation) {
			if($relation->deleteAction === Relation::DELETE_CASCADE) {
				$result = $this->$name;

				if ($result instanceof Store) {
					//has_many relations result in a statement.
					foreach ($result as $child) {
						if (!$child->equals($this)) {
							if(!$child->delete()){
								return false;
							}
						}
					}
				} elseif ($result) {
					//single relations return a model.
					if(!$result->delete()) {
						return false;
					}
				}
			}
		}
		
		return true;
	}
	
	/**
	 * Checks if the given record is equal to this record
	 * 
	 * @param \IFW\Orm\Record $record
	 * @return boolean
	 */
	public function equals(Record $record) {
		if($record->getClassName() != $this->getClassName()) {
			return false;
		}
		
		if($record->isNew() || $this->isNew()) {
			return false;
		}
		
		$pk1 = $this->pk();
		$pk2 = $record->pk();
		
		$diff = array_diff($pk1, $pk2);
		
		return empty($diff);
	}
	
	/**
	 * Delete's the model from the database
	 * 
	 * You should rarely use this function. For example when cleaning up soft 
	 * deleted models.
	 *
	 * <p>Example:</p>
	 * ```````````````````````````````````````````````````````````````````````````
	 * $model = User::findByPk(2);
	 * $model->deleteHard();
	 * ```````````````````````````````````````````````````````````````````````````
	 *
	 * @return boolean
	 */
	public final function deleteHard() {		
		
		if(!$this->getColumn('deleted')) {
			throw new \Exception($this->getClassName()." does not support soft delete. Use delete() instead of hardDelete()");
		}
		
		return $this->processDelete(true);
	}
	
	/**
	 * Delete's the model from the database or set's it to deleted if soft delete 
	 * is supported.
	 *
	 * Example:
	 * 
	 * ```php
	 * $model = User::findByPk(2);
	 * $model->delete();
	 * ```
	 * 
	 * Don't override this method. Override {@see internalDelete()} instead. The 
	 * internalDelete function is called after permission checks and validation.
	 * 
	 * No database transactions are used because you should use MySQL cascading 
	 * deletes if you want to remove hard relations. For example a contact is 
	 * removed with it's email addresses with a MySQL cascade delete key.
	 * 
	 * Deletion of soft relations should probably be done without a database 
	 * transaction. For example when deleting a folder with files it is OK to 
	 * partially complete and removing the files on disk could cause problems when
	 * you rollback a transaction because the files on disk have been removed in
	 * the internalDelete() override of the file model.
	 *
	 * @return boolean
	 */
	public final function delete() {		
//		IFW::app()->debug('delete '. $this->getClassName());
		return $this->processDelete();
	}
	
	
	private function processDelete($hard = false) {
		
		if(!$this->getPermissions()->can(PermissionsModel::PERMISSION_WRITE)) {
			throw new Forbidden("You're not permitted to delete ".$this->getClassName()." ".var_export($this->pk(), true));
		}

		if ($this->isNew) {
			IFW::app()->debug("Not deleting because this model is new");
			return true;
		}
		
		$this->deleteCheckRestrictions();		
		
		$success = $this->internalDelete($hard);
		if(!$success) {
			\IFW::app()->debug(static::class.'::internalDelete returned '.var_export($success, true));
		}
		if(!$this->fireEvent(self::EVENT_AFTER_DELETE, $this, $hard)) {			
			$success = false;
		}		
		
		return $success;
	}
	
	/**
	 * Get's the database connection
	 * 
	 * @return Connection
	 */
	protected function getDbConnection() {
		return IFW::app()->getDbConnection();
	}
	
	/**
	 * 
	 * Internal delete method
	 * 
	 * If you want to add functionality before or after delete then override this 
	 * method. This method is executed after 
	 * validation and permission checks so you don't have to worry about that.
	 * 
	 * @param boolean $hard true when the model will be deleted from the database even if it supports soft deletion.
	 * @return boolean
	 * @throws Exception
	 */
	protected function internalDelete($hard) {	
		
		if(!$this->fireEvent(self::EVENT_BEFORE_DELETE, $this, $hard)){			
			$this->getDbConnection()->rollBack();
			return false;
		}	
	
		if(!$this->deleteCascade()) {
			$this->getDbConnection()->rollBack();
			return false;
		}
		
		$soft = !$hard && $this->getColumn('deleted');
		$success = $soft ? $this->internalSoftDelete() : $this->internalHardDelete();

		if (!$success){
			
			$method = $soft ? 'internalSoftDelete' : 'internalHardDelete';
			\IFW::app()->debug(static::class.'::'.$method.' returned '.var_export($success, true));
			
			throw new Exception("Could not delete from database");
		}

		$this->isNew = !$soft;		
		$this->isDeleted = true;
		
		return true;
	}

	private function internalHardDelete() {		
		return $this->getDbConnection()->createCommand()->delete($this->tableName(), $this->pk())->execute();
	}
	
	private function internalSoftDelete() {	
		
		if($this->isDeleted()){
			return true;
		}
		
		$this->deleted = true;
		
		if($this->update()){
			$this->markDeleted = false;
			return true;
		}else
		{
			return false;
		}
	}
	
	/**
	 * Tells if the record for this object is already deleted from the database.
	 * 
	 * @return boolean
	 */
	public function isDeleted(){
		return $this->isDeleted;
	}


	
	/**
	 * When the API returns this model to the client in JSON format it uses 
	 * this function to convert it into an array. 
	 * 
	 * 
	 * {@inheritdoc}
	 * 
	 * @param string $properties The properties that will be returned. By default 
	 * all properties will be returned except for relations 
	 * (See {@see getDefaultApiProperties()}. However modified relations will 
	 * always be returned.
	 * 
	 * @return array
	 */
	public function toArray($properties = null){	
	
		//If relations where modified then return them too
//		if(!empty($this->relations)) {
//			$defaultProperties = $this->getDefaultApiProperties();
//			$properties = new ReturnProperties($properties, $defaultProperties);	
			
//			foreach($this->relations as $relationName => $store) {				
//				Don't do this because it can result in infinite loops
//				if(!isset($properties[$relationName]) && $store->isModified()) {
//					
//					IFW::app()->debug("Adding extra return property '".$relationName."' in ".$this->getClassName()."'::toArray() because it was modified.");
//					
//					$properties[$relationName] = '';	
//				}
//			}
//		}
		
		
	
		$array = parent::toArray($properties);
		
		if(!isset($array['validationErrors']) && $this->hasValidationErrors()) {
			$array['validationErrors'] = $this->getValidationErrors();
		}
		
		//Always add primary key
		foreach($this->getTable()->getColumns() as $column) {
			if($column->primary && !isset($array[$column->name])) {
				$array[$column->name] = $this->{$column->name};
			}
		}
		
		//Add className		
//		$array['className'] = self::getClassName();
		
		//Add validation errors even if not requested
		if($this->hasValidationErrors() && !isset($array['validationErrors'])) {
			$array['validationErrors'] = $this->getValidationErrors();
		}		
		
		$this->fireEvent(self::EVENT_TO_ARRAY, $this, $array);
		
		return $array;
	}	
	
	public static function getDefaultReturnProperties() {
		$props =  array_diff(parent::getReadableProperties(), ['validationErrors','modified', 'modifiedAttributes', 'markDeleted']);
		
		return implode(',', $props);
	}		

	/**
	 * Create a hasMany relation. 
	 * 
	 * For example a contact has many email addresses.
	 * 
	 * ```````````````````````````````````````````````````````````````````````````
	 * public static function defineRelations() {
	 *	...
	 * 
	 *	self::hasMany('emailAddresses', ContactEmailAddress::class, ["id" => "contactId"]);
	 * 
	 *	...
	 * }
	 * ```````````````````````````````````````````````````````````````````````````
	 *
	 * @param string $relatedModelName The class name of the related model. eg. UserGroup::class
	 * @param string $keys The relation keys. eg ['id'=>'userId']
	 * @return Relation
	 */
	public static function hasMany($name, $relatedModelName, array $keys){
		$calledClass = static::class;
		
		if(!isset(self::$relationDefs[$calledClass])) {
			self::$relationDefs[$calledClass] = [];
		}

		return self::$relationDefs[$calledClass][$name] = new Relation($name, $calledClass,$relatedModelName, $keys, true);
	}
	
	/**
	 * Create a has one relation. 
	 * 
	 * For example a user has one contact
	 * 
	 * ```````````````````````````````````````````````````````````````````````````
	 * public static function defineRelations() {
	 *	...
	 * 
	 *	self::hasOne('userGroup', Contact::class, ["id" => "userId"]);
	 * 
	 *	...
	 * }
	 * ```````````````````````````````````````````````````````````````````````````
	 *
	 * @param string $name The name of the relation
	 * @param string $relatedModelName The class name of the related model. eg. UserGroup::class
	 * @param string $keys The relation keys. eg ['id'=>'userId']
	 * @return Relation
	 */
	public static function hasOne($name, $relatedModelName, array $keys){
		$calledClass = static::class;
		
		if(!isset(self::$relationDefs[$calledClass])) {
			self::$relationDefs[$calledClass] = [];
		}

		return self::$relationDefs[$calledClass][$name] = new Relation($name, $calledClass,$relatedModelName, $keys, false);
	}
	
//	
//	/**
//	 * Copy this model
//	 * 
//	 * It only copies the database attributes and relations that are 
//	 * {@see Relation::isIdentifying()} and not {@see Relation::isBelongsTo()}.
//	 * 
//	 * ```````````````````````````````````````````````````````````````````````````
//	 * $model = $model->copy();	
//	 * ```````````````````````````````````````````````````````````````````````````
//	 * 
//	 * 
//	 * @param array $attributes
//	 * @return \self
//	 */
//	public function copy($properties) {
//		$copy = new static;
//		
//		//parent doesn't add PK's
////		$array = parent::toArray($properties);
//		
//		foreach($this->getColumns() as $column) {
//			if(!$column->primary || !$column->autoIncrement) {
//				$copy->{$column->name} = $this->{$column->name};
//			}
//		}
//		
//		foreach($this->getRelations() as $relation) {
//			if($relation->isIdentifying() && !$relation->isBelongsTo()) {
//				if($relation->hasMany()) {
//					foreach($this->{$relation->getName()} as $relatedModel) {
//						$copy->{$relation->getName()}[] = $relatedModel->copy();
//					}
//				}else
//				{
//					$relatedModel = $this->{$relation->getName()};
//					if($relatedModel) {
//						$copy->{$relation->getName()} = $relatedModel->copy();
//					}
//				}
//			}
//		}
//		return $copy;
//	}

	/**
	 * Truncates the modified database attributes to the maximum length of the 
	 * database column. Can be useful when importing stuff.
	 */
	public function truncateModifiedAttributes() {
		foreach($this->getModifiedAttributes() as $attributeName => $oldValue) {
			$this->$attributeName = mb_substr($this->$attributeName, 0, $this->getColumn($attributeName)->length);
		}
	}
	
	/**
	 * Creates the permissions model
	 * 
	 * By default only admins can access. Override this method to give it other
	 * permissions.
	 * 
	 * @return AdminsOnly
	 */
	protected static function internalGetPermissions() {
		return new AdminsOnly();
	}	
	
	private static $allowRelations = [];
	
	/**
	 * Bypass record read permissions for specific relational queries
	 * 
	 * @example Allow the customer contact of an invoice to be queried even if 
	 * the user doesn't have read permissions for the contact.
	 * 
	 * ```php
	 * Invoice::allow('customer');
	 * ```
	 * 
	 * @param string $relationPath "contact" or deeper "contact.organizations"
	 */
	public static function allow($relationPath, array $permissionTypes = [PermissionsModel::PERMISSION_READ]) {
		
		if(!isset(self::$allowRelations[static::class])) {
			self::$allowRelations[static::class] = [];
		}
		
		$parts = explode('.', $relationPath);		
		self::$allowRelations[static::class][$parts[0]] = $permissionTypes;
		
		if(count($parts) > 1) {
			$model = static::getRelation(array_shift($parts))->getToRecordName();
			foreach($parts as $part) {

				$model::allow($part, $permissionTypes);
				$model = $model::getRelation($part)->getToRecordName();			
			}
		}
	}	
	
	private static function relationIsAllowed($relationName) {

		if(!isset(self::$allowRelations[static::class][$relationName])) {
			return false;
		}
		
		return self::$allowRelations[static::class][$relationName];
	}
	
	/**
	 * Get the permissions model
	 * 
	 * See {@see PermissionsModel} for more information about how to implement 
	 * record permissionss
	 * 
	 * Override {@see internalGetPermissions()} to implement another permissons model.
	 * 
	 * @return PermissionsModel
	 */
	public final function getPermissions() {		
		if(!isset(self::$permissions[static::class])) {
			self::$permissions[static::class] = static::internalGetPermissions();
		}
		
		self::$permissions[static::class]->setRecord($this);
		
		return self::$permissions[static::class];
	}
}
