<?php

namespace OCA\Slack\Settings;

use OCA\Slack\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Personal implements ISettings {

	public function __construct(
		private IConfig $config,
		private IAppConfig $appConfig,
		private IInitialState $initialStateService,
		private ?string $userId,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$userId = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_id');
		$userDisplayName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_displayname');
		$fileActionEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'file_action_enabled', '1') === '1';

		// for OAuth
		$clientID = $this->appConfig->getAppValueString('client_id', lazy: true);
		$clientSecret = $this->appConfig->getAppValueString('client_secret', lazy: true);
		$usePopup = $this->appConfig->getAppValueString('use_popup', '0', lazy: true);

		$userConfig = [
			// don't need to decrypt it, just need to know if it's set
			'token' => $token ? 'dummyTokenContent' : '',
			'client_id' => $clientID,
			// don't need to decrypt it, just need to know if it's set
			'client_secret' => $clientSecret !== '' ? 'dummyClientSecret' : '',
			'use_popup' => ($usePopup === '1'),
			'user_id' => $userId,
			'user_displayname' => $userDisplayName,
			'file_action_enabled' => $fileActionEnabled,
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
