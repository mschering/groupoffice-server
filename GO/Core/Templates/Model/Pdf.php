<?php
namespace GO\Core\Templates\Model;

use GO\Core\Blob\Model\Blob;
use GO\Core\Orm\Record;
use IFW\Orm\Query;

/**
 * The Pdf model
 *
 * For usage see {@see PdfRenderer}
 * 
 * @property PdfBlock[] $blocks
 * @property Blob $stationaryPdfBlob
 *
 * @copyright (c) 2016, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */
class Pdf extends Record {
	
	/**
	 * 
	 * @var int
	 */							
	public $id;

	/**
	 * 
	 * @var bool
	 */							
	public $deleted;

	/**
	 * 
	 * @var int
	 */							
	public $moduleId;

	/**
	 * 
	 * @var string
	 */							
	public $language;

	/**
	 * 
	 * @var string
	 */							
	public $name;

	/**
	 * 
	 * @var string
	 */							
	public $stationaryPdfBlobId;

	/**
	 * 
	 * @var double
	 */							
	public $marginLeft = 10.0;

	/**
	 * 
	 * @var double
	 */							
	public $marginRight = 10.0;

	/**
	 * 
	 * @var double
	 */							
	public $marginTop = 10.0;

	/**
	 * 
	 * @var double
	 */							
	public $marginBottom = 10.0;

	/**
	 * 
	 * @var bool
	 */							
	public $landscape = false;

	/**
	 * Defaults to A4
	 * @var string
	 */							
	public $pageSize = 'A4';

	/**
	 * Defaults to mm
	 * @var string
	 */							
	public $measureUnit = 'mm';


	
	protected static function defineRelations() {
		
		self::hasMany('blocks', PdfBlock::class, ['id'=>'pdfTemplateId'])->setQuery((new Query())->orderBy(['sortOrder' => 'ASC']));
		self::hasOne('stationaryPdfBlob', Blob::class, ['stationaryPdfBlobId' => 'blobId']);
	}

	
	public function setModuleClassName($moduleClassName) {
		$module = \GO\Core\Modules\Model\Module::find(['name' => $moduleClassName])->single();
		$this->moduleId = $module->id;
	}
	
	protected static function internalGetPermissions() {
		return new \IFW\Auth\Permissions\Everyone();
	}
}
