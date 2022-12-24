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

use Datetime;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OC\User\NoUserException;
use OCA\Mattermost\AppInfo\Application;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;
use OCP\Http\Client\IClientService;
use OCP\Share\IManager as ShareManager;

class MattermostAPIService {
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var IL10N
	 */
	private $l10n;
	/**
	 * @var \OCP\Http\Client\IClient
	 */
	private $client;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IRootFolder
	 */
	private $root;
	/**
	 * @var ShareManager
	 */
	private $shareManager;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	/**
	 * Service to make requests to Mattermost API
	 */
	public function __construct (string $appName,
								LoggerInterface $logger,
								IL10N $l10n,
								IConfig $config,
								IRootFolder $root,
								ShareManager $shareManager,
								IURLGenerator $urlGenerator,
								IClientService $clientService) {
		$this->logger = $logger;
		$this->l10n = $l10n;
		$this->client = $clientService->newClient();
		$this->config = $config;
		$this->root = $root;
		$this->shareManager = $shareManager;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @param string $userId
	 * @param string $url
	 * @return array
	 * @throws Exception
	 */
	private function getMyTeamsInfo(string $userId, string $url): array {
		$mattermostUserId = $this->config->getUserValue($userId, Application::APP_ID, 'user_id');
		$teams = $this->request($userId, $url, 'users/' . $mattermostUserId . '/teams');
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
	 * @param string $mattermostUrl
	 * @param string $term
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 * @throws Exception
	 */
	public function searchMessages(string $userId, string $mattermostUrl, string $term, int $offset = 0, int $limit = 5): array {
		$params = [
			'include_deleted_channels' => true,
			'is_or_search' => true,
			'page' => 0,
			'per_page' => 60,
			'terms' => $term,
			'time_zone_offset' => 7200,
		];
		$result = $this->request($userId, $mattermostUrl, 'posts/search', $params, 'POST');
		$posts = $result['posts'] ?? [];

		// sort post by creation date, DESC
		usort($posts, function($a, $b) {
			$ta = (int) $a['create_at'];
			$tb = (int) $b['create_at'];
			return ($ta > $tb) ? -1 : 1;
		});

		$posts = array_slice($posts, $offset, $limit);

		return $this->addPostInfos($posts, $userId, $mattermostUrl);
	}

	/**
	 * @param string $userId
	 * @param string $mattermostUserId
	 * @param string $mattermostUrl
	 * @return array
	 * @throws Exception
	 */
	public function getUserAvatar(string $userId, string $mattermostUserId, string $mattermostUrl): array {
		$image = $this->request($userId, $mattermostUrl, 'users/' . $mattermostUserId . '/image', [], 'GET', false);
		if (!is_array($image)) {
			return ['avatarContent' => $image];
		}
		$image = $this->request($userId, $mattermostUrl, 'users/' . $mattermostUserId . '/image/default', [], 'GET', false);
		if (!is_array($image)) {
			return ['avatarContent' => $image];
		}

		$userInfo = $this->request($userId, $mattermostUrl, 'users/' . $mattermostUserId);
		return ['userInfo' => $userInfo];
	}

	/**
	 * @param string $userId
	 * @param string $teamId
	 * @param string $mattermostUrl
	 * @return array
	 * @throws Exception
	 */
	public function getTeamAvatar(string $userId, string $teamId, string $mattermostUrl): array {
		$image = $this->request($userId, $mattermostUrl, 'teams/' . $teamId . '/image', [], 'GET', false);
		if (!is_array($image)) {
			return ['avatarContent' => $image];
		}

		$userInfo = $this->request($userId, $mattermostUrl, 'teams/' . $teamId);
		return ['teamInfo' => $userInfo];
	}

	/**
	 * @param string $userId
	 * @param string $mattermostUserName
	 * @param string $mattermostUrl
	 * @param int|null $since
	 * @return array|string[]
	 * @throws Exception
	 */
	public function getMentionsMe(string $userId, string $mattermostUserName, string $mattermostUrl, ?int $since = null): array {
		$params = [
			'include_deleted_channels' => true,
			'is_or_search' => true,
			'page' => 0,
			'per_page' => 20,
			'terms' => '@' . $mattermostUserName . ' ',
			'time_zone_offset' => 7200,
		];
		$result = $this->request($userId, $mattermostUrl, 'posts/search', $params, 'POST');
		if (isset($result['error'])) {
			return $result;
		}
		$posts = $result['posts'] ?? [];

		// since filter
		$posts = array_filter($posts, function(array $post) use ($since) {
			$postTs = (int) $post['create_at'];
			return $postTs > $since;
		});

		$posts = $this->addPostInfos($posts, $userId, $mattermostUrl);

		// sort post by creation date, DESC
		usort($posts, function($a, $b) {
			$ta = (int) $a['create_at'];
			$tb = (int) $b['create_at'];
			return ($ta > $tb) ? -1 : 1;
		});
		return $posts;
	}

	/**
	 * @param array $posts
	 * @param string $userId
	 * @param string $mattermostUrl
	 * @return array
	 * @throws Exception
	 */
	public function addPostInfos(array $posts, string $userId, string $mattermostUrl): array {
		if (count($posts) > 0) {
			$channelsPerId = $this->getMyChannelsPerId($userId, $mattermostUrl);
			$teamsPerId = $this->getMyTeamsInfo($userId, $mattermostUrl);
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
				$userInfo = $this->request($userId, $mattermostUrl, 'users/' . $mmUserId);
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
	 * @param string $mattermostUrl
	 * @return array|string[]
	 * @throws Exception
	 */
	public function getMyChannelsPerId(string $userId, string $mattermostUrl): array {
		$result = $this->getMyChannels($userId, $mattermostUrl);
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
	 * @param string $mattermostUrl
	 * @return array|string[]
	 * @throws Exception
	 */
	public function getMyChannels(string $userId, string $mattermostUrl): array {
		$mattermostUserId = $this->config->getUserValue($userId, Application::APP_ID, 'user_id');
		$channelResult = $this->request($userId, $mattermostUrl, 'users/' . $mattermostUserId . '/channels');
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
			$teamResult = $this->request($userId, $mattermostUrl, 'teams/' . $teamId);
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
				$userResult = $this->request($userId, $mattermostUrl, 'users/' . $directUserId);
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
	 * @param string $mattermostUrl
	 * @param string $message
	 * @param string $channelId
	 * @param array|null $remoteFileIds
	 * @return array|string[]
	 * @throws Exception
	 */
	public function sendMessage(string $userId, string $mattermostUrl, string $message, string $channelId, ?array $remoteFileIds = null): array {
		$params = [
			'channel_id' => $channelId,
			'message' => $message,
		];
		if ($remoteFileIds !== null) {
			$params['file_ids'] = $remoteFileIds;
		}
		return $this->request($userId, $mattermostUrl, 'posts', $params, 'POST');
	}

	/**
	 * @param string $userId
	 * @param string $mattermostUrl
	 * @param array $fileIds
	 * @param string $channelId
	 * @param string $channelName
	 * @param string $comment
	 * @param string $permission
	 * @param string|null $expirationDate
	 * @param string|null $password
	 * @return array|string[]
	 * @throws NotPermittedException
	 * @throws NoUserException
	 */
	public function sendPublicLinks(string $userId, string $mattermostUrl, array $fileIds,
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
			return $this->request($userId, $mattermostUrl, 'posts', $params, 'POST');
		} else {
			return ['error' => 'Files not found'];
		}
	}

	/**
	 * @param string $userId
	 * @param string $mattermostUrl
	 * @param int $fileId
	 * @param string $channelId
	 * @return array|string[]
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\Lock\LockedException
	 * @throws \OC\User\NoUserException
	 */
	public function sendFile(string $userId, string $mattermostUrl, int $fileId, string $channelId): array {
		$userFolder = $this->root->getUserFolder($userId);
		$files = $userFolder->getById($fileId);
		if (count($files) > 0 && $files[0] instanceof File) {
			$file = $files[0];
			$endpoint = 'files?channel_id=' . urlencode($channelId) . '&filename=' . urlencode($file->getName());
			$sendResult = $this->requestSendFile($userId, $mattermostUrl, $endpoint, $file->fopen('r'));
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
	 * @param string $url
	 * @param string $endPoint
	 * @param $fileResource
	 * @return array|mixed|resource|string|string[]
	 * @throws Exception
	 */
	public function requestSendFile(string $userId, string $url, string $endPoint, $fileResource) {
		$this->checkTokenExpiration($userId, $url);
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		try {
			$url = $url . '/api/v4/' . $endPoint;
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
			$this->logger->warning('Mattermost API send file error : '.$e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $userId
	 * @param string $url
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @param bool $jsonResponse
	 * @return array|mixed|resource|string|string[]
	 * @throws Exception
	 */
	public function request(string $userId, string $url, string $endPoint, array $params = [], string $method = 'GET',
							bool $jsonResponse = true) {
		$this->checkTokenExpiration($userId, $url);
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		try {
			$url = $url . '/api/v4/' . $endPoint;
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
			} else {
				if ($jsonResponse) {
					return json_decode($body, true);
				} else {
					return $body;
				}
			}
		} catch (ServerException | ClientException $e) {
			$body = $e->getResponse()->getBody();
			$this->logger->warning('Mattermost API error : ' . $body, ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $userId
	 * @param string $url
	 * @return void
	 * @throws \OCP\PreConditionNotMetException
	 */
	private function checkTokenExpiration(string $userId, string $url): void {
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		$expireAt = $this->config->getUserValue($userId, Application::APP_ID, 'token_expires_at');
		if ($refreshToken !== '' && $expireAt !== '') {
			$nowTs = (new Datetime())->getTimestamp();
			$expireAt = (int) $expireAt;
			// if token expires in less than a minute or is already expired
			if ($nowTs > $expireAt - 60) {
				$this->refreshToken($userId, $url);
			}
		}
	}

	/**
	 * @param string $userId
	 * @param string $url
	 * @return bool
	 * @throws \OCP\PreConditionNotMetException
	 */
	private function refreshToken(string $userId, string $url): bool {
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$redirect_uri = $this->config->getUserValue($userId, Application::APP_ID, 'redirect_uri');
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		if (!$refreshToken) {
			$this->logger->error('No Mattermost refresh token found', ['app' => Application::APP_ID]);
			return false;
		}
		$result = $this->requestOAuthAccessToken($url, [
			'client_id' => $clientID,
			'client_secret' => $clientSecret,
			'grant_type' => 'refresh_token',
			'redirect_uri' => $redirect_uri,
			'refresh_token' => $refreshToken,
		], 'POST');
		if (isset($result['access_token'])) {
			$this->logger->info('Mattermost access token successfully refreshed', ['app' => Application::APP_ID]);
			$accessToken = $result['access_token'];
			$refreshToken = $result['refresh_token'];
			$this->config->setUserValue($userId, Application::APP_ID, 'token', $accessToken);
			$this->config->setUserValue($userId, Application::APP_ID, 'refresh_token', $refreshToken);
			if (isset($result['expires_in'])) {
				$nowTs = (new Datetime())->getTimestamp();
				$expiresAt = $nowTs + (int) $result['expires_in'];
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
			$url = $url . '/oauth/access_token';
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
			$this->logger->warning('Mattermost OAuth error : '.$e->getMessage(), ['app' => Application::APP_ID]);
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
					'User-Agent'  => Application::INTEGRATION_USER_AGENT,
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
			$this->logger->warning('Mattermost login error : '.$e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}
}
