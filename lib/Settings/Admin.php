<?php
namespace OCA\Mattermost\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

use OCA\Mattermost\AppInfo\Application;

class Admin implements ISettings {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IInitialState
	 */
	private $initialStateService;

	public function __construct(IConfig $config,
								IInitialState $initialStateService) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$oauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');
		$usePopup = $this->config->getAppValue(Application::APP_ID, 'use_popup', '0');

		$webhookSecret = $this->config->getAppValue(Application::APP_ID, Application::WEBHOOK_SECRET_CONFIG_KEY);
		$calEventAddedWebhook = $this->config->getAppValue(Application::APP_ID, Application::CALENDAR_EVENT_CREATED_WEBHOOK_CONFIG_KEY);
		$calEventEditedWebhook = $this->config->getAppValue(Application::APP_ID, Application::CALENDAR_EVENT_UPDATED_WEBHOOK_CONFIG_KEY);

		$adminConfig = [
			'client_id' => $clientID,
			'client_secret' => $clientSecret,
			'oauth_instance_url' => $oauthUrl,
			'use_popup' => ($usePopup === '1'),
			Application::WEBHOOK_SECRET_CONFIG_KEY => $webhookSecret,
			Application::CALENDAR_EVENT_CREATED_WEBHOOK_CONFIG_KEY => $calEventAddedWebhook,
			Application::CALENDAR_EVENT_UPDATED_WEBHOOK_CONFIG_KEY => $calEventEditedWebhook,
		];
		$this->initialStateService->provideInitialState('admin-config', $adminConfig);
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
