<?php
namespace GO\Core;

use Exception;
use IFW\Auth\Exception\LoginRequired;
use IFW\Controller as IFWController;
use IFW\Data\Model;
use IFW\Data\Store;
use IFW\Orm\Record;

abstract class Controller extends IFWController {
	
	/**
	 * Set data to render
	 * 
	 * @var array 
	 */
	protected $responseData = [];
	
	
	private $rendered;
	
	/**
	 * Checks if there's a logged in user
	 * 
	 * @return boolean
	 * @throws LoginRequired
	 */
	public function checkAccess() {
		
		if(!\IFW::app()->getAuth()->isLoggedIn())
		{
			throw new LoginRequired();
		}
		
		return parent::checkAccess();
	}
	
	protected function getDefaultView($interfaceType, $name) {
		
		$view = 'GO\\Core\\View\\'.$interfaceType.'\\'.$name;				
		return $view;
		
//		return parent::getDefaultView($interfaceType, $name);
	}
	
	/**
	 * Helper funtion to render an array into JSON
	 * 
	 * @param array $data
	 * @throws Exception
	 */
	protected function render(array $data = [], $viewName = null) {
		
		$this->dataToString($data, $viewName);
		
		if(GO()->getEnvironment()->isCli()) {
			echo $this->rendered;
		}else
		{
			GO()->getResponse()->send($this->rendered);
		}
	}	
	
	private function dataToString(array $data, $viewName = null) {
		$view = $this->getView($viewName);
		$view->render(array_merge($this->responseData, $data));	
		
		$this->rendered = $view;
	}

	/**
	 * Used for rendering a model response
	 * 
	 * @param Record $models
	 */
	protected function renderModel(Model $model, $returnProperties = null) {
		
		//For HTTP Caching
//		if (!GO()->getRequest()->getMethod() == 'GET' && isset($model->modifiedAt)) {
//			GO()->getResponse()->setModifiedAt($model->modifiedAt);
//			GO()->getResponse()->setEtag($model->modifiedAt->format(\IFW\Util\DateTime::FORMAT_API));
//			GO()->getResponse()->abortIfCached();
//		}
//
		$response = ['data' => $model->toArray($returnProperties)];
		$response['success'] = true;
		
		//add validation errors even when not requested		
		if(method_exists($model, 'hasValidationErrors') && $model->hasValidationErrors()){			
			$response['success'] = false;
			GO()->getResponse()->setStatus(422, 'Record validation failed');
		}
		return $this->render($response);
		
	}
	
	/**
	 * Used for rendering a store response
	 * 
	 * @param Store|array $store
	 */
	protected function renderStore($store) {
		
		if(is_array($store)) {
			$store = new \IFW\Data\Store($store);
		}
		$data = $store->toArray();
		
		$this->dataToString([
				'data' => $data,
//				'count' => count($store) //Not needed by our webclient now but might be useful for other implementations. Perhaps by supplying a param returnCount=1 ?
						]);
		//generate an ETag for HTTP Caching
//		GO()->getResponse()->setETag(md5($this->rendered));
//		GO()->getResponse()->abortIfCached();
		
		
		if(GO()->getEnvironment()->isCli()) {
			echo $this->rendered;
		}else
		{
			GO()->getResponse()->send($this->rendered);
		}
	}
	
	
	
	
}
