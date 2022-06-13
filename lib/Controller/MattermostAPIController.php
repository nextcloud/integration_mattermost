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

use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Mattermost\Service\MattermostAPIService;
use OCA\Mattermost\AppInfo\Application;
use OCP\IURLGenerator;

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
	private $accessToken;
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
		$this->accessToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$this->mattermostUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', 'https://mattermost.com');
		$this->mattermostUrl = $this->mattermostUrl && $this->mattermostUrl !== '' ? $this->mattermostUrl : 'https://mattermost.com';
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * get notification list
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
	 * @param int $userId
	 */
	public function getUserAvatar(int $userId) {
		$result = $this->mattermostAPIService->getUserAvatar($this->userId, $userId, $this->mattermostUrl);
		if (isset($result['userInfo'])) {
			$userName = $result['userInfo']['name'] ?? '??';
			$fallbackAvatarUrl = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $userName, 'size' => 44]);
			return new RedirectResponse($fallbackAvatarUrl);
		} else {
			$response = new DataDisplayResponse($result['avatarContent']);
			$response->cacheFor(60*60*24);
			return $response;
		}
	}

	/**
	 * get Mattermost team icon/avatar
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $teamId
	 * @return DataDisplayResponse|RedirectResponse
	 */
	public function getTeamAvatar(int $teamId) {
		$result = $this->mattermostAPIService->getProjectAvatar($this->userId, $teamId, $this->mattermostUrl);
		if (isset($result['projectInfo'])) {
			$projectName = $result['projectInfo']['name'] ?? '??';
			$fallbackAvatarUrl = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $projectName, 'size' => 44]);
			return new RedirectResponse($fallbackAvatarUrl);
		} else {
			$response = new DataDisplayResponse($result['avatarContent']);
			$response->cacheFor(60*60*24);
			return $response;
		}
	}
}
