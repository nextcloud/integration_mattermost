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

use OCA\DAV\Events\CalendarObjectCreatedEvent;
use OCA\Mattermost\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class CalendarObjectCreatedListener extends AbstractListener implements IEventListener {

	public function handleIncomingEvent(Event $event): ?array {
		if (!($event instanceof CalendarObjectCreatedEvent)) {
			return null;
		}

		if ($this->userId === null || $this->userId === '') {
			return null;
		}

		$url = $this->config->getUserValue($this->userId, Application::APP_ID, Application::CALENDAR_EVENT_CREATED_WEBHOOK_CONFIG_KEY);
		if ($url === '') {
			return null;
		}
		$webhooksEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, Application::WEBHOOKS_ENABLED_CONFIG_KEY) === '1';
		if (!$webhooksEnabled) {
			return null;
		}

		return [
			$url,
			[
				'calendarId' => $event->getCalendarId(),
				'calendarData' => $event->getCalendarData(),
				'shares' => $event->getShares(),
				'objectData' => $event->getObjectData(),
			]
		];
	}
}
