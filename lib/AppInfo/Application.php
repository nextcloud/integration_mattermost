<?php
/**
 * Nextcloud - Slack
 *
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @author Anupam Kumar <kyteinsky@gmail.com>
 * @copyright Julien Veyssier 2022
 * @copyright Anupam Kumar 2023
 */

namespace OCA\Slack\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Collaboration\Resources\LoadAdditionalScriptsEvent;

use OCA\Slack\Listener\FilesMenuListener;

class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_slack';
	public const INTEGRATION_USER_AGENT = 'Nextcloud Slack Integration';
	public const SLACK_API_URL = 'https://slack.com/api/';
	public const SLACK_OAUTH_ACCESS_URL = 'https://slack.com/api/oauth.v2.access';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, FilesMenuListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
