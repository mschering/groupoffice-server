<?php
namespace IFW;

use IFW;
use IFW\Data\Object;
use IFW\Exception\Forbidden;
use IFW\Exception\HttpException;
use ReflectionMethod;

/**
 * Abstract controller class
 *
 * The router routes requests to controller actions.
 * All controllers must extend this or a subclass of this class.
 * 
 * {@see Router The router routes requests to controllers}
 *
 * @copyright (c) 2014, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */

abstract class Controller extends Object {
	
	const VIEW_DEFAULT = 'Api';
	
	private $action;	

	/**
	 * Get the action in lower case that is currently being executed
	 * 
	 * @param string
	 */
	protected function getAction() {
		return $this->action;
	}	
	
	/**
	 * Finds a view by name
	 * 
	 * It tries to find the view by interface type (Typically Cli or Web) and
	 * module name.
	 * 
	 * @param string $name If empty the constant {@see VIEW_DEFAULT} is used.
	 * @return View\ViewInterface
	 * @throws \Exception
	 */
	protected function getView($name = null) {		
		
		if(!isset($name)) {
			$name = self::VIEW_DEFAULT;
		}
		
		$router = IFW::app()->getRouter();
		$interfaceType = $router->getInterfaceType(); //Cli or Web		
		$module = $router->getModuleName();
		
		//GO\Modules\Contacts\Web\View\Api
		$view = $moduleView = $module.'\\View\\'.$interfaceType.'\\'.$name;
		
		if(class_exists($view)) {
			return new $view;
		}
		
		$view =  $this->getDefaultView($interfaceType, $name);				
		if(!class_exists($view)) {
			throw new \Exception("Can't find view '".$name."' in '".$moduleView."' and ".$view);
		}
		
		return new $view;
	}
	
	/**
	 * Get the default view when a module view wasn't found
	 * 
	 * @param string $interfaceType Typically Cli or Web {@see Router::getInterfaceType()}
	 * @param string $name Name of the view
	 * @param string eg. IFW\View\Web\Api
	 */
	protected function getDefaultView($interfaceType, $name) {					
		//IFW\Web\View\Api
		$view = 'IFW\\View\\'.$interfaceType.'\\'.$name;				
		return $view;
	}
	
	/**
	 * Authenticate
	 * 
	 * Checks if there's a logged in user and if the user has access to the module
	 * this controller belong too if applicable.
	 * 
	 * Override this for special use cases. By default it checks the presence
	 * of {@see \IFW::app()->auth()->user()}.
	 * 
	 * @return boolean
	 */
	protected function checkAccess(){		
		if(!IFW::app()->getAuth()->checkXSRF()) {
			throw new \Exception("Cross Site Request Forgery check failed");
		}
		
		return true;
	}

	/**
	 * Runs the controller action
	 * 
	 * @param string $action The action name in lower case
	 * @param string[] $routerParams A merge of route and query params
	 */
	public function run($action, array $routerParams) {	

		if(!$this->checkAccess()){
			throw new Forbidden();
		}

		//Should we remove action prefix? Please consider reserved name like "print"
		$this->callMethodWithParams("action".$action, $routerParams);
	}
	

	/**
	 * Runs controller method with URL query and route params.
	 * 
	 * For an explanation about route params {@see Router::routeParams}
	 * 
	 * @param string $methodName
	 * @params array $routerParams A merge of route and query params
	 * @throws HttpException
	 */
	protected function callMethodWithParams($methodName, array $routerParams){
		
		if(!method_exists($this, $methodName)){
			throw new HttpException(501, $methodName." defined but doesn't exist in controller ".static::class);
		}
		
		$method = new ReflectionMethod($this, $methodName);
		$rParams = $method->getParameters();		

		//call method with all parameters from the $_REQUEST object.
		$methodArgs = array();
		foreach ($rParams as $param) {
			if (!isset($routerParams[$param->getName()]) && !$param->isOptional()) {
				throw new HttpException(400, "Bad request. Missing argument '" . $param->getName() . "' for action method '" . get_class($this) . "->" . $methodName . "'");
			}

			$methodArgs[] = isset($routerParams[$param->getName()]) ? $routerParams[$param->getName()] : $param->getDefaultValue();
		}
		
		call_user_func_array([$this, $methodName], $methodArgs);
	}
}