<?php

/**
 * Nextcloud - Mattermost
 *
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Mattermost\AppInfo;

use Closure;
use OCA\DAV\Events\CalendarObjectCreatedEvent;
use OCA\DAV\Events\CalendarObjectUpdatedEvent;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Mattermost\Dashboard\MattermostWidget;
use OCA\Mattermost\Listener\CalendarObjectCreatedListener;
use OCA\Mattermost\Listener\CalendarObjectUpdatedListener;
use OCA\Mattermost\Reference\MessageReferenceProvider;
use OCA\Mattermost\Search\MattermostSearchMessagesProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Services\IAppConfig;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_mattermost';

	public const INTEGRATION_USER_AGENT = 'Nextcloud Mattermost integration';

	/**
	 * @var mixed
	 */
	private $config;
	private $appConfig;

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
		$this->config = $container->get(IConfig::class);
		$this->appConfig = $container->get(IAppConfig::class);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(MattermostWidget::class);
		$context->registerSearchProvider(MattermostSearchMessagesProvider::class);

		$context->registerReferenceProvider(MessageReferenceProvider::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerNavigation']));
		$context->injectFn(Closure::fromCallable([$this, 'loadFilesPlugin']));
		Util::addStyle(self::APP_ID, 'mattermost-search');
	}

	public function loadFilesPlugin(IUserSession $userSession, IEventDispatcher $eventDispatcher): void {
		$user = $userSession->getUser();
		if ($user !== null) {
			$userId = $user->getUID();
			if ($this->config->getUserValue($userId, self::APP_ID, 'file_action_enabled', '1') === '1') {
				$eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, function () {
					Util::addInitScript(self::APP_ID, self::APP_ID . '-filesplugin');
				});
			}
		}
	}

	public function registerNavigation(IUserSession $userSession): void {
		$user = $userSession->getUser();
		if ($user !== null) {
			$userId = $user->getUID();
			$container = $this->getContainer();
			$navlinkDefault = $this->appConfig->getAppValueString('navlink_default', lazy: true);
			if ($this->config->getUserValue($userId, self::APP_ID, 'navigation_enabled', $navlinkDefault) === '1') {
				$adminOauthUrl = $this->appConfig->getAppValueString('oauth_instance_url', lazy: true);
				$mattermostUrl = $this->config->getUserValue($userId, self::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
				if ($mattermostUrl === '') {
					return;
				}
				$container->get(INavigationManager::class)->add(function () use ($container, $mattermostUrl) {
					$urlGenerator = $container->get(IURLGenerator::class);
					$l10n = $container->get(IL10N::class);
					return [
						'id' => self::APP_ID,
						'order' => 10,
						'href' => $mattermostUrl,
						'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
						'name' => $l10n->t('Mattermost'),
						'target' => '_blank',
					];
				});
			}
		}
	}
}
