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

namespace OCA\Slack\Service;

use DateTime;
use Exception;
use OC\User\NoUserException;
use OCA\Slack\AppInfo\Application;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\ICacheFactory;
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
 * Service to make requests to Slack API
 */
class SlackAPIService {

	private IClient $client;

	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IConfig $config,
		private IAppConfig $appConfig,
		private IRootFolder $root,
		private ShareManager $shareManager,
		private IURLGenerator $urlGenerator,
		private ICrypto $crypto,
		private ICacheFactory $cacheFactory,
		private NetworkService $networkService,
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $userId
	 * @param string $slackUserId
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	public function getUserAvatar(string $userId, string $slackUserId): array {
		$userInfo = $this->request($userId, 'users.info', ['user' => $slackUserId]);

		if (isset($userInfo['error'])) {
			$this->logger->warning('Slack user info fetch error', ['error' => $userInfo]);
			return ['displayName' => 'User ' . $slackUserId];
		}

		if (isset($userInfo['user'], $userInfo['user']['profile'], $userInfo['user']['profile']['image_48'])) {
			// due to some Slack API changes, we now have to sanitize the image url
			//   for some of them
			$parsedUrlObj = parse_url($userInfo['user']['profile']['image_48']);

			if (isset($parsedUrlObj['query'])) {
				parse_str($parsedUrlObj['query'], $params);
				if (!isset($params['d'])) {
					if (isset($userInfo['user'], $userInfo['user']['real_name'])) {
						return ['displayName' => $userInfo['user']['real_name']];
					}

					return ['displayName' => 'User ' . $slackUserId];
				}

				$image = $this->request($userId, $params['d'], [], 'GET', false, false);
			} else {
				$image = $this->request($userId, $userInfo['user']['profile']['image_48'], [], 'GET', false, false);
			}

			if (!is_array($image)) {
				return ['avatarContent' => $image];
			}
		}

		if (isset($userInfo['user'], $userInfo['user']['real_name'])) {
			return ['displayName' => $userInfo['user']['real_name']];
		}

		return ['displayName' => 'User ' . $slackUserId];
	}

	/**
	 * @param string $userId
	 * @param string $slackUserId
	 * @return string|null
	 */
	private function getUserRealName(string $userId, string $slackUserId): ?string {
		$userInfo = $this->request($userId, 'users.info', ['user' => $slackUserId]);
		if (isset($userInfo['error'])) {
			return null;
		}
		if (!isset($userInfo['user'], $userInfo['user']['real_name'])) {
			return null;
		}
		return $userInfo['user']['real_name'];
	}

	/**
	 * @param string $userId
	 * @param bool $useCache
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	public function getMyChannels(string $userId, bool $useCache): array {
		$dCache = $this->cacheFactory->createDistributed(Application::APP_ID);
		$cacheKey = 'channels-' . $userId;

		if ($useCache && $cachedChannels = $dCache->get($cacheKey)) {
			$this->logger->debug('Slack channels cache hit', ['userId' => $userId]);
			return $cachedChannels;
		}

		$cursor = 'dummdumm'; // initial value
		$rawChannels = [];

		while ($cursor !== '' || count($rawChannels) >= Application::MAX_CHANNELS_TO_FETCH) {
			$convFetchResult = $this->request($userId, 'users.conversations', [
				'exclude_archived' => true,
				'types' => 'public_channel,private_channel,im,mpim',
				'limit' => 1000,
				...($cursor !== 'dummdumm' ? ['cursor' => $cursor] : []),
			]);

			if (isset($convFetchResult['error'])) {
				$this->logger->warning('Slack channels fetch error', ['error' => $convFetchResult]);
				return (array)$convFetchResult;
			}

			if (!isset($convFetchResult['channels']) || !is_array($convFetchResult['channels'])) {
				$this->logger->warning('No channels found in Slack', ['response' => $convFetchResult]);
				return ['error' => 'No channels found in Slack'];
			}

			$rawChannels = array_merge($rawChannels, $convFetchResult['channels']);
			$cursor = $convFetchResult['response_metadata']['next_cursor'] ?? '';

			$this->logger->debug('Slack channels fetch', ['count' => count($rawChannels)]);
		}

		/* Cheat sheet:
		 * is_channel, name  => channel
		 * is_group,   topic => group
		 * is_im,      user  => direct
		 */

		$channels = [];

		foreach ($rawChannels as $channel) {
			if (
				(isset($channel['is_group']) && $channel['is_group'])
				|| (isset($channel['is_mpim']) && $channel['is_mpim'])
			) {
				$groupName = array_values(array_filter(
					[
						$channel['topic']['value'] ?? null,
						$channel['purpose']['value'] ?? null,
						$channel['name'] ?? null,
						'Group ' . $channel['id'],
					],
					fn ($val) => $val !== '' && $val !== null,
				))[0];

				$channels[] = [
					'id' => $channel['id'],
					'name' => $groupName,
					'type' => 'group',
					'updated' => $channel['updated'] ?? 0,
				];
			} elseif (isset($channel['is_channel']) && $channel['is_channel']) {
				$channelName = array_values(array_filter(
					[
						$channel['name'] ?? null,
						$channel['topic']['value'] ?? null,
						$channel['purpose']['value'] ?? null,
						'Channel ' . $channel['id'],
					],
					fn ($val) => $val !== '' && $val !== null,
				))[0];

				$channels[] = [
					'id' => $channel['id'],
					'name' => $channelName,
					'type' => 'channel',
					'updated' => $channel['updated'] ?? 0,
				];
			} elseif (isset($channel['user'], $channel['is_im']) && $channel['is_im']) {
				// need to make another request to get the real name
				$realName = $this->getUserRealName($userId, $channel['user']);

				$channels[] = [
					'id' => $channel['id'],
					'user' => $channel['user'],
					'name' => $realName ?? $channel['user'],
					'type' => 'direct',
					'updated' => $channel['updated'] ?? 0,
				];
			} else {
				$this->logger->warning('Unknown channel type from Slack', ['channel' => $channel]);
			}
		}

		$dCache->set($cacheKey, $channels, Application::CHANNELS_CACHE_TTL);
		return $channels;
	}

	/**
	 * @param string $userId
	 * @param string $message
	 * @param string $channelId
	 * @return array|string[]
	 * @throws PreConditionNotMetException
	 */
	public function sendMessage(string $userId, string $message, string $channelId): array {
		$params = [
			'as_user' => true, // legacy but we'll use it for now
			'link_names' => false, // we onlu send links (public and internal)
			'parse' => 'full',
			'unfurl_links' => true,
			'unfurl_media' => true,
			'channel' => $channelId,
			'text' => $message,
		];
		return $this->request($userId, 'chat.postMessage', $params, 'POST');
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
				$share->setLabel('Slack (' . $channelName . ')');

				if ($expirationDate !== null) {
					$share->setExpirationDate(new DateTime($expirationDate));
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

		if (count($links) === 0) {
			return ['error' => 'Files not found'];
		}

		$message = ($comment !== ''
			? $comment . "\n\n"
			: '') . join("\n", array_map(fn ($link) => $link['name'] . ': ' . $link['url'], $links));

		return $this->sendMessage($userId, $message, $channelId);
	}

	/**
	 * @param string $userId
	 * @param int $fileId
	 * @param string $channelId
	 * @param string $comment
	 * @return array|string[]
	 * @throws NoUserException
	 * @throws NotPermittedException
	 * @throws LockedException
	 */
	public function sendFile(string $userId, int $fileId, string $channelId, string $comment = ''): array {
		$userFolder = $this->root->getUserFolder($userId);
		$file = $userFolder->getFirstNodeById($fileId);

		if ($file !== null && $file instanceof File) {
			// files.upload is deprecated and does not work if the oauth app has been created after 2024.05.08
			// so we follow https://api.slack.com/messaging/files#uploading_files

			// files.getUploadURLExternal
			$params = [
				'filename' => $file->getName(),
				'length' => $file->getSize(),
			];
			$uploadUrlResult = $this->request($userId, 'files.getUploadURLExternal', $params);

			if (!isset($uploadUrlResult['upload_url'])
				|| !is_string($uploadUrlResult['upload_url'])
				|| !isset($uploadUrlResult['file_id'])
				|| !is_string($uploadUrlResult['file_id'])
			) {
				return ['error' => 'Cannot get the upload URL'];
			}

			// POST to upload URL
			$uploadUrl = $uploadUrlResult['upload_url'];
			$uploadedFileId = $uploadUrlResult['file_id'];
			$uploadResult = $this->networkService->uploadFile($uploadUrl, $file);
			if (!isset($uploadResult['success'])) {
				return $uploadResult;
			}

			// files.completeUploadExternal
			$params = [
				'channel_id' => $channelId,
				'files' => [
					['id' => $uploadedFileId, 'title' => $file->getName()],
				],
			];
			if ($comment !== '') {
				$params['initial_comment'] = $comment;
			}

			$sendResult = $this->request($userId, 'files.completeUploadExternal', $params, 'POST');

			if (isset($sendResult['error'])) {
				$this->logger->warning('Slack file upload error: ', ['error' => $sendResult]);
				return (array)$sendResult;
			}

			return ['success' => true];
		} else {
			return ['error' => 'File not found'];
		}
	}

	/**
	 * @param string $userId
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @param bool $jsonResponse
	 * @param bool $slackApiRequest
	 * @return array|mixed|resource|string|string[]
	 * @throws PreConditionNotMetException
	 */
	public function request(string $userId, string $endPoint, array $params = [], string $method = 'GET',
		bool $jsonResponse = true, bool $slackApiRequest = true) {
		$this->checkTokenExpiration($userId);
		return $this->networkService->request($userId, $endPoint, $params, $method, $jsonResponse, $slackApiRequest);
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
			$nowTs = (new DateTime())->getTimestamp();
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
		$clientID = $this->appConfig->getAppValueString('client_id', lazy: true);
		$clientSecret = $this->appConfig->getAppValueString('client_secret', lazy: true);
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		$refreshToken = $refreshToken === '' ? '' : $this->crypto->decrypt($refreshToken);

		if (!$refreshToken) {
			$this->logger->error('No Slack refresh token found', ['app' => Application::APP_ID]);
			return false;
		}

		try {
			$clientSecret = $this->crypto->decrypt($clientSecret);
		} catch (Exception $e) {
			$this->logger->error('Unable to decrypt Slack secrets', ['app' => Application::APP_ID]);
			return false;
		}

		$result = $this->requestOAuthAccessToken(Application::SLACK_OAUTH_ACCESS_URL, [
			'client_id' => $clientID,
			'client_secret' => $clientSecret,
			'grant_type' => 'refresh_token',
			'refresh_token' => $refreshToken,
		], 'POST');

		if (isset($result['access_token'])) {
			$this->logger->info('Slack access token successfully refreshed', ['app' => Application::APP_ID]);

			$accessToken = $result['access_token'];
			$refreshToken = $result['refresh_token'];
			$encryptedAccessToken = $accessToken === '' ? '' : $this->crypto->encrypt($accessToken);
			$encryptedRefreshToken = $refreshToken === '' ? '' : $this->crypto->encrypt($refreshToken);
			$this->config->setUserValue($userId, Application::APP_ID, 'token', $encryptedAccessToken);
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
				'Token is not valid anymore. Impossible to refresh it: '
					. ($result['error'] ?? '') . ' '
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
			$this->logger->warning('Slack OAuth error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}
}
