<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Julien Veyssier <eneiluj@posteo.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Mattermost\BackgroundJob;

use OCA\Mattermost\Service\WebhookService;
use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;

use OCA\Mattermost\Service\MattermostAPIService;

/**
 * Class CheckOpenTickets
 *
 * @package OCA\Mattermost\BackgroundJob
 */
class DailySummaryWebhook extends TimedJob {

	/** @var LoggerInterface */
	protected $logger;
	/**
	 * @var WebhookService
	 */
	private $webhookService;

	public function __construct(ITimeFactory $time,
								WebhookService $webhookService,
								LoggerInterface $logger) {
		parent::__construct($time);
		// Every hour but it will only do something once a day
		$this->setInterval(60 * 60);

		$this->logger = $logger;
		$this->webhookService = $webhookService;
	}

	protected function run($argument): void {
		foreach ($this->webhookService->dailySummaryWebhook() as $userResult) {
			$userId = $userResult['user_id'];
			$this->logger->debug('Mattermost daily summary webhook for user "' . $userId . '"');
		}
	}
}
