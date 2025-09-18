<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022, Julien Veyssier
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
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

use OCA\Mattermost\AppInfo\Application;
use OCA\Mattermost\Service\MattermostAPIService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\IExternalProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class MattermostSearchMessagesProvider implements IProvider, IExternalProvider {

	public function __construct(
		private IAppManager $appManager,
		private IL10N $l10n,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private IDateTimeFormatter $dateTimeFormatter,
		private IDateTimeZone $dateTimeZone,
		private MattermostAPIService $service,
	) {
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
		$searchMessagesEnabled = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'search_messages_enabled', '0') === '1';
		if ($accessToken === '' || !$searchMessagesEnabled) {
			return SearchResult::paginated($this->getName(), [], 0);
		}
		$mattermostUrl = $this->service->getMattermostUrl($user->getUID());

		$searchResult = $this->service->searchMessages($user->getUID(), $term, $offset, $limit);
		if (isset($searchResult['error'])) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$formattedResults = array_map(function (array $entry) use ($mattermostUrl): SearchResultEntry {
			$finalThumbnailUrl = $this->getThumbnailUrl($entry);
			return new SearchResultEntry(
				$finalThumbnailUrl,
				$this->getMainText($entry),
				$this->getSubline($entry),
				$this->getLinkToMattermost($entry, $mattermostUrl),
				$finalThumbnailUrl === '' ? 'icon-mattermost-search-fallback' : '',
				true
			);
		}, $searchResult);

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
		if ($entry['channel_type'] === 'D') {
			return $this->l10n->t('%s in @%s at %s', [$entry['user_name'], $entry['direct_message_user_name'], $this->getFormattedDate($entry['create_at'])]);
		}
		return $this->l10n->t('%s in #%s at %s', [$entry['user_name'], $entry['channel_name'], $this->getFormattedDate($entry['create_at'])]);
	}

	protected function getFormattedDate(int $timestamp): string {
		return $this->dateTimeFormatter->formatDateTime((int)($timestamp / 1000), 'long', 'short', $this->dateTimeZone->getTimeZone());
	}

	/**
	 * @param array $entry
	 * @param string $url
	 * @return string
	 */
	protected function getLinkToMattermost(array $entry, string $url): string {
		/*
		if ($entry['channel_type'] === 'D') {
			// in a direct conversation
			return $url . '/' . $entry['team_name'] . '/messages/@' . $entry['direct_message_user_name'];
		}
		*/
		// in a channel
		// return $url . '/' . $entry['team_name'] . '/channels/' . $entry['channel_name'];

		// most generic way: permalinks
		// https://mm.org/teamID/pl/postID
		return $url . '/' . $entry['team_name'] . '/pl/' . $entry['id'];
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getThumbnailUrl(array $entry): string {
		$userId = $entry['user_id'] ?? '';
		return $userId
			? $this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->linkToRoute('integration_mattermost.mattermostAPI.getUserAvatar', ['userId' => $userId])
			)
			: '';
	}

		public function isExternalProvider(): bool {
		return True;
	}
}
