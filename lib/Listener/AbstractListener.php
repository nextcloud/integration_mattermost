<?php

/**
 * @copyright Copyright (c) 2022, Julien Veyssier <eneiluj@posteo.net>
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
 *
 */
namespace OCA\Mattermost\Listener;

use OCA\Mattermost\Service\MattermostAPIService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;

abstract class AbstractListener implements IEventListener {
	/**
	 * @var MattermostAPIService
	 */
	private $mattermostAPIService;
	/**
	 * @var IConfig
	 */
	protected $config;

	public function __construct(IConfig $config,
								MattermostAPIService $mattermostAPIService) {
		$this->mattermostAPIService = $mattermostAPIService;
		$this->config = $config;
	}

	public function handle(Event $event): void {
		$info = $this->handleIncomingEvent($event);

		if ($info !== null) {
			[$url, $content] = $info;
			$content['eventType'] = get_class($event);
			$this->mattermostAPIService->sendWebhook($url, $content);
		}
	}

	abstract public function handleIncomingEvent(Event $event): ?array;
}
