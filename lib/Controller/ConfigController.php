<?php
/**
 * Nextcloud - Slack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @author Anupam Kumar <kyteinsky@gmail.com>
 * @copyright Julien Veyssier 2022
 * @copyright Anupam Kumar 2023
 */

namespace OCA\Slack\Controller;

use DateTime;
use Exception;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\PreConditionNotMetException;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

use OCA\Slack\AppInfo\Application;
use OCA\Slack\Service\SlackAPIService;

class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private IL10N $l,
		private IInitialState $initialStateService,
		private SlackAPIService $slackAPIService,
		private ICrypto $crypto,
		private LoggerInterface $logger,
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
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$oauthPossible = $clientID !== '' && $clientSecret !== '';
		$usePopup = $this->config->getAppValue(Application::APP_ID, 'use_popup', '0');

		return new DataResponse([
			'connected' => ($token !== ''),
			'oauth_possible' => $oauthPossible,
			'use_popup' => ($usePopup === '1'),
			'client_id' => $clientID,
		]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getFilesToSend(): DataResponse {
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');

		if ($token === '') {
			return new DataResponse(['message' => 'Not connected']);
		}

		$fileIdsToSendAfterOAuth = $this->config->getUserValue($this->userId, Application::APP_ID, 'file_ids_to_send_after_oauth');
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'file_ids_to_send_after_oauth');
		$currentDirAfterOAuth = $this->config->getUserValue($this->userId, Application::APP_ID, 'current_dir_after_oauth');
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'current_dir_after_oauth');

		return new DataResponse([
			'file_ids_to_send_after_oauth' => $fileIdsToSendAfterOAuth,
			'current_dir_after_oauth' => $currentDirAfterOAuth,
		]);
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
		foreach ($values as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}

		$result = [];

		if (isset($values['token'])) {
			if ($values['token'] === '') {
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_id');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_displayname');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token');
				$result['user_id'] = '';
				$result['user_displayname'] = '';
			}

			// if the token is set, cleanup refresh token and expiration date
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'refresh_token');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token_expires_at');
		}
		return new DataResponse($result);
	}

	/**
	 * set admin config values
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			try {
				if ($key === 'client_secret' && $value !== '') {
					$value = $this->crypto->encrypt($value);
				}
			} catch (Exception $e) {
				$this->config->setAppValue(Application::APP_ID, 'client_secret', '');
			// logger takes care not to leak the secret
				$this->logger->error('Could not encrypt client secret', ['exception' => $e]);
				return new DataResponse(['message' => $this->l->t('Could not encrypt client secret')]);
			}

			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		return new DataResponse(1);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $user_id
	 * @param string $user_displayname
	 * @return TemplateResponse
	 */
	public function popupSuccessPage(string $user_id, string $user_displayname): TemplateResponse {
		$this->initialStateService->provideInitialState('popup-data', [
			'user_id' => $user_id,
			'user_displayname' => $user_displayname,
		]);
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

		// decrypt client secret
		try {
			$clientSecret = $this->crypto->decrypt($clientSecret);
		} catch (Exception $e) {
			$this->logger->error('Could not decrypt client secret', ['exception' => $e]);
			return new RedirectResponse(
				$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
				'?result=error&message=' . $this->l->t('Invalid client secret')
			);
		}

		// anyway, reset state
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'oauth_state');

		if ($clientID && $clientSecret && $configState !== '' && $configState === $state) {
			$redirect_uri = $this->config->getUserValue($this->userId, Application::APP_ID, 'redirect_uri', '');
			$result = $this->slackAPIService->requestOAuthAccessToken(Application::SLACK_OAUTH_ACCESS_URL, [
				'client_id' => $clientID,
				'client_secret' => $clientSecret,
				'code' => $code,
				'redirect_uri' => $redirect_uri,
				'grant_type' => 'authorization_code'
			], 'POST');

			if (isset($result['authed_user'], $result['authed_user']['access_token'], $result['authed_user']['id'])) {
				$accessToken = $result['authed_user']['access_token'];
				$refreshToken = $result['authed_user']['refresh_token'] ?? '';

				if (isset($result['authed_user']['expires_in'])) {
					$nowTs = (new Datetime())->getTimestamp();
					$expiresAt = $nowTs + (int) $result['authed_user']['expires_in'];
					$this->config->setUserValue($this->userId, Application::APP_ID, 'token_expires_at', $expiresAt);
				}

				$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $accessToken);
				$this->config->setUserValue($this->userId, Application::APP_ID, 'refresh_token', $refreshToken);

				$userInfo = $this->storeUserInfo($result['authed_user']['id']);
				$usePopup = $this->config->getAppValue(Application::APP_ID, 'use_popup', '0') === '1';

				if ($usePopup) {
					return new RedirectResponse(
						$this->urlGenerator->linkToRoute('integration_slack.config.popupSuccessPage', [
							'user_id' => $userInfo['user_id'] ?? '',
							'user_displayname' => $userInfo['user_displayname'] ?? '',
						])
					);
				} else {
					$oauthOrigin = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_origin');
					$this->config->deleteUserValue($this->userId, Application::APP_ID, 'oauth_origin');

					if ($oauthOrigin === 'settings') {
						return new RedirectResponse(
							$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
							'?result=success'
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

			$result = $this->l->t('Error getting OAuth access token. ' . $result['error'] ?? '');
		} else {
			$result = $this->l->t('Error during OAuth exchanges');
		}
		return new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
			'?result=error&message=' . urlencode($result)
		);
	}

	/**
	 * @return string
	 * @throws PreConditionNotMetException
	 */
	private function storeUserInfo(string $slackUserId = ''): array {
		$info = $this->slackAPIService->request($this->userId, 'users.info', [
			'user' => $slackUserId,
		]);

		if (isset($info['user'], $info['user']['id'], $info['user']['real_name'])
		) {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', $info['user']['id']);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_displayname', $info['user']['real_name']);

			return [
				'user_id' => $info['user']['id'],
				'user_displayname' => $info['user']['real_name'],
			];
		} else {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', '');
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_displayname', '');

			return [
				'user_id' => '',
				'user_displayname' => '',
			];
		}
	}
}
