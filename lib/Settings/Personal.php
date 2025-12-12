<?php

namespace OCA\Mattermost\Settings;

use OCA\Mattermost\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Security\ICrypto;
use OCP\Settings\ISettings;

class Personal implements ISettings {

	public function __construct(
		private IAppConfig $appConfig,
		private IConfig $config,
		private IInitialState $initialStateService,
		private ICrypto $crypto,
		private ?string $userId,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$token = $token === '' ? '' : $this->crypto->decrypt($token);
		$searchMessagesEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_messages_enabled', '0') === '1';
		$navlinkDefault = $this->appConfig->getAppValueString('navlink_default', '0', lazy: true);
		$navigationEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'navigation_enabled', $navlinkDefault) === '1';
		$fileActionEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'file_action_enabled', '1') === '1';
		$mmUserId = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_id');
		$mmUserName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$mmUserDisplayName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_displayname');
		$adminOauthUrl = $this->appConfig->getAppValueString('oauth_instance_url', lazy: true);
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;

		// for OAuth
		$clientID = $this->appConfig->getAppValueString('client_id', lazy: true);
		$clientID = $clientID === '' ? '' : $this->crypto->decrypt($clientID);
		$clientSecret = $this->appConfig->getAppValueString('client_secret', lazy: true);
		if ($clientSecret !== '') {
			$clientSecret = $this->crypto->decrypt($clientSecret);
		}
		$oauthUrl = $this->appConfig->getAppValueString('oauth_instance_url', lazy: true);
		$usePopup = $this->appConfig->getAppValueString('use_popup', '0', lazy: true);

		// webhooks
		$webhooksEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'webhooks_enabled', '0') === '1';
		$webhookSecret = $this->config->getUserValue($this->userId, Application::APP_ID, Application::WEBHOOK_SECRET_CONFIG_KEY);
		$calEventAddedWebhook = $this->config->getUserValue($this->userId, Application::APP_ID, Application::CALENDAR_EVENT_CREATED_WEBHOOK_CONFIG_KEY);
		$calEventEditedWebhook = $this->config->getUserValue($this->userId, Application::APP_ID, Application::CALENDAR_EVENT_UPDATED_WEBHOOK_CONFIG_KEY);
		$dailySummaryWebhook = $this->config->getUserValue($this->userId, Application::APP_ID, Application::DAILY_SUMMARY_WEBHOOK_CONFIG_KEY);
		$imminentEventsWebhook = $this->config->getUserValue($this->userId, Application::APP_ID, Application::IMMINENT_EVENTS_WEBHOOK_CONFIG_KEY);

		$userConfig = [
			// Do not expose the saved token to the user
			'token' => $token !== '' ? 'dummyTokenContent' : '',
			'url' => $url,
			'client_id' => $clientID,
			// don't expose the client secret to users
			'client_secret' => $clientSecret !== '',
			'oauth_instance_url' => $oauthUrl,
			'use_popup' => $usePopup === '1',
			'user_id' => $mmUserId,
			'user_name' => $mmUserName,
			'user_displayname' => $mmUserDisplayName,
			'search_messages_enabled' => $searchMessagesEnabled,
			'navigation_enabled' => $navigationEnabled,
			'file_action_enabled' => $fileActionEnabled,
			Application::WEBHOOKS_ENABLED_CONFIG_KEY => $webhooksEnabled,
			Application::WEBHOOK_SECRET_CONFIG_KEY => $webhookSecret,
			Application::CALENDAR_EVENT_CREATED_WEBHOOK_CONFIG_KEY => $calEventAddedWebhook,
			Application::CALENDAR_EVENT_UPDATED_WEBHOOK_CONFIG_KEY => $calEventEditedWebhook,
			Application::DAILY_SUMMARY_WEBHOOK_CONFIG_KEY => $dailySummaryWebhook,
			Application::IMMINENT_EVENTS_WEBHOOK_CONFIG_KEY => $imminentEventsWebhook,
		];
		$this->initialStateService->provideInitialState('user-config', $userConfig);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
