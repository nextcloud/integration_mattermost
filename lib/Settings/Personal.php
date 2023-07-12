<?php
namespace OCA\Slack\Settings;

use OCA\Slack\Service\MattermostAPIService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

use OCA\Slack\AppInfo\Application;

class Personal implements ISettings {

	public function __construct(
		private IConfig $config,
		private IInitialState $initialStateService,
		private ?string $userId
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$userId = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_id');
		$userAvatar = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_avatar');
		$userDisplayName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_displayname');
		$fileActionEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'file_action_enabled', '1') === '1';

		// for OAuth
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$usePopup = $this->config->getAppValue(Application::APP_ID, 'use_popup', '0');

		$userConfig = [
			'token' => $token ? 'dummyTokenContent' : '',
			'client_id' => $clientID,
			'use_popup' => ($usePopup === '1'),
			'user_id' => $userId,
			'user_avatar' => $userAvatar,
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
