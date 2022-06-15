<?php
/**
 * Nextcloud - Mattermost
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Mattermost\Controller;

use DateTime;
use OCA\Activity\Data;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IL10N;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Mattermost\Service\MattermostAPIService;
use OCA\Mattermost\AppInfo\Application;

class ConfigController extends Controller {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;
	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var MattermostAPIService
	 */
	private $mattermostAPIService;
	/**
	 * @var string|null
	 */
	private $userId;

	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								IURLGenerator $urlGenerator,
								IL10N $l,
								MattermostAPIService $mattermostAPIService,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->l = $l;
		$this->mattermostAPIService = $mattermostAPIService;
		$this->userId = $userId;
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function isUserConnected(): DataResponse {
		$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');
		$mattermostUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		return new DataResponse([
			'connected' => $mattermostUrl && $token,
		]);
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @param array $values
	 * @return DataResponse
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
				$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');
				$mattermostUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
				$result = $this->storeUserInfo($mattermostUrl);
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
	 * receive oauth code and get oauth access token
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $code
	 * @param string $state
	 * @return RedirectResponse
	 */
	public function oauthRedirect(string $code = '', string $state = ''): RedirectResponse {
		$configState = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_state');
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');

		// anyway, reset state
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'oauth_state');
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'oauth_origin');

		if ($clientID and $clientSecret and $configState !== '' and $configState === $state) {
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
				$this->storeUserInfo($mattermostUrl);
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
	 */
	private function storeUserInfo(string $mattermostUrl): array {
		$info = $this->mattermostAPIService->request($this->userId, $mattermostUrl, 'users/me');
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
