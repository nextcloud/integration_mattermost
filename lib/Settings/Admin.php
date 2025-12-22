<?php

namespace OCA\Mattermost\Settings;

use OCA\Mattermost\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	public function __construct(
		private IAppConfig $appConfig,
		private IInitialState $initialStateService,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$clientID = $this->appConfig->getAppValueString('client_id', lazy: true);
		$clientSecret = $this->appConfig->getAppValueString('client_secret', lazy: true);

		$oauthUrl = $this->appConfig->getAppValueString('oauth_instance_url', lazy: true);
		$usePopup = $this->appConfig->getAppValueString('use_popup', '0', lazy: true);
		$navlinkDefault = $this->appConfig->getAppValueString('navlink_default', '0', lazy: true);

		$adminConfig = [
			'client_id' => $clientID,
			// Do not expose the saved client secret to the user
			'client_secret' => $clientSecret !== '' ? 'dummySecret' : '',
			'oauth_instance_url' => $oauthUrl,
			'use_popup' => ($usePopup === '1'),
			'navlink_default' => ($navlinkDefault === '1'),
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
