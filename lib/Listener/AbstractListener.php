<?php

/**
 * @copyright Copyright (c) 2022, Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
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

use OCA\Mattermost\AppInfo\Application;
use OCA\Mattermost\Service\WebhookService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;

/**
 * @template-implements IEventListener<Event>
 */
abstract class AbstractListener implements IEventListener {

	public function __construct(
		protected IConfig $config,
		private WebhookService $webhookService,
		protected ?string $userId,
	) {
	}

	public function handle(Event $event): void {
		$info = $this->handleIncomingEvent($event);

		if ($info !== null) {
			[$url, $content] = $info;
			$content['eventType'] = get_class($event);
			$secret = $this->config->getUserValue($this->userId, Application::APP_ID, Application::WEBHOOK_SECRET_CONFIG_KEY);
			$this->webhookService->sendWebhook($url, $content, $secret);
		}
	}

	abstract public function handleIncomingEvent(Event $event): ?array;
}
