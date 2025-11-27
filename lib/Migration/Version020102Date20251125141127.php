<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Slack\Migration;

use Closure;
use OCP\AppFramework\Services\IAppConfig;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version020102Date20251125141127 extends SimpleMigrationStep {
	private static array $configKeys = [
		'client_id',
		'client_secret',
		'use_popup',
	];

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$allSetKeys = $this->appConfig->getAppKeys();

		foreach (self::$configKeys as $key) {
			// skip if not already set
			if (!in_array($key, $allSetKeys)) {
				continue;
			}
			$value = $this->appConfig->getAppValueString($key);
			$this->appConfig->setAppValueString($key, $value, lazy: true);
		}
	}
}
