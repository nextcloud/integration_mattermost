<?php
/**
 * Nextcloud - Mattermost
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Mattermost\Controller;

use DateTime;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IL10N;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Mattermost\Service\MattermostAPIService;
use OCA\Mattermost\AppInfo\Application;
use OCP\PreConditionNotMetException;

class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private IL10N $l,
		private IInitialState $initialStateService,
		private MattermostAPIService $mattermostAPIService,
		private ?string $userId
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function isUserConnected(): DataResponse {
		$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');
		$mattermostUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');

		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$oauthPossible = $clientID !== '' && $clientSecret !== '' && $mattermostUrl === $adminOauthUrl;
		$usePopup = $this->config->getAppValue(Application::APP_ID, 'use_popup', '0');

		return new DataResponse([
			'connected' => $mattermostUrl && $token,
			'oauth_possible' => $oauthPossible,
			'use_popup' => ($usePopup === '1'),
			'url' => $mattermostUrl,
			'client_id' => $clientID,
		]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getFilesToSend(): DataResponse {
		$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');
		$mattermostUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$isConnected = $mattermostUrl && $token;

		if ($isConnected) {
			$fileIdsToSendAfterOAuth = $this->config->getUserValue($this->userId, Application::APP_ID, 'file_ids_to_send_after_oauth');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'file_ids_to_send_after_oauth');
			$currentDirAfterOAuth = $this->config->getUserValue($this->userId, Application::APP_ID, 'current_dir_after_oauth');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'current_dir_after_oauth');

			return new DataResponse([
				'file_ids_to_send_after_oauth' => $fileIdsToSendAfterOAuth,
				'current_dir_after_oauth' => $currentDirAfterOAuth,
			]);
		}
		return new DataResponse(['message' => 'Not connected']);
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @param array $values
	 * @return DataResponse
	 * @throws PreConditionNotMetException
	 */
	public function setConfig(array $values): DataResponse {
		if (isset($values['url'], $values['login'], $values['password'])) {
			return $this->loginWithCredentials($values['url'], $values['login'], $values['password']);
		}

		foreach ($values as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}
		$result = [];

		if (isset($values['token'])) {
			if ($values['token'] && $values['token'] !== '') {
				$result = $this->storeUserInfo();
			} else {
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_id');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_name');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_displayname');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token');
				$result['user_id'] = '';
				$result['user_name'] = '';
				$result['user_displayname'] = '';
			}
			// if the token is set, cleanup refresh token and expiration date
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'refresh_token');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token_expires_at');
		}
		return new DataResponse($result);
	}

	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 *
	 * @param string|null $calendar_event_updated_url
	 * @param string|null $calendar_event_created_url
	 * @param string|null $daily_summary_url
	 * @param string|null $imminent_events_url
	 * @param bool|null $enabled
	 * @param string|null $webhook_secret
	 * @return DataResponse
	 * @throws PreConditionNotMetException
	 */
	public function setWebhooksConfig(?string $calendar_event_updated_url = null, ?string $calendar_event_created_url = null,
									?string $daily_summary_url = null, ?string $imminent_events_url = null,
									?bool $enabled = null, ?string $webhook_secret = null): DataResponse {
		$result = [];
		if ($calendar_event_created_url !== null) {
			$result['calendar_event_created_url'] = $calendar_event_created_url;
			$this->config->setUserValue($this->userId, Application::APP_ID, Application::CALENDAR_EVENT_CREATED_WEBHOOK_CONFIG_KEY, $calendar_event_created_url);
		}
		if ($calendar_event_updated_url !== null) {
			$result['calendar_event_updated_url'] = $calendar_event_updated_url;
			$this->config->setUserValue($this->userId, Application::APP_ID, Application::CALENDAR_EVENT_UPDATED_WEBHOOK_CONFIG_KEY, $calendar_event_updated_url);
		}
		if ($daily_summary_url !== null) {
			$result['daily_summary_url'] = $daily_summary_url;
			$this->config->setUserValue($this->userId, Application::APP_ID, Application::DAILY_SUMMARY_WEBHOOK_CONFIG_KEY, $daily_summary_url);
		}
		if ($imminent_events_url !== null) {
			$result['imminent_events_url'] = $imminent_events_url;
			$this->config->setUserValue($this->userId, Application::APP_ID, Application::IMMINENT_EVENTS_WEBHOOK_CONFIG_KEY, $imminent_events_url);
		}
		if ($enabled !== null) {
			$result['enabled'] = $enabled;
			$this->config->setUserValue($this->userId, Application::APP_ID, Application::WEBHOOKS_ENABLED_CONFIG_KEY, $enabled ? '1' : '0');
		}
		if ($webhook_secret !== null) {
			$result['webhook_secret'] = $webhook_secret;
			$this->config->setUserValue($this->userId, Application::APP_ID, Application::WEBHOOK_SECRET_CONFIG_KEY, $webhook_secret);
		}
		if (empty(array_keys($result))) {
			$result = [
				'error' => 'You must set at least one valid setting.',
				'valid_keys' => [
					'enabled',
					'webhook_secret',
					'calendar_event_created_url',
					'calendar_event_updated_url',
					'daily_summary_url',
				],
			];
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}

	/**
	 * @param string $url
	 * @param string $login
	 * @param string $password
	 * @return DataResponse
	 * @throws \OCP\PreConditionNotMetException
	 */
	private function loginWithCredentials(string $url, string $login, string $password): DataResponse {
		// cleanup refresh token and expiration date on classic login
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'refresh_token');
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token_expires_at');

		$result = $this->mattermostAPIService->login($url, $login, $password);
		if (isset($result['token'])) {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $result['token']);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', $result['info']['id'] ?? '');
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', $result['info']['username'] ?? '');
			$userDisplayName = ($result['info']['first_name'] ?? '') . ' ' . ($result['info']['last_name'] ?? '');
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_displayname', $userDisplayName);
			return new DataResponse([
				'user_id' => $result['info']['id'] ?? '',
				'user_name' => $result['info']['username'] ?? '',
				'user_displayname' => $userDisplayName,
			]);
		}
		return new DataResponse([
			'user_id' => '',
			'user_name' => '',
			'user_displayname' => '',
		]);
	}

	/**
	 * set admin config values
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		return new DataResponse(1);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $user_name
	 * @param string $user_displayname
	 * @return TemplateResponse
	 */
	public function popupSuccessPage(string $user_name, string $user_displayname): TemplateResponse {
		$this->initialStateService->provideInitialState('popup-data', ['user_name' => $user_name, 'user_displayname' => $user_displayname]);
		return new TemplateResponse(Application::APP_ID, 'popupSuccess', [], TemplateResponse::RENDER_AS_GUEST);
	}

	/**
	 * receive oauth code and get oauth access token
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $code
	 * @param string $state
	 * @return RedirectResponse
	 * @throws PreConditionNotMetException
	 */
	public function oauthRedirect(string $code = '', string $state = ''): RedirectResponse {
		$configState = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_state');
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');

		// anyway, reset state
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'oauth_state');

		if ($clientID && $clientSecret && $configState !== '' && $configState === $state) {
			$redirect_uri = $this->config->getUserValue($this->userId, Application::APP_ID, 'redirect_uri');
			$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');
			$mattermostUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
			$result = $this->mattermostAPIService->requestOAuthAccessToken($mattermostUrl, [
				'client_id' => $clientID,
				'client_secret' => $clientSecret,
				'code' => $code,
				'redirect_uri' => $redirect_uri,
				'grant_type' => 'authorization_code'
			], 'POST');
			if (isset($result['access_token'])) {
				$accessToken = $result['access_token'];
				$refreshToken = $result['refresh_token'] ?? '';
				if (isset($result['expires_in'])) {
					$nowTs = (new Datetime())->getTimestamp();
					$expiresAt = $nowTs + (int) $result['expires_in'];
					$this->config->setUserValue($this->userId, Application::APP_ID, 'token_expires_at', $expiresAt);
				}
				$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $accessToken);
				$this->config->setUserValue($this->userId, Application::APP_ID, 'refresh_token', $refreshToken);
				$userInfo = $this->storeUserInfo();
				$usePopup = $this->config->getAppValue(Application::APP_ID, 'use_popup', '0') === '1';
				if ($usePopup) {
					return new RedirectResponse(
						$this->urlGenerator->linkToRoute('integration_mattermost.config.popupSuccessPage', [
							'user_name' => $userInfo['user_name'] ?? '',
							'user_displayname' => $userInfo['user_displayname'] ?? '',
						])
					);
				} else {
					$oauthOrigin = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_origin');
					$this->config->deleteUserValue($this->userId, Application::APP_ID, 'oauth_origin');
					if ($oauthOrigin === 'settings') {
						return new RedirectResponse(
							$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
							'?mattermostToken=success'
						);
					} elseif ($oauthOrigin === 'dashboard') {
						return new RedirectResponse(
							$this->urlGenerator->linkToRoute('dashboard.dashboard.index')
						);
					} elseif (preg_match('/^files--.*/', $oauthOrigin)) {
						$parts = explode('--', $oauthOrigin);
						if (count($parts) > 1) {
							// $path = preg_replace('/^files--/', '', $oauthOrigin);
							$path = $parts[1];
							if (count($parts) > 2) {
								$this->config->setUserValue($this->userId, Application::APP_ID, 'file_ids_to_send_after_oauth', $parts[2]);
								$this->config->setUserValue($this->userId, Application::APP_ID, 'current_dir_after_oauth', $path);
							}
							return new RedirectResponse(
								$this->urlGenerator->linkToRoute('files.view.index', ['dir' => $path])
							);
						}
					}
				}
			}
			$result = $this->l->t('Error getting OAuth access token. ' . $result['error']);
		} else {
			$result = $this->l->t('Error during OAuth exchanges');
		}
		return new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
			'?mattermostToken=error&message=' . urlencode($result)
		);
	}

	/**
	 * @param string $mattermostUrl
	 * @return string
	 * @throws PreConditionNotMetException
	 */
	private function storeUserInfo(): array {
		$info = $this->mattermostAPIService->request($this->userId, 'users/me');
		if (isset($info['first_name'], $info['last_name'], $info['id'], $info['username'])) {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', $info['id'] ?? '');
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', $info['username'] ?? '');
			$userDisplayName = ($info['first_name'] ?? '') . ' ' . ($info['last_name'] ?? '');
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_displayname', $userDisplayName);

			return [
				'user_id' => $info['id'] ?? '',
				'user_name' => $info['username'] ?? '',
				'user_displayname' => $userDisplayName,
			];
		} else {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', '');
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', '');
			return [
				'user_id' => '',
				'user_name' => '',
				'user_displayname' => '',
				// TODO change perso settings to get/check user name errors correctly
			];
		}
	}
}
