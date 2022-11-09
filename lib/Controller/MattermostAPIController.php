<?php
/**
 * Nextcloud - Mattermost
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Mattermost\Controller;

use Exception;
use OC\User\NoUserException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Mattermost\Service\MattermostAPIService;
use OCA\Mattermost\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\Lock\LockedException;

class MattermostAPIController extends Controller {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var MattermostAPIService
	 */
	private $mattermostAPIService;
	/**
	 * @var string|null
	 */
	private $userId;
	/**
	 * @var string
	 */
	private $mattermostUrl;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								IURLGenerator $urlGenerator,
								MattermostAPIService $mattermostAPIService,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->mattermostAPIService = $mattermostAPIService;
		$this->userId = $userId;
		$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');
		$this->mattermostUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getMattermostUrl(): DataResponse {
		return new DataResponse($this->mattermostUrl);
	}

	/**
	 * get mattermost user avatar
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $userId
	 * @param int $useFallback
	 * @return DataDisplayResponse|RedirectResponse
	 * @throws \Exception
	 */
	public function getUserAvatar(string $userId, int $useFallback = 1) {
		$result = $this->mattermostAPIService->getUserAvatar($this->userId, $userId, $this->mattermostUrl);
		if (isset($result['avatarContent'])) {
			$response = new DataDisplayResponse($result['avatarContent']);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} elseif ($useFallback !== 0 && isset($result['userInfo'])) {
			$userName = $result['userInfo']['username'] ?? '??';
			$fallbackAvatarUrl = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $userName, 'size' => 44]);
			return new RedirectResponse($fallbackAvatarUrl);
		}
		return new DataDisplayResponse('', Http::STATUS_NOT_FOUND);
	}

	/**
	 * get Mattermost team icon/avatar
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $teamId
	 * @param int $useFallback
	 * @return DataDisplayResponse|RedirectResponse
	 * @throws \Exception
	 */
	public function getTeamAvatar(string $teamId, int $useFallback = 1)	{
		$result = $this->mattermostAPIService->getTeamAvatar($this->userId, $teamId, $this->mattermostUrl);
		if (isset($result['avatarContent'])) {
			$response = new DataDisplayResponse($result['avatarContent']);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} elseif ($useFallback !== 0 && isset($result['teamInfo'])) {
			$projectName = $result['teamInfo']['display_name'] ?? '??';
			$fallbackAvatarUrl = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $projectName, 'size' => 44]);
			return new RedirectResponse($fallbackAvatarUrl);
		}
		return new DataDisplayResponse('', Http::STATUS_NOT_FOUND);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 * @throws Exception
	 */
	public function getNotifications(?int $since = null) {
		$mmUserName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$result = $this->mattermostAPIService->getMentionsMe($this->userId, $mmUserName, $this->mattermostUrl, $since);
		if (isset($result['error'])) {
			return new DataResponse($result['error'], Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 * @throws Exception
	 */
	public function getChannels() {
		$result = $this->mattermostAPIService->getMyChannels($this->userId, $this->mattermostUrl);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $message
	 * @param string $channelId
	 * @return DataResponse
	 * @throws Exception
	 */
	public function sendMessage(string $message, string $channelId) {
		$result = $this->mattermostAPIService->sendMessage($this->userId, $this->mattermostUrl, $message, $channelId);
		if (isset($result['error'])) {
			return new DataResponse($result['error'], Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $fileId
	 * @param string $channelId
	 * @return DataResponse
	 * @throws NotPermittedException
	 * @throws LockedException
	 * @throws NoUserException
	 */
	public function sendFile(int $fileId, string $channelId) {
		$result = $this->mattermostAPIService->sendFile($this->userId, $this->mattermostUrl, $fileId, $channelId);
		if (isset($result['error'])) {
			return new DataResponse($result['error'], Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param array $fileIds
	 * @param string $channelId
	 * @param string $channelName
	 * @param string $comment
	 * @param string $permission
	 * @param string|null $expirationDate
	 * @param string|null $password
	 * @return DataResponse
	 * @throws NoUserException
	 * @throws NotPermittedException
	 */
	public function sendPublicLinks(array $fileIds, string $channelId, string $channelName, string $comment,
							  string $permission, ?string $expirationDate = null, ?string $password = null): DataResponse {
		$result = $this->mattermostAPIService->sendPublicLinks(
			$this->userId, $this->mattermostUrl, $fileIds, $channelId, $channelName,
			$comment, $permission, $expirationDate, $password
		);
		if (isset($result['error'])) {
			return new DataResponse($result['error'], Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}
}
