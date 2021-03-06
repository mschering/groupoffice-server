<?php
namespace IFW\Db;

use DateTime;
use IFW;
use PHPUnit\Framework\TestCase;

/**
 * The App class is a collection of static functions to access common services
 * like the configuration, reqeuest, debugger etc.
 */
class CommandTest extends TestCase {
	public function testSelect() {
		
		$query = (new Query())
						->select('id,username')
						->from('auth_user')
						->where(['id' => 1]);
		
		$stmt = $query->createCommand()->execute();
		
		$record = $stmt->fetch();
		
		$this->assertArrayHasKey('username', $record);
	}
	
	public function testInsert() {
		$command = IFW::app()->getDbConnection()->createCommand()->insert('auth_user', ['id' => 1, 'username' => 'test']);
		
		$this->assertStringStartsWith('INSERT INTO', $command->toString());
	}
	
	public function testInsertSelect() {
		
		$query = new Query();
		$query->select('id,username')
						->from('auth_user');
		
		$command = IFW::app()->getDbConnection()->createCommand()->insert('auth_user', $query);
		
		$this->assertStringStartsWith('INSERT INTO', $command->toString());
	}
	
	public function testUpdate() {
				
		$command = IFW::app()->getDbConnection()->createCommand()->update('auth_user', ['lastLogin' => new DateTime()], ['id' => 1]);
		
		$this->assertStringStartsWith('UPDATE', $command->toString());
		
		$stmt = $command->execute();
		
		$this->assertEquals(1, $stmt->rowCount());
	}
	public function testDelete() {
				
		$command = IFW::app()->getDbConnection()->createCommand()->delete('auth_user', ['id' => -123]);
		
		$this->assertStringStartsWith('DELETE', $command->toString());
		
	}
}
