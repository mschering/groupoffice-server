<?php
namespace GO\Core\Resources\Controller;

use GO\Core\Controller;
use IFW\Exception\NotFound;
use IFW\Fs\Folder;

class DownloadController extends Controller {
	public function download($moduleName, $path) {
		$module = new $moduleName;
		
		$moduleFolder = new Folder($module->findPath());
		$resourcesFolder = $moduleFolder->getFolder('Resources');
		
		$file = $resourcesFolder->getFile($path);
		
		if(!$file->exists()) {
			throw new NotFound('File '.$path.' not found in resources of module '.$moduleName);
		}
	
		$file->output();
	}
}