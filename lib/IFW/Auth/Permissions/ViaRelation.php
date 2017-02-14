<?php
namespace IFW\Auth\Permissions;

use Exception;
use IFW\Auth\UserInterface;
use IFW\Orm\Query;
use IFW\Orm\Relation;

/**
 * Permissions model to relay all permission checks to a relation.
 * Typically used in identifying relations like emailAddresses for a contact.
 * 
 * {@see \GO\Modules\GroupOffice\Contacts\Model\EmailAddress}
 */
class ViaRelation extends Model {
	
	protected $relationName;
	
	
	/**
	 * Constructor
	 * 
	 * @param string $relationName
	 */
	public function __construct($relationName) {
		$this->relationName = $relationName;		
	}
	
	protected function internalCan($permissionType, UserInterface $user) {		
		
		$relationName = $this->relationName;
		
		$permissionType = $permissionType == self::PERMISSION_READ ? self::PERMISSION_READ : self::PERMISSION_UPDATE;		
		
		$relatedRecord = $this->record->{$relationName};

		if(!isset($relatedRecord)) {
			throw new Exception("Relation $relationName is not set in ".$this->record->getClassName().", Maybe you didn't select or set the key?");
		}
		
		return $relatedRecord->permissions->can($permissionType, $user);
	}
	
	public function toArray($properties = null) {
		return null;
	}
	
	protected function internalApplyToQuery(Query $query, UserInterface $user) {
		

		if($query->getRelation()) {
			//check if we're doing a relational query from the relation set in $this->relationName.
			//If so we can skip the permissions
			$parent = $query->getRelation()->findParent();

			if($parent && $parent->getName() == $this->relationName) {
				//query is relational and coming from the ViaRelation so no extra query is needed.
				return;
			}
		}
		
		
		$recordClassName = $this->recordClassName;

		$relation = $recordClassName::getRelation($this->relationName);
		
		/* @var $relation  Relation */
		
	
	  $toRecordName = $relation->getToRecordName();
		$subquery = isset($relation->query) ? clone $relation->query : new Query();	
		$subquery->tableAlias($this->relationName);
		
		
		if($relation->getViaRecordName() !== null) {
			
			$linkTableAlias = $relation->getName().'Link';
			
			//ContactTag.tagId -> tag.id
			$on = '';
			foreach($relation->getViaKeys() as $fromField => $toField) {
				$on .= '`'.$linkTableAlias.'`.`'.$fromField.'`=`'.$subquery->getTableAlias().'`.`'.$toField.'`'; 
			}
			//join ContactTag
			$subquery->join($this->viaRecordName, $linkTableAlias, $on);

			foreach($relation->getKeys() as $myKey => $theirKey) {
				$subquery->andWhere($linkTableAlias.'.'.$theirKey.' = `'.$subquery->getTableAlias().'`.`'. $myKey.'`');			
			}
		}else
		{
			foreach($relation->getKeys() as $myKey => $theirKey) {
				$subquery->andWhere('`'.$subquery->getTableAlias().'`.`'.$theirKey. '` = `'.$query->getTableAlias().'`.`'. $myKey.'`');			
			}
		}
		
		self::$enablePermissions = true;
		$store = $toRecordName::find($subquery);		
		self::$enablePermissions = false;
		$query->andWhere(['EXISTS', $store]);
		$query->skipReadPermission();
		
	}

}