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
use GuzzleHttp\RequestOptions;
use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OCA\Mattermost\AppInfo\Application;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;
use OCP\Http\Client\IClientService;
use OCP\Share\IManager as ShareManager;
use Throwable;

class MattermostAPIService {
	/**
	 * @var string
	 */
	private $appName;
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
	private IRootFolder $root;
	private ShareManager $shareManager;
	private IURLGenerator $urlGenerator;

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
		$this->appName = $appName;
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
		$params = [
			'membership' => 'true',
		];
		$projects = $this->request($userId, $url, 'projects', $params);
		if (isset($projects['error'])) {
			return $projects;
		}
		$projectsInfo = [];
		foreach ($projects as $project) {
			$pid = $project['id'];
			$projectsInfo[$pid] = [
				'path_with_namespace' => $project['path_with_namespace'],
				'avatar_url' => $project['avatar_url'],
				'visibility' => $project['visibility'],
			];
		}
		return $projectsInfo;
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
			// get channel and team information for each post
			foreach ($posts as $postId => $post) {
				$channelId = $post['channel_id'];
				$posts[$postId]['channel_name'] = $channelsPerId[$channelId]['name'] ?? '';
				$posts[$postId]['channel_display_name'] = $channelsPerId[$channelId]['display_name'] ?? '';
				$posts[$postId]['team_id'] = $channelsPerId[$channelId]['team_id'] ?? '';
				$posts[$postId]['team_name'] = $channelsPerId[$channelId]['team_name'] ?? '';
				$posts[$postId]['team_display_name'] = $channelsPerId[$channelId]['team_display_name'] ?? '';
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
		$result = $this->request($userId, $mattermostUrl, 'channels');
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
		$result = $this->request($userId, $mattermostUrl, 'channels');
		if (isset($result['error'])) {
			return $result;
		}
		return $result;
	}

	/**
	 * @param string $userId
	 * @param string $mattermostUrl
	 * @param string $message
	 * @param string $channelId
	 * @return array|string[]
	 * @throws Exception
	 */
	public function sendMessage(string $userId, string $mattermostUrl, string $message, string $channelId): array {
		$params = [
			'channel_id' => $channelId,
			'message' => $message,
		];
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
	 * @return array|string[]
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function sendLinks(string $userId, string $mattermostUrl, array $fileIds,
							  string $channelId, string $channelName, string $comment,
							  string $permission, ?string $expirationDate = null): array {
		$links = [];
		$userFolder = $this->root->getUserFolder($userId);

		// create public links
		foreach ($fileIds as $fileId) {
			$nodes = $userFolder->getById($fileId);
//			if (count($nodes) > 0 && $nodes[0] instanceof File) {
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
				$share = $this->shareManager->createShare($share);
				if ($expirationDate === null) {
					$share->setExpirationDate(null);
					$this->shareManager->updateShare($share);
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
				$params = [
					'channel_id' => $channelId,
					'message' => '',
					'file_ids' => [$remoteFileId],
				];
				return $this->request($userId, $mattermostUrl, 'posts', $params, 'POST');
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
			$this->logger->warning('Mattermost API send file error : '.$e->getMessage(), ['app' => $this->appName]);
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
			$this->logger->debug('Mattermost API error : '.$e->getMessage(), ['app' => $this->appName]);
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
			$this->logger->error('No Mattermost refresh token found', ['app' => $this->appName]);
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
			$this->logger->info('Mattermost access token successfully refreshed', ['app' => $this->appName]);
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
				['app' => $this->appName]
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
			$this->logger->warning('Mattermost OAuth error : '.$e->getMessage(), array('app' => $this->appName));
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
			$this->logger->warning('Mattermost login error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $url
	 * @param array $content
	 * @return void
	 */
	public function sendWebhook(string $url, array $content): void {
		try {
			$stringContent = json_encode($content);
			$options = [
				'headers' => [
					'User-Agent'  => Application::INTEGRATION_USER_AGENT,
					'Content-Type' => 'application/json',
				],
				'body' => $stringContent,
			];
			$secret = $this->config->getAppValue(Application::APP_ID, Application::WEBHOOK_SECRET_CONFIG_KEY);
			if ($secret !== '') {
				$hash = hash('sha256', $stringContent . $secret);
				$options['headers']['X-Webhook-Signature'] = $hash;
			}
			$this->client->post($url, $options);
		} catch (Exception | Throwable $e) {
			$this->logger->error('Mattermost Webhook error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
		}
	}
}
