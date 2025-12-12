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

namespace OCA\Mattermost\Service;

use DateTime;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OC\User\NoUserException;
use OCA\Mattermost\AppInfo\Application;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Lock\LockedException;
use OCP\PreConditionNotMetException;
use OCP\Security\ICrypto;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Service to make requests to Mattermost API
 */
class MattermostAPIService {

	private IClient $client;

	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IAppConfig $appConfig,
		private IConfig $config,
		private IRootFolder $root,
		private ShareManager $shareManager,
		private IURLGenerator $urlGenerator,
		private ICrypto $crypto,
		private NetworkService $networkService,
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $userId
	 * @return string
	 */
	public function getMattermostUrl(string $userId): string {
		$adminOauthUrl = $this->appConfig->getAppValueString('oauth_instance_url', lazy: true);
		return $this->config->getUserValue($userId, Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
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
	 * @param string $term
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	public function searchMessages(string $userId, string $term, int $offset = 0, int $limit = 5): array {
		$params = [
			'include_deleted_channels' => true,
			'is_or_search' => true,
			'page' => 0,
			'per_page' => 60,
			'terms' => $term,
			'time_zone_offset' => 7200,
		];
		$result = $this->request($userId, 'posts/search', $params, 'POST');
		$posts = $result['posts'] ?? [];

		// sort post by creation date, DESC
		usort($posts, function ($a, $b) {
			$ta = (int)$a['create_at'];
			$tb = (int)$b['create_at'];
			return ($ta > $tb) ? -1 : 1;
		});

		$posts = array_slice($posts, $offset, $limit);

		return $this->addPostInfos($posts, $userId);
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
	 * @param string $mattermostUserName
	 * @param int|null $since
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	public function getMentionsMe(string $userId, string $mattermostUserName, ?int $since = null): array {
		$params = [
			'include_deleted_channels' => true,
			'is_or_search' => true,
			'page' => 0,
			'per_page' => 20,
			'terms' => '@' . $mattermostUserName . ' ',
			'time_zone_offset' => 7200,
		];
		$result = $this->request($userId, 'posts/search', $params, 'POST');
		if (isset($result['error'])) {
			return (array)$result;
		}
		$posts = $result['posts'] ?? [];

		// since filter
		$posts = array_filter($posts, function (array $post) use ($since) {
			$postTs = (int)$post['create_at'];
			return $postTs > $since;
		});

		$posts = $this->addPostInfos($posts, $userId);

		// sort post by creation date, DESC
		usort($posts, function ($a, $b) {
			$ta = (int)$a['create_at'];
			$tb = (int)$b['create_at'];
			return ($ta > $tb) ? -1 : 1;
		});
		return $posts;
	}

	/**
	 * @param string $userId
	 * @param string $postId
	 * @return array|string[]
	 * @throws PreConditionNotMetException
	 */
	public function getPostInfo(string $userId, string $postId): array {
		return $this->request($userId, 'posts/' . $postId);
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
	 * @param array $posts
	 * @param string $userId
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	public function addPostInfos(array $posts, string $userId): array {
		if (count($posts) > 0) {
			$channelsPerId = $this->getMyChannelsPerId($userId);
			$teamsPerId = $this->getMyTeamsInfo($userId);
			$teamIds = array_keys($teamsPerId);
			$fallbackTeamId = end($teamIds);
			// get channel and team information for each post
			foreach ($posts as $postId => $post) {
				$channelId = $post['channel_id'];
				$teamId = $channelsPerId[$channelId]['team_id'] ?? '';
				$posts[$postId]['channel_type'] = $channelsPerId[$channelId]['type'] ?? '';
				$posts[$postId]['channel_name'] = $channelsPerId[$channelId]['name'] ?? '';
				$posts[$postId]['channel_display_name'] = $channelsPerId[$channelId]['display_name'] ?? '';
				$posts[$postId]['team_id'] = $teamId;
				$posts[$postId]['team_name'] = $teamsPerId[$teamId]['name'] ?? '';
				$posts[$postId]['team_display_name'] = $teamsPerId[$teamId]['display_name'] ?? '';
				if ($channelsPerId[$channelId]['type'] === 'D') {
					$posts[$postId]['direct_message_user_name'] = $channelsPerId[$channelId]['direct_message_user_name'] ?? '';
					// add any team to direct messages as it apparently does not matter but is needed...
					$posts[$postId]['team_name'] = $teamsPerId[$fallbackTeamId]['name'] ?? '';
				}
			}

			// get user/author info
			$usersById = [];
			foreach ($posts as $postId => $post) {
				$usersById[$post['user_id']] = [];
			}
			foreach ($usersById as $mmUserId => $user) {
				$userInfo = $this->request($userId, 'users/' . $mmUserId);
				if (isset($userInfo['username'], $userInfo['first_name'], $userInfo['last_name'])) {
					$usersById[$mmUserId]['user_name'] = $userInfo['username'];
					$usersById[$mmUserId]['user_display_name'] = $userInfo['first_name'] . ' ' . $userInfo['last_name'];
				}
			}
			foreach ($posts as $postId => $post) {
				$mmUserId = $post['user_id'];
				$posts[$postId]['user_name'] = $usersById[$mmUserId]['user_name'] ?? '';
				$posts[$postId]['user_display_name'] = $usersById[$mmUserId]['user_display_name'] ?? '';
			}
		}
		return $posts;
	}

	/**
	 * @param string $userId
	 * @return array|string[]
	 * @throws PreConditionNotMetException
	 */
	public function getMyChannelsPerId(string $userId): array {
		$result = $this->getMyChannels($userId);
		if (isset($result['error'])) {
			return $result;
		}
		$perId = [];
		foreach ($result as $channel) {
			$perId[$channel['id']] = $channel;
		}
		return $perId;
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
			return (array)$channelResult;
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

		return (array)$channelResult;
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
	public function sendPublicLinks(
		string $userId,
		array $fileIds,
		string $channelId,
		string $channelName,
		string $comment,
		string $permission,
		?string $expirationDate = null,
		?string $password = null,
	): array {
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
				return (array)$sendResult;
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
	 * @param resource $fileResource
	 * @return array|mixed|resource|string|string[]
	 * @throws PreConditionNotMetException
	 */
	public function requestSendFile(string $userId, string $endPoint, $fileResource) {
		$mattermostUrl = $this->getMattermostUrl($userId);
		$this->checkTokenExpiration($userId);
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		$accessToken = $accessToken === '' ? '' : $this->crypto->decrypt($accessToken);
		try {
			$url = $mattermostUrl . '/api/v4/' . $endPoint;
			$options = [
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
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
		} catch (ServerException|ClientException $e) {
			$this->logger->error('Mattermost API send file error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
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
	public function request(
		string $userId,
		string $endPoint,
		array $params = [],
		string $method = 'GET',
		bool $jsonResponse = true,
	) {
		$mattermostUrl = $this->getMattermostUrl($userId);
		$this->checkTokenExpiration($userId);
		return $this->networkService->request($userId, $mattermostUrl, $endPoint, $params, $method, $jsonResponse);
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
			$expireAt = (int)$expireAt;
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
		$adminOauthUrl = $this->appConfig->getAppValueString('oauth_instance_url', lazy: true);
		$clientID = $this->appConfig->getAppValueString('client_id', lazy: true);
		$clientID = $clientID === '' ? '' : $this->crypto->decrypt($clientID);
		$clientSecret = $this->appConfig->getAppValueString('client_secret', lazy: true);
		$clientSecret = $clientSecret === '' ? '' : $this->crypto->decrypt($clientSecret);
		$redirect_uri = $this->config->getUserValue($userId, Application::APP_ID, 'redirect_uri');
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		$refreshToken = $refreshToken === '' ? '' : $this->crypto->decrypt($refreshToken);
		if (!$refreshToken) {
			$this->logger->error('No Mattermost refresh token found', ['app' => Application::APP_ID]);
			return false;
		}
		$result = $this->requestOAuthAccessToken($adminOauthUrl, [
			'client_id' => $clientID,
			'client_secret' => $clientSecret,
			'grant_type' => 'refresh_token',
			'redirect_uri' => $redirect_uri,
			'refresh_token' => $refreshToken,
		], 'POST');
		if (isset($result['access_token'])) {
			$this->logger->info('Mattermost access token successfully refreshed', ['app' => Application::APP_ID]);
			$accessToken = $result['access_token'];
			$encryptedToken = $accessToken === '' ? '' : $this->crypto->encrypt($accessToken);
			$refreshToken = $result['refresh_token'];
			$encryptedRefreshToken = $refreshToken === '' ? '' : $this->crypto->encrypt($refreshToken);
			$this->config->setUserValue($userId, Application::APP_ID, 'token', $encryptedToken);
			$this->config->setUserValue($userId, Application::APP_ID, 'refresh_token', $encryptedRefreshToken);
			if (isset($result['expires_in'])) {
				$nowTs = (new DateTime())->getTimestamp();
				$expiresAt = $nowTs + (int)$result['expires_in'];
				$this->config->setUserValue($userId, Application::APP_ID, 'token_expires_at', strval($expiresAt));
			}
			return true;
		} else {
			// impossible to refresh the token
			$this->logger->error(
				'Token is not valid anymore. Impossible to refresh it. '
					. $result['error'] . ' '
					. ($result['error_description'] ?? '[no error description]'),
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
			$url = $url . '/oauth/access_token';
			$options = [
				'headers' => [
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
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
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
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
			$this->logger->error('Mattermost OAuth error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $baseUrl
	 * @param string $login
	 * @param string $password
	 * @return array
	 */
	public function login(string $baseUrl, string $login, string $password): array {
		try {
			$url = $baseUrl . '/api/v4/users/login';
			$options = [
				'headers' => [
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
					'Content-Type' => 'application/x-www-form-urlencoded',
				],
				'json' => [
					'login_id' => $login,
					'password' => $password,
				],
			];
			$response = $this->client->post($url, $options);
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Invalid credentials')];
			} else {
				$token = $response->getHeader('Token');
				if ($token) {
					return [
						'token' => $token,
						'info' => json_decode($body, true),
					];
				}
				return ['error' => $this->l10n->t('Invalid response')];
			}
		} catch (Exception $e) {
			$this->logger->error('Mattermost login error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}
}
