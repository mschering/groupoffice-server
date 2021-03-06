<?php
namespace GO\Core\Users\Controller;

use GO\Core\Controller;
use GO\Core\Email\Model\Message;
use GO\Core\Users\Model\User;
use IFW\Exception\Forbidden;
use IFW\Exception\NotFound;
use IFW\Template\VariableParser;
use function GO;



/**
 * The controller for users. Admin group is required.
 * 
 * Uses the {@see User} model.
 *
 * @copyright (c) 2014, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */
class ForgotPasswordController extends Controller {

	public function checkAccess() {
		return true;
	}
	/**
	 * 
	 * @param string $email
	 * @throws NotFound
	 */
	public function send($email) {
		
		$numberOfRecipients = GO()->getAuth()->sudo(function() use ($email) {
			
			$user = User::find(['OR','LIKE', ['email'=>$email, 'emailSecondary'=>$email]])->single();		

			if (!$user) {
				throw new NotFound();
			}

			$token = $user->generateToken();

			//example: "Hello {user.username}, Your token is {token}."
			$templateParser = new VariableParser();
			$templateParser->addModel('token', $token)
							->addModel('user', $user);

			$message = new Message(
							GO()->getSettings()->smtpAccount, 
							GO()->getRequest()->getBody()['subject'], 
							$templateParser->parse(GO()->getRequest()->getBody()['body']),
							'text/plain');

			$message->setTo($email);

			return $message->send();
		
		});

		$this->render(['success' => $numberOfRecipients === 1]);
		
	}
	
	
	public function resetPassword($userId, $token) {
		$user = GO()->getAuth()->sudo(function() use ($userId, $token) {
			$user = User::findByPk($userId);
			
			if(!$user) {
				throw new NotFound();
			}
			
			
			$correctToken = $user->generateToken();
			if($token != $correctToken) {
				throw new Forbidden("Token incorrect");
			}
			
			$user->setValues(GO()->getRequest()->getBody()['data']);
			
			$user->save();
			
			return $user;
			
		});
		
		$this->renderModel($user);
		
	}
}