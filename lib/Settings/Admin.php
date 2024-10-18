<?php

namespace OCA\Mattermost\Settings;

use OCA\Mattermost\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Security\ICrypto;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	public function __construct(
		private IConfig $config,
		private IInitialState $initialStateService,
		private ICrypto $crypto,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientID = $clientID === '' ? '' : $this->crypto->decrypt($clientID);
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$clientSecret = $clientSecret === '' ? '' : $this->crypto->decrypt($clientSecret);

		$oauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');
		$usePopup = $this->config->getAppValue(Application::APP_ID, 'use_popup', '0');

		$adminConfig = [
			'client_id' => $clientID,
			// Do not expose the saved client secret to the user
			'client_secret' => $clientSecret !== '' ? 'dummySecret' : '',
			'oauth_instance_url' => $oauthUrl,
			'use_popup' => ($usePopup === '1'),
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
