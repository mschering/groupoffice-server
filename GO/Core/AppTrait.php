<?php

namespace GO\Core;

use Exception;
use GO\Core\Email\Model\Mailer;
use GO\Core\Log\Model\Entry;
use GO\Core\Settings\Model\Settings;
use IFW\Orm\Record;

trait AppTrait {

	/**
	 *
	 * @var Mailer
	 */
	private $mailer;

	/**
	 * Get the mailer component to send e-mail messages.
	 * 
	 * @return Mailer
	 */
	public function getMailer() {
		if (!isset($this->mailer)) {
			$this->mailer = new Mailer();
		}
		return $this->mailer;
	}

	/**
	 * Get the available modules
	 * 
	 * @example
	 * ````````````````````````````````````````````````````````````````````````````
	 * $modules = GO()->getModules();
	 * 
	 * foreach($modules as $moduleClassName) {
	 * 	echo $moduleClassName;
	 * }
	 * ````````````````````````````````````````````````````````````````````````````
	 * 
	 * @return string[]
	 */
	public function getModules() {
		if (!isset($this->moduleCollection)) {
			$this->moduleCollection = new Modules\ModuleCollection();
		}
		return $this->moduleCollection;
	}

	/**
	 * Get the system wide settings record
	 * 
	 * @return Settings
	 */
	public function getSettings() {
		$record = Settings::find()->single();
		if (!$record) {
			$record = new Settings();
		}

		return $record;
	}
	
	
	private static $logEnabled = true;
	
	/**
	 * Suspend logging to the database via the {@see log()} function
	 */
	public static function logSuspend() {
		self::$logEnabled = false;
	}
	
	/**
	 * Resume logging to the database via the {@see log()} function
	 */
	public static function logResume() {
		self::$logEnabled = true;
	}

	/**
	 * 
	 * @param type $type
	 * @param type $type
	 * @param type $description
	 * @param Record $record
	 * @throws Exception
	 */
	public function log($type, $description, Record $record = null) {
		
		if(!self::$logEnabled) {
			return false;
		}
		
		$log = new Entry();

		if(isset($record)) {
			$log->setRecord($record);
		}
		
		$log->type = $type;
		$log->description = $description;
		$log->moduleName = $record->findModuleName();
//		var_dump($log->moduleName);
		if(!$log->moduleName) {
			throw new Exception("Module not found for ".$record->getClassName());
		}

		if (!$log->save()) {
			throw new Exception("Could not save log entry: " . var_export($log->getValidationErrors(), true));
		}

		$this->debug($description, $type, 1);
	}

	public function error($description, Record $record = null) {
		return $this->log('error', $description, $record);
	}

}
