<?php

namespace GO\Core\Smtp\Model;

use ErrorException;
use GO\Core\Accounts\Model\AccountRecord;
use Swift_Mailer;
use Swift_SmtpTransport;
use Swift_Transport;

/**
 * The SmtpAccount model
 *
 * @copyright (c) 2015, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */
class Account extends AccountRecord {

	
	/**
	 * 
	 * @var int
	 */							
	public $id;

	/**
	 * 
	 * @var string
	 */							
	public $hostname;

	/**
	 * 
	 * @var int
	 */							
	public $port = 25;

	/**
	 * 
	 * @var string
	 */							
	public $encryption;

	/**
	 * 
	 * @var string
	 */							
	public $username;

	/**
	 * 
	 * @var string
	 */							
	public $password;

	/**
	 * The from name of the sent messages
	 * @var string
	 */							
	public $fromName;

	/**
	 * The from email address of the sent messages
	 * @var string
	 */							
	public $fromEmail;
	
	/**
	 * Creator
	 * 
	 * @var int 
	 */
	public $createdBy;

	private function testConnection() {
		$streamContext = stream_context_create(['ssl' => [
						"verify_peer" => false,
						"verify_peer_name" => false
		]]);

		$errorno = null;
		$errorstr = null;
		$remote = $this->encryption == 'ssl' ? 'ssl://' : '';
		$remote .= $this->hostname . ":" . $this->port;

		try {
			$handle = stream_socket_client($remote, $errorno, $errorstr, 10, STREAM_CLIENT_CONNECT, $streamContext);
		}
		catch(ErrorException $e) {
			GO()->debug($e->getMessage());
		}

		if (!is_resource($handle)) {
			$this->setValidationError('hostname', 'connecterror', ['msg' => 'Failed to open socket #' . $errorno . '. ' . $errorstr]);
			return false;
		}
		
		stream_socket_shutdown($handle, STREAM_SHUT_RDWR);

		return true;
	}

	/**
	 * Create a swift transport with these account settings
	 * 
	 * @return Swift_Transport
	 */
	private function createTransport(){
		$transport = Swift_SmtpTransport::newInstance($this->hostname, $this->port);
		
		if(isset($this->encryption)){
			$transport->setEncryption($this->encryption);
		}
		
		if(isset($this->username)){
			$transport->setUsername($this->username);
			$transport->setPassword($this->password);
		}

		return $transport;		
	}
	
	/**
	 * Get the mailer using this account settings
	 * 
	 * @return Swift_Mailer
	 */
	public function createMailer(){
		return new Swift_Mailer($this->createTransport());
	}
	
	/**
	 * {@inheritdoc}
	 * 
	 * Overridden to hide passwords from API
	 * 
	 * @param $returnProperties
	 * @param string
	 */
	public function toArray($returnProperties = null) {
		$attr = parent::toArray($returnProperties);
		
		//protect password		
		unset($attr['password']);// = '***********';

		return $attr;
	}

	public function getName() {
		return $this->fromEmail;
	}
//
//	/**
//	 * 
//	 * @param int $userId
//	 * @return self[]
//	 */
//	public static function findForUser($userId) {
//		
//		$q = (new \IFW\Orm\Query())
//						->select('id,fromEmail')
//						->where(['createdBy' => $userId]);
//		
//		return self::find($q)->all();
//	}

}