<?php
namespace OCA\Mattermost\Settings;

use OCA\Mattermost\Service\MattermostAPIService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

use OCA\Mattermost\AppInfo\Application;

class Personal implements ISettings {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IInitialState
	 */
	private $initialStateService;
	/**
	 * @var string|null
	 */
	private $userId;
	private MattermostAPIService $mattermostAPIService;

	public function __construct(IConfig $config,
								IInitialState $initialStateService,
								MattermostAPIService $mattermostAPIService,
								?string $userId) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->userId = $userId;
		$this->mattermostAPIService = $mattermostAPIService;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');

		if ($token) {
//			$this->mattermostAPIService->checkToken();
		}

		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$searchMessagesEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_messages_enabled', '0');
		$navigationEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'navigation_enabled', '0');
		$mmUserId = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_id');
		$mmUserName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$mmUserDisplayName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_displayname');
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url');

		// for OAuth
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		// don't expose the client secret to users
		$clientSecret = ($this->config->getAppValue(Application::APP_ID, 'client_secret') !== '');
		$oauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');

		$userConfig = [
			'token' => $token,
			'url' => $url,
			'client_id' => $clientID,
			'client_secret' => $clientSecret,
			'oauth_instance_url' => $oauthUrl,
			'user_id' => $mmUserId,
			'user_name' => $mmUserName,
			'user_displayname' => $mmUserDisplayName,
			'search_messages_enabled' => ($searchMessagesEnabled === '1'),
			'navigation_enabled' => ($navigationEnabled === '1'),
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
