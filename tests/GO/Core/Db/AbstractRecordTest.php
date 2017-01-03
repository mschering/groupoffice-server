<?php

namespace IFW\Orm;

use DateTime;
use GO\Core\Users\Model\Group;
use GO\Core\Users\Model\User;
use GO\Modules\GroupOffice\Contacts\Model\Contact;
use GO\Modules\GroupOffice\Contacts\Model\EmailAddress;
use PHPUnit_Framework_TestCase;

/**
 * The App class is a collection of static functions to access common services
 * like the configuration, reqeuest, debugger etc.
 */
class AbstractRecordTest extends PHPUnit_Framework_TestCase {

	public function testDateSet() {

		$user = new User();
		$user->setValues([
			"modifiedAt" => "2007-04-05T12:30:00+02:00"	
		]);
		$this->assertEquals(new DateTime("2007-04-05T10:30:00Z"), $user->modifiedAt);
	}
	
	public function testFindModule() {
		$contact = new Contact();
		$this->assertEquals(\GO\Modules\GroupOffice\Contacts\Module::class, $contact->findModuleName());
	}
	
	
	public function testAll(){
		
		//login as admin
//		$admin = User::findByPk(1);
//		$admin->setCurrent();
		
		$this->_testFindByPkReference();
		
		$this->_testCreateUser();
		$this->_testJoinRelation();
		$this->_testManyMany();
		$this->_testSetHasOneWithExisting();
		$this->_testHasOneWithNew();
//		$this->_testSetBelongsTo();
		$this->_testHasMany();
		
		$this->_testIsNew();
		
//		$this->_testCopy();
		$this->_testDelete();
		
		
		
		
	}
	
	
//	
//	public function testModifyingHasOneRelation() {
//		
//		$contact = new Contact();
//		$contact->setValues([
//				'firstName' => 'Test',
//				'lastName' => 'Contact',
//				'organization' => [
//						'name' => 'Test 1'
//				]
//		]);	
//
//		$contact->save();
//		
//		
//		$contact->organization->name = 'Test 2';
//		$contact->save();
//		
//		$this->assertEquals('Test 2', $contact->organization->name);
//		
//	}
	
	private function _testJoinRelation () {
		$query = (new Query())->joinRelation('group', true);
		
		$usersWithGroups = User::find($query);
		
		foreach($usersWithGroups as $user){
//			echo $user->username.":".$user->group->name."\n";
			
//			var_dump($user);
		}		
		
		
		$query = (new Query())
						->joinRelation('groups', false)
						->where(['groups.id'=>  Group::ID_EVERYONE]);
		
		$usersFromEveryOne = User::find($query);
		
		$allUsers = User::find();
		
		$this->assertEquals($allUsers->getRowCount(), $usersFromEveryOne->getRowCount());
		
	}
	
	private function _testFindByPkReference() {
		$user1 = User::find(['id' => 1])->single();
		$user2 = User::find(['id' => 1])->single();
//		$user3 = clone User::find(['id' => 1])->single();

		$user1->username = date('Ymdgis');
		
		
		$this->assertEquals($user1->username, $user2->username);
		$user1->reset();
	}
	
	
	private function _testCreateUser(){
		//Set's all groups on test user.
		$user = User::find(['username' => 'unittest'])->single();
		
		if($user) {		
			$user->deleteHard();
		}
		
		
//		if (!$user) {
			$user = new User();
			$user->email = 'unittest@unittest.dev';
			$user->username = 'unittest';
			$user->password = 'Test123!';
//		}

		$user->save();

		$success = $user->save() !== false;
		
		if(!$success) {
			var_dump($user);
		}
		
		$this->assertEquals(true, $success);
	}
	
	

	private function _testManyMany() {		

		$user = User::find(['username' => 'unittest'])->single();

		//Testing relational setters		
		$groups = Group::find();		

		foreach ($groups as $group) {
			$allGroups[] = $group;
			$groupArr = $group->toArray('id,name');
			unset($groupArr['className']);
			$groupAttributes[] = $groupArr;
		}
	

		//do it again but with models instead of primary keys
		$user->groups = $allGroups;
		
		$success = $user->save() !== false;
		
		$this->assertEquals(true, $success);

		$this->assertEquals(count($allGroups), count($user->groups->all()));


		//do it again but with arrays instead of primary keys
		$user->groups = $groupAttributes;
		$success = $user->save() !== false;

		$this->assertEquals(true, $success);
		
		$this->assertEquals(count($allGroups), count($user->groups->all()));		
	}
	

	private function _testSetHasOneWithExisting() {
		
		$user = User::find(['username' => 'unittest'])->single();
		
		$groupOfUser = $user->group;
		
		$this->assertEquals(true, is_a($groupOfUser, Group::class));

		$user->group = $groupOfUser;
		$success = $user->save() !== false;

		$this->assertEquals(true, $success);
		
		$this->assertEquals($user->group->id, $groupOfUser->id);
	}
	
	private function _testHasOneWithNew() {
			
		
		
		$contactAttr = ['firstName' => 'Test', 'lastName'=>'Has One'];		
		$contact = Contact::find($contactAttr)->single();
		if($contact){
			$contact->deleteHard();
		}
		
		$user = User::find((new Query())->where(['username' => 'unittest2'])->withDeleted())->single();
		
		if($user) {		
			$user->deleteHard();
		}
		
		$user = new User();
		$user->email = 'unittest@unittest.dev';
		$user->username = 'unittest2';
		$user->password = 'Test123!';
		
		$contact = new Contact();
		$contact->setValues($contactAttr);		
		$contact->user = $user;
		
		
		
		$success = $contact->save() !== false;

		$this->assertEquals(true, $success);
		
		$this->assertEquals($contact->user->username, $user->username);
		
		

	}
	
	private function _testHasMany(){
		
		
		$contactAttr = ['firstName' => 'Test', 'lastName'=>'Has One'];		
		$contact = Contact::find($contactAttr)->single();
		if($contact){
			$contact->deleteHard();
		}
		
		$email = new EmailAddress();
		$email->email = 'test3@intermesh.nl';
		
		
		$contactAttr['emailAddresses'] = [
				['email'=>'test1@intermesh.nl','type'=>'work'],
				['email'=>'test2@intermesh.nl','type'=>'work'],
				$email				
		];
		
		$contactAttr['phoneNumbers'] = [
				['number'=>'1234567890','type'=>'work']				
		];
		
		$contact = new Contact();
		$contact->setValues($contactAttr);			
		
		
				
		$success = $contact->save() !== false;		

		$this->assertEquals(true, $success);		
		$this->assertEquals(count($contactAttr['emailAddresses']), $contact->emailAddresses->getRowCount());		
		
		$firstEmail = $contact->emailAddresses->single();
		$firstEmail->type='home';
		
		//update single email
		$contact->emailAddresses[] = $firstEmail;
		$contact->emailAddresses[] = ['email'=>'piet@intermesh.nl'];
		
		
//		var_dump($contact);
//		exit();
		
		
		
		$success = $contact->save() !== false;		
		
		
		$this->assertEquals(true, $success);
		
		
		$firstEmail = $contact->emailAddresses->single();
		$this->assertEquals('home', $firstEmail->type);
		
	}
	
	
//	private function _testCopy() {
//		$contactAttr = ['firstName' => 'Test', 'lastName'=>'Has One'];		
//		$contact = Contact::find($contactAttr)->single();
//
//		$copy = $contact->copy();		
//		$success = $copy->save() !== false;		
//		
//		$this->assertEquals(true, $success);
//		
//		$arr1 = $contact->toArray('firstName,lastName,emailAddresses[email,type],customfields');
//		$arr2 = $copy->toArray('firstName,lastName,emailAddresses[email,type],customfields');
//		
//		unset($arr1['id']);
//		unset($arr2['id']);
//		
//		
//		$this->assertEquals($arr1, $arr2);
//		$copy->deleteHard();
//	}
	

	private function _testSetBelongsTo(){
		
//		$user = User::find(['username' => 'unittest'])->single();
		
		//Create contact for test user
		$contactAttr = ['firstName' => 'Test', 'lastName'=>'User'];		
		$contact = Contact::find($contactAttr)->single();
		if($contact){
			$contact->deleteHard();
		}
		
		$organizationAttr = ['name' => 'Test Inc.', 'isOrganization' => true];	
//		echo  Contact::find($organizationAttr)->getQuery()->getBuilder(Contact::class);
		$organization = Contact::find($organizationAttr)->single();
		if($organization) {
			
			$organization->deleteHard();
		} 
		
		$contact = new Contact();
		$contact->setValues($contactAttr);		
		$contact->organization = $organizationAttr;
		
		//array is turned into a contact model
		$this->assertEquals(true, is_a($contact->organization, Contact::class));		
		
		$success = $contact->save() !== false;
		
		
//		var_dump($contact->getRelation('organization')->isBelongsTo());

		$this->assertEquals(true, $success);		
		
		$this->assertEquals(true, is_a($contact->organization, Contact::class));		
	}
	
	private function _testIsNew(){
		$user = new User();
		
		$this->assertEquals(true, $user->isNew());
		
		$user = User::find()->single();
		
		$this->assertEquals(false, $user->isNew());
	}
	
	
	private function _testDelete(){
		$user = User::find(['username' => 'unittest'])->single();
		
		$success = $user->delete();
		
		$this->assertEquals(true, $success);
		//soft delete
		$this->assertEquals(true, $user->deleted);
		
		$success = $user->deleteHard();
		$this->assertEquals(true, $success);
		
		$user = User::find(['username' => 'unittest'])->single();
		
		$this->assertEquals(false, $user);
		
	}
	

}