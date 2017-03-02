<?php

namespace IFW;

use IFW\Data\Object;

/**
 * Debugger class. All entries are stored and the view can render them eventually.
 * The JSON view returns them all.
 * 
 * The client can enable by sending an HTTP header X-Debug=1
 * 
 * Example:
 * 
 * ````````````````````````````````````````````````````````````````````````````
 * IFW::app()->debug($mixed);
 * ````````````````````````````````````````````````````````````````````````````
 * 
 * or:
 * 
 * ````````````````````````````````````````````````````````````````````````````
 * IFW::app()->getDebugger()->debugCalledFrom();
 * ````````````````````````````````````````````````````````````````````````````
 *
 * @copyright (c) 2014, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */
class Debugger extends Object {

	/**
	 * Sets the debugger on or off
	 * @var boolean
	 */
	public $enabled = false;

	/**
	 * The debug entries as strings
	 * @var array
	 */
	private $entries = [];

	/**
	 * Get time in milliseconds
	 * 
	 * @return float Milliseconds
	 */
	public function getMicroTime() {
		list ($usec, $sec) = explode(" ", microtime());
		return ((float) $usec + (float) $sec);
	}

	/**
	 * Add a debug entry. Objects will be converted to strings with var_export();
	 * 
	 * You can also provide a closure function so code will only be executed when
	 * debugging is enabled.
	 *
	 * @todo if for some reason an error occurs here then an infinite loop is created
	 * @param callable|string|object $mixed
	 * @param string $section
	 */
	public function debug($mixed, $section = 'general', $traceBackSteps = 0) {
		
		if(!$this->enabled) {
			return;
		}
		
		if($mixed instanceof \Closure) {
			$mixed = call_user_func($mixed);
		}elseif (!is_scalar($mixed)) {
			$mixed = print_r($mixed, true);
		}
		
		$bt = debug_backtrace(null, 4+$traceBackSteps);
		$caller = $lastCaller = array_shift($bt);		
		//can be called with IFW::app()->debug(). We need to go one step back
		while($caller['function'] == 'debug' || $caller['class'] == self::class) {		
			$lastCaller = $caller;
			$caller = array_shift($bt);
		}
		
		while($traceBackSteps > 0) {			
			$lastCaller = $caller;
			$caller = array_shift($bt);
			$traceBackSteps--;			
		}
		
		$entry = "[" . $this->getTimeStamp() . "][" . $caller['class'] . ":".$lastCaller['line']."] " . $mixed;
		
//		$debugLog = \IFW::app()->getConfig()->getTempFolder()->getFile('debug.log');
//		if($debugLog->isWritable()) {
//			$debugLog->putContents($entry."\n", FILE_APPEND);
//		}
		
		if(PHP_SAPI == 'cli') {
			echo $entry."\n";
//			flush();
//			ob_flush();
		}else {
			$this->entries[] = $entry;
		}
	}

	/**
	 * Add a message that notes the time since the request started in milliseconds
	 * 
	 * @param string $message
	 */
	public function debugTiming($message) {
		$this->debug($this->getTimeStamp() . ' ' . $message, 'timing');
	}

	private function getTimeStamp() {
		return intval(($this->getMicroTime() - $_SERVER["REQUEST_TIME_FLOAT"])*1000) . 'ms';
	}

	public function debugCalledFrom($limit = 5) {

		$this->debug("START BACKTRACE");
		$trace = debug_backtrace();

		$count = count($trace);

		$limit++;
		if ($limit > $count) {
			$limit = $count;
		}

		for ($i = 1; $i < $limit; $i++) {
			$call = $trace[$i];

			if (!isset($call["file"])) {
				$call["file"] = 'unknown';
			}
			if (!isset($call["function"])) {
				$call["function"] = 'unknown';
			}

			if (!isset($call["line"])) {
				$call["line"] = 'unknown';
			}

			$this->debug("Function: " . $call["function"] . " called in file " . $call["file"] . " on line " . $call["line"]);
		}
		$this->debug("END BACKTRACE");
	}
	
	/**
	 * Get the debugger entries
	 * 
	 * @return array
	 */
	public function getEntries() {
		return $this->entries;
	}
	
	/**
	 * Returns the type of a given variable.
	 * 
	 * @param mixed $var
	 * @return string
	 */
	public static function getType($var) {
		if(is_object($var)) {
			return get_class($var);
		}else
		{
			return gettype($var);
		}
	}

}
