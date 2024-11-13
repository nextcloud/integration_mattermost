<?php
/**
 * Nextcloud - Slack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Anupam Kumar <kyteinsky@gmail.com>
 * @copyright Copyright (c) 2023 Anupam Kumar <kyteinsky@gmail.com>
 */

namespace OCA\Slack\Listener;

use OCA\Slack\AppInfo\Application;
use OCP\Collaboration\Resources\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\Util;

/**
 * @template-implements IEventListener<Event>
 */
class FilesMenuListener implements IEventListener {

	public function __construct(
		private IConfig $config,
		private ?string $userId,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}

		if (is_null($this->userId)) {
			return;
		}

		if ($this->config->getUserValue($this->userId, Application::APP_ID, 'file_action_enabled', '1') !== '1') {
			return;
		}

		Util::addInitScript(Application::APP_ID, Application::APP_ID . '-filesplugin');
	}
}
