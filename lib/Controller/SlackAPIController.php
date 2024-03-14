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

use Exception;
use OC\User\NoUserException;
use OCA\Slack\Service\SlackAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Lock\LockedException;

class SlackAPIController extends Controller {

	public function __construct(
		string                  $appName,
		IRequest                $request,
		private IConfig         $config,
		private IURLGenerator   $urlGenerator,
		private SlackAPIService $slackAPIService,
		private ?string         $userId) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get Slack user avatar
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $slackUserId
	 * @param int $useFallback
	 * @return DataDisplayResponse|RedirectResponse
	 * @throws \Exception
	 */
	public function getUserAvatar(string $slackUserId, int $useFallback = 1): DataDisplayResponse|RedirectResponse {
		$result = $this->slackAPIService->getUserAvatar($this->userId, $slackUserId);
		if (isset($result['avatarContent'])) {
			$response = new DataDisplayResponse($result['avatarContent']);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		}
		if ($useFallback !== 0 && isset($result['displayName'])) {
			$fallbackAvatarUrl = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $result['displayName'], 'size' => 44]);
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
		$result = $this->slackAPIService->getMyChannels($this->userId);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
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
		$result = $this->slackAPIService->sendMessage($this->userId, $message, $channelId);
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
	 * @param string $comment
	 * @return DataResponse
	 * @throws NotPermittedException
	 * @throws LockedException
	 * @throws NoUserException
	 */
	public function sendFile(int $fileId, string $channelId, string $comment = '') {
		$result = $this->slackAPIService->sendFile($this->userId, $fileId, $channelId, $comment);
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
		$result = $this->slackAPIService->sendPublicLinks(
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
