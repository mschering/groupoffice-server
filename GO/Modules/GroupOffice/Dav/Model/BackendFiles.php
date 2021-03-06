<?php

/**
 * Copyright Intermesh
 *
 * This file is part of Group-Office. You should have received a copy of the
 * Group-Office license along with Group-Office. See the file /LICENSE.TXT
 *
 * If you have questions write an e-mail to info@intermesh.nl
 *
 * @version $Id: Shared_Directory.class.inc.php 7752 2011-07-26 13:48:43Z mschering $
 * @copyright Copyright Intermesh
 * @author Merijn Schering <mschering@intermesh.nl>
 */

namespace GO\Modules\GroupOffice\Dav\Model;

use GO;
use Sabre;
use GO\Modules\GroupOffice\Files\Model\Node as NodeRecord;
use GO\Modules\GroupOffice\Files\Model\Drive;
use GO\Modules\GroupOffice\Files\Model\Mount;

class BackendFiles extends Directory {

	private static $home = 'Home';

	public function __construct($path) {
		$this->path = $path;
		if(\GO()->getAuth()->isLoggedIn()) {
			$this->node = new NodeRecord();
			$this->node->name = $path;
		}
	}

	function getName() {
		if(empty($this->node) || $this->node->name == '/') {
			return 'files';
		}
		return parent::getName();
	}

	function getChildren() {
		$nodes = [];
		if($this->path == '/') {
			$nodes[] = new self(self::$home);
			foreach ($this->drives() as $drive) {
				$nodes[] = new self($drive->root->name);
			}
			return $nodes;
		}

		return parent::getChildren();
	}
	
	private function drives($name = null) {
		$query = (new \IFW\Orm\Query)
			->joinRelation('owner', 'name')
			->join(Mount::tableName(),'m','t.id = m.driveId AND m.userId = '.GO()->getAuth()->user()->id, 'LEFT')
			->where('m.userId IS NOT NULL');
		if($name !== null) {
			$query->andWhere(['name' => $name]);
		}

		return Drive::find($query);
	}

	public function getChild($name) {
		if($name === self::$home) {
			return new Directory(Drive::home()->root);
		} else {
			return new Directory($this->drives($name)->single()->root);
		}
	}

	/**
	 * Creates a new file in the directory
	 *
	 * data is a readable stream resource
	 *
	 * @param StringHelper $name Name of the file
	 * @param resource $data Initial payload
	 * @return void
	 */
	public function createFile($name, $data = null) {
		throw new Sabre\DAV\Exception\Forbidden();
	}

	/**
	 * Creates a new subdirectory
	 *
	 * @param StringHelper $name
	 * @return void
	 */
	public function createDirectory($name) {
		throw new Sabre\DAV\Exception\Forbidden();
	}

	/**
	 * Deletes all files in this directory, and then itself
	 *
	 * @return void
	 */
	public function delete() {
		throw new Sabre\DAV\Exception\Forbidden();
	}

	/**
	 * Returns the last modification time, as a unix timestamp
	 *
	 * @return int
	 */
	public function getLastModified() {
		$absolute = realpath(\GO()->getConfig()->getDataFolder()->getPath());
		return filemtime($absolute);
	}

}
