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

namespace OCA\Slack\Controller;

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

use OCA\Slack\Service\MattermostAPIService;
use OCP\IURLGenerator;
use OCP\Lock\LockedException;

class MattermostAPIController extends Controller {

	private string $mattermostUrl;

	public function __construct(string                       $appName,
								IRequest                     $request,
								private IConfig              $config,
								private IURLGenerator        $urlGenerator,
								private MattermostAPIService $mattermostAPIService,
								private ?string              $userId) {
		parent::__construct($appName, $request);
	}

	// TODO: check if this is still needed
	// --> user_avatar
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
		$result = $this->mattermostAPIService->getUserAvatar($this->userId, $userId);
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
	public function getTeamAvatar(string $teamId, int $useFallback = 1): DataDisplayResponse | RedirectResponse	{
		$result = $this->mattermostAPIService->getTeamAvatar($this->userId, $teamId);
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
	public function getChannels() {
		$result = $this->mattermostAPIService->getMyChannels($this->userId);
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
	 * @param array|null $remoteFileIds
	 * @return DataResponse
	 * @throws Exception
	 */
	public function sendMessage(string $message, string $channelId, ?array $remoteFileIds = null) {
		$result = $this->mattermostAPIService->sendMessage($this->userId, $message, $channelId, $remoteFileIds);
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
		$result = $this->mattermostAPIService->sendFile($this->userId, $fileId, $channelId);
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
			$this->userId, $fileIds, $channelId, $channelName,
			$comment, $permission, $expirationDate, $password
		);
		if (isset($result['error'])) {
			return new DataResponse($result['error'], Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}
}
