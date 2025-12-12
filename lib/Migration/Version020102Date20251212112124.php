<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Mattermost\Migration;

use Closure;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\Security\ICrypto;

class Version020102Date20251212112124 extends SimpleMigrationStep {

	public function __construct(
		private ICrypto $crypto,
		private IConfig $config,
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		foreach (['client_id', 'client_secret', 'oauth_instance_url', 'use_popup', 'navlink_default'] as $key) {
			$value = $this->appConfig->getAppValueString($key, lazy: true);
			if ($value === '') {
				continue;
			}
			if (in_array($key, ['client_id', 'client_secret'], true)) {
				$value = $this->crypto->decrypt($value);
				$this->appConfig->setAppValueString($key, $value, lazy: true, sensitive: true);
			} else {
				$this->appConfig->setAppValueString($key, $value, lazy: true);
			}

		}
	}
}
