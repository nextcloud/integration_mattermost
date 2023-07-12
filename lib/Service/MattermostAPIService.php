<?php
/**
 * Nextcloud - Mattermost
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Slack\Service;

use Datetime;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OC\User\NoUserException;
use OCA\Slack\AppInfo\Application;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\IClient;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Lock\LockedException;
use OCP\PreConditionNotMetException;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;
use OCP\Http\Client\IClientService;
use OCP\Share\IManager as ShareManager;
use Throwable;

/**
 * Service to make requests to Mattermost API
 */
class MattermostAPIService {

	private IClient $client;

	public function __construct(
		string $appName,
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IConfig $config,
		private IRootFolder $root,
		private ShareManager $shareManager,
		private IURLGenerator $urlGenerator,
		IClientService $clientService
	) {
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $userId
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	private function getMyTeamsInfo(string $userId): array {
		$mattermostUserId = $this->config->getUserValue($userId, Application::APP_ID, 'user_id');
		$teams = $this->request($userId, 'users/' . $mattermostUserId . '/teams');
		if (isset($teams['error'])) {
			return [];
		}
		$teamsById = [];
		foreach ($teams as $team) {
			$teamsById[$team['id']] = $team;
		}
		return $teamsById;
	}

	/**
	 * @param string $userId
	 * @param string $mattermostUserId
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	public function getUserAvatar(string $userId, string $mattermostUserId): array {
		$image = $this->request($userId, 'users/' . $mattermostUserId . '/image', [], 'GET', false);
		if (!is_array($image)) {
			return ['avatarContent' => $image];
		}
		$image = $this->request($userId, 'users/' . $mattermostUserId . '/image/default', [], 'GET', false);
		if (!is_array($image)) {
			return ['avatarContent' => $image];
		}

		$userInfo = $this->request($userId, 'users/' . $mattermostUserId);
		return ['userInfo' => $userInfo];
	}

	/**
	 * @param string $userId
	 * @param string $teamId
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	public function getTeamAvatar(string $userId, string $teamId): array {
		$image = $this->request($userId, 'teams/' . $teamId . '/image', [], 'GET', false);
		if (!is_array($image)) {
			return ['avatarContent' => $image];
		}

		$userInfo = $this->request($userId, 'teams/' . $teamId);
		return ['teamInfo' => $userInfo];
	}

	/**
	 * @param string $userId
	 * @param string $channelId
	 * @return array|string[]
	 * @throws PreConditionNotMetException
	 */
	public function getChannelInfo(string $userId, string $channelId): array {
		return $this->request($userId, 'channels/' . $channelId);
	}

	/**
	 * @param string $userId
	 * @param string $mattermostUserId
	 * @return array|string[]
	 * @throws PreConditionNotMetException
	 */
	public function getUserInfo(string $userId, string $mattermostUserId): array {
		return $this->request($userId, 'users/' . $mattermostUserId);
	}

	/**
	 * @param string $userId
	 * @return array|string[]
	 * @throws PreConditionNotMetException
	 */
	public function getMyChannels(string $userId): array {
		$mattermostUserId = $this->config->getUserValue($userId, Application::APP_ID, 'user_id');
		$channelResult = $this->request($userId, 'users/' . $mattermostUserId . '/channels');
		if (isset($channelResult['error'])) {
			return $channelResult;
		}

		// get team names
		$teamIds = [];
		foreach ($channelResult as $channel) {
			if ($channel['type'] === 'O') {
				$teamId = $channel['team_id'];
				if (!in_array($teamId, $teamIds)) {
					$teamIds[] = $teamId;
				}
			}
		}
		$teamDisplayNamesById = [];
		foreach ($teamIds as $teamId) {
			$teamResult = $this->request($userId, 'teams/' . $teamId);
			if (!isset($teamResult['error'])) {
				$teamDisplayNamesById[$teamId] = $teamResult['display_name'];
			}
		}
		// put it back in the channels
		foreach ($channelResult as $i => $channel) {
			$channelResult[$i]['team_display_name'] = $teamDisplayNamesById[$channel['team_id']] ?? null;
		}

		// get direct message author names
		foreach ($channelResult as $i => $channel) {
			if ($channel['type'] === 'D') {
				$names = explode('__', $channel['name']);
				if (count($names) !== 2) {
					continue;
				}
				$directUserId = $names[0];
				if ($directUserId === $mattermostUserId) {
					$directUserId = $names[1];
				}
				$userResult = $this->request($userId, 'users/' . $directUserId);
				if (!isset($userResult['error'])) {
					$userDisplayName = preg_replace('/^\s+$/', '', $userResult['first_name'] . ' ' . $userResult['last_name']);
					$userDisplayName = $userDisplayName ?: $userResult['username'];
					$userName = $userResult['username'];
					$channelResult[$i]['display_name'] = $userDisplayName;
					$channelResult[$i]['direct_message_display_name'] = $userDisplayName;
					$channelResult[$i]['direct_message_user_name'] = $userName;
					$channelResult[$i]['direct_message_user_id'] = $directUserId;
				}
			}
		}

		return $channelResult;
	}

	/**
	 * @param string $userId
	 * @param string $message
	 * @param string $channelId
	 * @param array|null $remoteFileIds
	 * @return array|string[]
	 * @throws PreConditionNotMetException
	 */
	public function sendMessage(string $userId, string $message, string $channelId, ?array $remoteFileIds = null): array {
		$params = [
			'channel_id' => $channelId,
			'message' => $message,
		];
		if ($remoteFileIds !== null) {
			$params['file_ids'] = $remoteFileIds;
		}
		return $this->request($userId, 'posts', $params, 'POST');
	}

	/**
	 * @param string $userId
	 * @param array $fileIds
	 * @param string $channelId
	 * @param string $channelName
	 * @param string $comment
	 * @param string $permission
	 * @param string|null $expirationDate
	 * @param string|null $password
	 * @return array|string[]
	 * @throws NoUserException
	 * @throws NotPermittedException
	 * @throws PreConditionNotMetException
	 */
	public function sendPublicLinks(string $userId, array $fileIds,
							  string $channelId, string $channelName, string $comment,
							  string $permission, ?string $expirationDate = null, ?string $password = null): array {
		$links = [];
		$userFolder = $this->root->getUserFolder($userId);

		// create public links
		foreach ($fileIds as $fileId) {
			$nodes = $userFolder->getById($fileId);
			// if (count($nodes) > 0 && $nodes[0] instanceof File) {
			if (count($nodes) > 0 && ($nodes[0] instanceof File || $nodes[0] instanceof Folder)) {
				$node = $nodes[0];

				$share = $this->shareManager->newShare();
				$share->setNode($node);
				if ($permission === 'edit') {
					$share->setPermissions(Constants::PERMISSION_READ | Constants::PERMISSION_UPDATE);
				} else {
					$share->setPermissions(Constants::PERMISSION_READ);
				}
				$share->setShareType(IShare::TYPE_LINK);
				$share->setSharedBy($userId);
				$share->setLabel('Mattermost (' . $channelName . ')');
				if ($expirationDate !== null) {
					$share->setExpirationDate(new Datetime($expirationDate));
				}
				if ($password !== null) {
					try {
						$share->setPassword($password);
					} catch (Exception $e) {
						return ['error' => $e->getMessage()];
					}
				}
				try {
					$share = $this->shareManager->createShare($share);
					if ($expirationDate === null) {
						$share->setExpirationDate(null);
						$this->shareManager->updateShare($share);
					}
				} catch (Exception $e) {
					return ['error' => $e->getMessage()];
				}
				$token = $share->getToken();
				$linkUrl = $this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->linkToRoute('files_sharing.Share.showShare', [
						'token' => $token,
					])
				);
				$links[] = [
					'name' => $node->getName(),
					'url' => $linkUrl,
				];
			}
		}

		if (count($links) > 0) {
			$message = $comment . "\n";
			foreach ($links as $link) {
				$message .= '```' . $link['name'] . '```: ' . $link['url'] . "\n";
			}
			$params = [
				'channel_id' => $channelId,
				'message' => $message,
			];
			return $this->request($userId, 'posts', $params, 'POST');
		} else {
			return ['error' => 'Files not found'];
		}
	}

	/**
	 * @param string $userId
	 * @param int $fileId
	 * @param string $channelId
	 * @return array|string[]
	 * @throws NoUserException
	 * @throws NotPermittedException
	 * @throws LockedException
	 */
	public function sendFile(string $userId, int $fileId, string $channelId): array {
		$userFolder = $this->root->getUserFolder($userId);
		$files = $userFolder->getById($fileId);
		if (count($files) > 0 && $files[0] instanceof File) {
			$file = $files[0];
			$endpoint = 'files?channel_id=' . urlencode($channelId) . '&filename=' . urlencode($file->getName());
			$sendResult = $this->requestSendFile($userId, $endpoint, $file->fopen('r'));
			if (isset($sendResult['error'])) {
				return $sendResult;
			}

			if (isset($sendResult['file_infos']) && is_array($sendResult['file_infos']) && count($sendResult['file_infos']) > 0) {
				$remoteFileId = $sendResult['file_infos'][0]['id'] ?? 0;
				return [
					'remote_file_id' => $remoteFileId,
				];
			} else {
				return ['error' => 'File upload error'];
			}
		} else {
			return ['error' => 'File not found'];
		}
	}

	/**
	 * @param string $userId
	 * @param string $endPoint
	 * @param $fileResource
	 * @return array|mixed|resource|string|string[]
	 * @throws PreConditionNotMetException
	 */
	public function requestSendFile(string $userId, string $endPoint, $fileResource) {
		/* TODO: check token expiration */
		$this->checkTokenExpiration($userId);
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');

		try {
			$url = Application::SLACK_API_URL . $endPoint;
			$options = [
				'headers' => [
					'Authorization'  => 'Bearer ' . $accessToken,
					'User-Agent'  => Application::INTEGRATION_USER_AGENT,
				],
				'body' => $fileResource,
			];

			$response = $this->client->post($url, $options);
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return json_decode($body, true);
			}
		} catch (ServerException | ClientException $e) {
			$this->logger->warning('Slack API send file error : '.$e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $userId
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @param bool $jsonResponse
	 * @return array|mixed|resource|string|string[]
	 * @throws PreConditionNotMetException
	 */
	public function request(string $userId, string $endPoint, array $params = [], string $method = 'GET',
							bool $jsonResponse = true) {
		$this->checkTokenExpiration($userId);
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');

		try {
			$url = Application::SLACK_API_URL . $endPoint;
			$options = [
				'headers' => [
					'Authorization'  => 'Bearer ' . $accessToken,
					'Content-Type' => 'application/x-www-form-urlencoded',
					'User-Agent'  => Application::INTEGRATION_USER_AGENT,
				],
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					// manage array parameters
					$paramsContent = '';
					foreach ($params as $key => $value) {
						if (is_array($value)) {
							foreach ($value as $oneArrayValue) {
								$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
							}
							unset($params[$key]);
						}
					}
					$paramsContent .= http_build_query($params);

					$url .= '?' . $paramsContent;
				} else {
					$options['json'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			}
			if ($jsonResponse) {
				return json_decode($body, true);
			}
			return $body;
		} catch (ServerException | ClientException $e) {
			$body = $e->getResponse()->getBody();
			$this->logger->warning('Slack API error : ' . $body, ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		} catch (Exception | Throwable $e) {
			$this->logger->warning('Slack API error', ['exception' => $e, 'app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $userId
	 * @return void
	 * @throws \OCP\PreConditionNotMetException
	 */
	private function checkTokenExpiration(string $userId): void {
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		$expireAt = $this->config->getUserValue($userId, Application::APP_ID, 'token_expires_at');
		if ($refreshToken !== '' && $expireAt !== '') {
			$nowTs = (new Datetime())->getTimestamp();
			$expireAt = (int) $expireAt;
			// if token expires in less than a minute or is already expired
			if ($nowTs > $expireAt - 60) {
				$this->refreshToken($userId);
			}
		}
	}

	/**
	 * @param string $userId
	 * @return bool
	 * @throws \OCP\PreConditionNotMetException
	 */
	private function refreshToken(string $userId): bool {
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$redirect_uri = $this->config->getUserValue($userId, Application::APP_ID, 'redirect_uri');
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');

		if (!$refreshToken) {
			$this->logger->error('No Slack refresh token found', ['app' => Application::APP_ID]);
			return false;
		}

		$result = $this->requestOAuthAccessToken(Application::SLACK_OAUTH_ACCESS_URL, [
			'client_id' => $clientID,
			'client_secret' => $clientSecret,
			'grant_type' => 'refresh_token',
			'refresh_token' => $refreshToken,
		], 'POST');

		if (isset($result['authed_user'], $result['authed_user']['access_token'])) {
			$this->logger->info('Slack access token successfully refreshed', ['app' => Application::APP_ID]);

			$accessToken = $result['authed_user']['access_token'];
			$refreshToken = $result['authed_user']['refresh_token'];
			$this->config->setUserValue($userId, Application::APP_ID, 'token', $accessToken);
			$this->config->setUserValue($userId, Application::APP_ID, 'refresh_token', $refreshToken);

			if (isset($result['authed_user']['expires_in'])) {
				$nowTs = (new Datetime())->getTimestamp();
				$expiresAt = $nowTs + (int) $result['authed_user']['expires_in'];
				$this->config->setUserValue($userId, Application::APP_ID, 'token_expires_at', $expiresAt);
			}

			return true;
		} else {
			// impossible to refresh the token
			$this->logger->error(
				'Token is not valid anymore. Impossible to refresh it. '
					. $result['error'] . ' '
					. $result['error_description'] ?? '[no error description]',
				['app' => Application::APP_ID]
			);

			return false;
		}
	}

	/**
	 * @param string $url
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function requestOAuthAccessToken(string $url, array $params = [], string $method = 'GET'): array {
		try {
			$options = [
				'headers' => [
					'User-Agent'  => Application::INTEGRATION_USER_AGENT,
				]
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					$paramsContent = http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('OAuth access token refused')];
			} else {
				return json_decode($body, true);
			}
		} catch (Exception $e) {
			$this->logger->warning('Slack OAuth error : '.$e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}
}
