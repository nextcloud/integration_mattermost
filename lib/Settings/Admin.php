<?php

namespace OCA\Slack\Settings;

use Exception;
use OCA\Slack\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Security\ICrypto;
use OCP\Settings\ISettings;
use Psr\Log\LoggerInterface;

class Admin implements ISettings {

	public function __construct(
		private IConfig $config,
		private IInitialState $initialStateService,
		private ICrypto $crypto,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$usePopup = $this->config->getAppValue(Application::APP_ID, 'use_popup', '0');

		try {
			if ($clientSecret !== '') {
				$clientSecret = $this->crypto->decrypt($clientSecret);
			}
		} catch (Exception $e) {
			// logger takes care not to leak the secret
			$this->logger->error('Failed to decrypt client secret', ['exception' => $e]);
			$clientSecret = '';
		}

		$adminConfig = [
			'client_id' => $clientID,
			'client_secret' => $clientSecret === '' ? '' : 'dummyClientSecret',
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
