<?php

namespace GO\Core;

use GO\Core\Accounts\Controller\AccountController;
use GO\Core\Auth\Browser\Controller\AuthController;
use GO\Core\Blob\Controller\BlobController;
use GO\Core\Cron\Controller\JobController;
use GO\Core\CustomFields\Controller\FieldController;
use GO\Core\CustomFields\Controller\FieldSetController;
use GO\Core\CustomFields\Controller\ModelController;
use GO\Core\Install\Controller\SystemController;
use GO\Core\Log\Controller\EntryController;
use GO\Core\Modules\Controller\ModuleController;
use GO\Core\Modules\Controller\PermissionsController;
use GO\Core\Notifications\Controller\NotificationController;
use GO\Core\Resources\Controller\DownloadController;
use GO\Core\Settings\Controller\SettingsController;
use GO\Core\Smtp\Controller\AccountController as SmtpAccountController;
use GO\Core\Tags\Controller\TagController;
use GO\Core\Templates\Controller\MessageController;
use GO\Core\Templates\Controller\PdfController;
use GO\Core\Upload\Controller\FlowController;
use GO\Core\Upload\Controller\TempThumbController;
use GO\Core\Users\Controller\AdminUserController;
use GO\Core\Users\Controller\ForgotPasswordController;
use GO\Core\Users\Controller\GroupController;
use GO\Core\Users\Controller\ThumbController;
use GO\Core\Users\Controller\UserController;
use IFW\Cli\Router as Router2;
use IFW\Modules\Module as BaseModule;
use IFW\Web\Router;

class Module extends BaseModule {

	public static function defineWebRoutes(Router $router) {
		
		$router->addRoutesFor(SettingsController::class)
						->get('settings', 'read')
						->put('settings', 'update')
						->post('settings/testSmtp', 'testSmtp');
		
		$router->addRoutesFor(DownloadController::class)
						->get('resources/:moduleName/*path', 'download');

		$router->addRoutesFor(AccountController::class)
						->crud('accounts', 'accountId')
						->get('accounts/sync', 'syncAll')
						->get('accounts/:accountId/sync', 'sync');

//		$router->addRoutesFor(FlowController::class)
//						->get('upload', 'upload');
////						->post('upload', 'upload')
						
		$router->addRoutesFor(BlobController::class)
				->get('upload', 'upload')
				->post('upload', 'upload')
				->get('download/:id', 'download')
				->get('thumb/:id', 'thumb');

		$router->addRoutesFor(TempThumbController::class)
						->get('upload/thumb/:tempFile', 'download');



		$router->addRoutesFor(TagController::class)
						->get('tags', 'store')
						->get('tags/0', 'new')
						->get('tags/:tagId', 'read')
						->put('tags/:tagId', 'update')
						->post('tags', 'create')
						->delete('tags/:tagId', 'delete');


		$router->addRoutesFor(SmtpAccountController::class)
						->crud('smtp/accounts', 'accountId');


		$router->addRoutesFor(ModuleController::class)
						->crud('modules', 'moduleName')
						->get('modules/all', 'allModules')
						->get('modules/filters', 'filters');

		$router->addRoutesFor(PermissionsController::class)
						->get('modules/:moduleName/permissions', 'store')
						->post('modules/:moduleName/permissions/:groupId/:action', 'create')
						->delete('modules/:moduleName/permissions/:groupId/:action', 'delete')
						->delete('modules/:moduleName/permissions/:groupId', 'deleteGroup');

		$router->addRoutesFor(SystemController::class)
						->get('system/install', 'install')
						->get('system/upgrade', 'upgrade')
						->get('system/check', 'check');


		$router->addRoutesFor(ModelController::class)
						->get('customfields/models', 'get')
						->get('customfields/models/:modelName','read');

		$router->addRoutesFor(FieldSetController::class)
						->get('customfields/fieldsets/:modelName', 'store')
						->get('customfields/fieldsets/:modelName/0', 'new')
						->get('customfields/fieldsets/:modelName/:fieldSetId', 'read')
						->put('customfields/fieldsets/:modelName/:fieldSetId', 'update')
						->post('customfields/fieldsets/:modelName', 'create')
						->delete('customfields/fieldsets/:modelName/:fieldSetId', 'delete');

		$router->addRoutesFor(FieldController::class)
						->get('customfields/fieldsets/:modelName/:fieldSetId/fields', 'store')
						->get('customfields/fieldsets/:modelName/:fieldSetId/fields/0', 'new')
						->get('customfields/fieldsets/:modelName/:fieldSetId/fields/:fieldId', 'read')
						->put('customfields/fieldsets/:modelName/:fieldSetId/fields/:fieldId', 'update')
						->post('customfields/fieldsets/:modelName/:fieldSetId/fields', 'create')
						->delete('customfields/fieldsets/:modelName/:fieldSetId/fields/:fieldId', 'delete');


		$router->addRoutesFor(JobController::class)
						->crud('cron/jobs', 'jobId')
						->get('cron/run', 'run');


		$router->addRoutesFor(AuthController::class)
						->get('auth', 'isLoggedIn')
						->get('auth/login-by-token/:token', 'loginByToken')
						->post('auth', 'login')
						->delete('auth', 'logout')
						->post('auth/users/:userId/switch-to', 'switchTo');

//		Oauth2Controller::routes()
//				->post('auth/oauth2/token', 'token');

		$router->addRoutesFor(AdminUserController::class)
						->get('auth/users', 'store')
						->get('auth/users/0', 'new')
						->get('auth/users/:userId', 'read')
						->put('auth/users/:userId', 'update')
						->post('auth/users', 'create')
						->delete('auth/users', 'delete')
						->get('auth/users/filters', 'filters');
		
			$router->addRoutesFor(UserController::class)
						->put('auth/users/:userId/change-password', 'changePassword');
		
		$router->addRoutesFor(ForgotPasswordController::class)
						->post('auth/forgotpassword/:email', 'send')
						->post('auth/users/:userId/resetpassword', 'resetPassword');
		
		
		$router->addRoutesFor(ThumbController::class)		
						->get('auth/users/:userId/photo', 'download');

		$router->addRoutesFor(GroupController::class)
						->get('auth/groups', 'store')
						->get('auth/groups/0', 'new')
						->get('auth/groups/:groupId', 'read')
						->put('auth/groups/:groupId', 'update')
						->post('auth/groups', 'create')
						->delete('auth/groups', 'delete');
		
		$router->addRoutesFor(EntryController::class)
						->get('log', 'store')
						->get('log/filters', 'filters');
		
//		$router->addRoutesFor(DiskControler::class)->get('files', 'disk');
		
		$router->addRoutesFor(NotificationController::class)
						->get('notifications', 'store')
						->post('notifications/dismiss/:userId', 'dismissAll')
						->post('notifications/dismiss/:userId/:notificationId', 'dismiss')
						->get('notifications/watches/:recordClassName/:recordId/:userId', 'isWatched')
						->post('notifications/watches/:recordClassName/:recordId/:userId', 'watch')
						->delete('notifications/watches/:recordClassName/:recordId/:userId', 'unwatch');
		
		
		$router->addRoutesFor(MessageController::class)
						->crud('templates/messages/:moduleClassName', 'templateMessageId')
						->post('templates/messages/:moduleClassName/:templateMessageId/duplicate', 'duplicate');
		
		$router->addRoutesFor(PdfController::class)
						->crud('templates/pdf/:moduleClassName', 'pdfTemplateId')
						->get('templates/pdf/:moduleClassName/:pdfTemplateId/preview', 'preview')
						->post('templates/pdf/:moduleClassName/:pdfTemplateId/duplicate', 'duplicate');
		
		
		$router->addRoutesFor(Comments\Controller\CommentController::class)
						->crud('comments', 'commentId');
		
		
		$router->addRoutesFor(Selections\Controller\SelectionsController::class)
						->post('selections', 'create');
	}

	public static function defineCliRoutes(Router2 $router) {
		$router->addRoutesFor(SystemController::class)
						->set('system/check', 'check')
						->set('system/install', 'install')
						->set('system/upgrade', 'upgrade');
		
		$router->addRoutesFor(AccountController::class)
						->set('accounts/:accountId/sync', 'sync')
						->set('accounts/sync', 'syncAll');
		
		$router->addRoutesFor(JobController::class)->set('cron/run', 'run');
	}
	
}
