<?php
/**
 * @copyright Copyright (c) 2023 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Mattermost\Reference;

use OCA\Mattermost\Service\MattermostAPIService;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Mattermost\AppInfo\Application;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\Reference;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IURLGenerator;

class MessageReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_message';

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private ReferenceManager $referenceManager,
		private IDateTimeFormatter $dateTimeFormatter,
		private IDateTimeZone $dateTimeZone,
		private MattermostAPIService $mattermostAPIService,
		private ?string $userId) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string	{
		return 'mattermost-message';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Mattermost messages');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int	{
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getSupportedSearchProviderIds(): array {
		return ['mattermost-search-messages'];
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		if ($this->userId !== null) {
			$mattermostUrl = $this->mattermostAPIService->getMattermostUrl($this->userId);
			if ($mattermostUrl) {
				return preg_match('/^' . preg_quote($mattermostUrl, '/') . '\/[^\/\?]+\/[^\/\?]+\/[^\/\?]+$/', $referenceText) === 1;
			}
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$mattermostUrl = $this->mattermostAPIService->getMattermostUrl($this->userId);
			$postId = $this->getPostId($mattermostUrl, $referenceText);
			if ($postId !== null) {
				$postInfo = $this->mattermostAPIService->getPostInfo($this->userId, $postId);
				if (isset($postInfo['message'], $postInfo['channel_id'], $postInfo['user_id'])) {
					$channelInfo = $this->mattermostAPIService->getChannelInfo($this->userId, $postInfo['channel_id']);
					$userInfo = $this->mattermostAPIService->getUserInfo($this->userId, $postInfo['user_id']);
					if (isset($channelInfo['name'], $channelInfo['type'], $userInfo['username'])) {
						$reference = new Reference($referenceText);
						$reference->setTitle($postInfo['message']);
						if ($channelInfo['type'] === 'D') {
							$description = $this->l10n->t('%s in @%s at %s', [$userInfo['username'], $userInfo['username'], $this->getFormattedDate($postInfo['create_at'])]);
						} else {
							$description = $this->l10n->t('%s in #%s at %s', [$userInfo['username'], $channelInfo['name'], $this->getFormattedDate($postInfo['create_at'])]);
						}
						$reference->setDescription($description);
						$thumbnailUrl = $this->urlGenerator->getAbsoluteURL(
							$this->urlGenerator->linkToRoute('integration_mattermost.mattermostAPI.getUserAvatar', ['userId' => $userInfo['id']])
						);
						$reference->setImageUrl($thumbnailUrl);
						return $reference;
					}
				}
			}
		}

		return null;
	}

	/**
	 * @param int $timestamp
	 * @return string
	 */
	private function getFormattedDate(int $timestamp): string {
		return $this->dateTimeFormatter->formatDateTime((int) ($timestamp / 1000), 'long', 'short', $this->dateTimeZone->getTimeZone());
	}

	/**
	 * @param string $mattermostUrl
	 * @param string $url
	 * @return string|null
	 */
	private function getPostId(string $mattermostUrl, string $url): ?string {
		preg_match('/^' . preg_quote($mattermostUrl, '/') . '\/[^\/\?]+\/[^\/\?]+\/([^\/\?]+)$/', $url, $matches);
		return count($matches) > 1 ? $matches[1] : null;
	}

	/**
	 * We use the userId here because when connecting/disconnecting from the GitHub account,
	 * we want to invalidate all the user cache and this is only possible with the cache prefix
	 * @inheritDoc
	 */
	public function getCachePrefix(string $referenceId): string {
		return $this->userId ?? '';
	}

	/**
	 * We don't use the userId here but rather a reference unique id
	 * @inheritDoc
	 */
	public function getCacheKey(string $referenceId): ?string {
		return $referenceId;
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	public function invalidateUserCache(string $userId): void {
		$this->referenceManager->invalidateCache($userId);
	}
}
