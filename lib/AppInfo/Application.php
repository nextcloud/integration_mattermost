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

use Closure;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IUserSession;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_slack';
	public const INTEGRATION_USER_AGENT = 'Nextcloud Slack Integration';
	public const SLACK_API_URL = 'https://slack.com/api/';
	public const SLACK_OAUTH_ACCESS_URL = 'https://slack.com/api/oauth.v2.access';

	/**
	 * @var mixed
	 */
	private $config;

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
		$this->config = $container->get(IConfig::class);
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'loadFilesPlugin']));
	}

	public function loadFilesPlugin(IUserSession $userSession, IEventDispatcher $eventDispatcher): void {
		$user = $userSession->getUser();
		if ($user !== null) {
			$userId = $user->getUID();
			if ($this->config->getUserValue($userId, self::APP_ID, 'file_action_enabled', '1') === '1') {
				$eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, function () {
					Util::addscript(self::APP_ID, self::APP_ID . '-filesplugin', 'files');
					Util::addStyle(self::APP_ID, self::APP_ID . '-files');
				});
			}
		}
	}
}
