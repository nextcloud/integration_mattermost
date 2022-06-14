<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022, Julien Veyssier
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Mattermost\Search;

use DateTime;
use OCA\Mattermost\Service\MattermostAPIService;
use OCA\Mattermost\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IDateTimeFormatter;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;

class MattermostSearchMessagesProvider implements IProvider {

	/** @var IAppManager */
	private $appManager;

	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var MattermostAPIService
	 */
	private $service;
	private IDateTimeFormatter $dateTimeFormatter;
	private IDateTimeZone $dateTimeZone;

	/**
	 * CospendSearchProvider constructor.
	 *
	 * @param IAppManager $appManager
	 * @param IL10N $l10n
	 * @param IConfig $config
	 * @param IURLGenerator $urlGenerator
	 * @param MattermostAPIService $service
	 */
	public function __construct(IAppManager $appManager,
								IL10N $l10n,
								IConfig $config,
								IURLGenerator $urlGenerator,
								IDateTimeFormatter $dateTimeFormatter,
								IDateTimeZone $dateTimeZone,
								MattermostAPIService $service) {
		$this->appManager = $appManager;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->service = $service;
		$this->dateTimeFormatter = $dateTimeFormatter;
		$this->dateTimeZone = $dateTimeZone;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'mattermost-search-messages';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Mattermost messages');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if (strpos($route, Application::APP_ID . '.') === 0) {
			// Active app, prefer Mattermost results
			return -1;
		}

		return 20;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			return SearchResult::complete($this->getName(), []);
		}

		$limit = $query->getLimit();
		$term = $query->getTerm();
		$offset = $query->getCursor();
		$offset = $offset ? intval($offset) : 0;

		$accessToken = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'token');
		$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');
		$url = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
		$searchIssuesEnabled = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'search_messages_enabled', '0') === '1';
		if ($accessToken === '' || !$searchIssuesEnabled) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$issues = $this->service->searchMessages($user->getUID(), $url, $term, $offset, $limit);
		if (isset($searchResult['error'])) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$formattedResults = array_map(function (array $entry) use ($url): MattermostSearchResultEntry {
			$finalThumbnailUrl = $this->getThumbnailUrl($entry);
			return new MattermostSearchResultEntry(
				$finalThumbnailUrl,
				$this->getMainText($entry),
				$this->getSubline($entry),
				$this->getLinkToMattermost($entry, $url),
				$finalThumbnailUrl === '' ? 'icon-mattermost-search-fallback' : '',
				true
			);
		}, $issues);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$offset + $limit
		);
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getMainText(array $entry): string {
		return $entry['message'];
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getSubline(array $entry): string {
		return $this->l10n->t('%s in #%s at %s', [$entry['user_name'], $entry['channel_name'], $this->getFormattedDate($entry['create_at'])]);
	}

	protected function getFormattedDate(int $timestamp): string {
		// return (new DateTime())->setTimestamp((int) ($timestamp / 1000))->format('Y-m-d H:i:s');
		return $this->dateTimeFormatter->formatDateTime((int) ($timestamp / 1000), 'long', 'short', $this->dateTimeZone->getTimeZone());
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getLinkToMattermost(array $entry, string $url): string {
		return $url . '/' . $entry['team_name'] . '/channels/' . $entry['channel_name'];
	}

	/**
	 * @param array $entry
	 * @param string $thumbnailUrl
	 * @return string
	 */
	protected function getThumbnailUrl(array $entry): string {
		$userId = $entry['user_id'] ?? '';
		return $userId
			? $this->urlGenerator->linkToRoute('integration_mattermost.mattermostAPI.getUserAvatar', []) . '?userId=' . urlencode(strval($userId))
			: '';
	}
}
