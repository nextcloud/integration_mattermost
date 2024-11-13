<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Slack\Migration;

use Closure;
use OCA\Slack\AppInfo\Application;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

class Version020000Date20241113112051 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $connection,
		private ICrypto $crypto,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		// user tokens
		$qbUpdate = $this->connection->getQueryBuilder();
		$qbUpdate->update('preferences')
			->set('configvalue', $qbUpdate->createParameter('updateValue'))
			->where(
				$qbUpdate->expr()->eq('appid', $qbUpdate->createNamedParameter(Application::APP_ID, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qbUpdate->expr()->eq('userid', $qbUpdate->createParameter('updateUserId'))
			)
			->andWhere(
				$qbUpdate->expr()->eq('configkey', $qbUpdate->createParameter('updateConfigKey'))
			);

		$qbSelect = $this->connection->getQueryBuilder();
		$qbSelect->select('userid', 'configvalue', 'configkey')
			->from('preferences')
			->where(
				$qbSelect->expr()->eq('appid', $qbSelect->createNamedParameter(Application::APP_ID, IQueryBuilder::PARAM_STR))
			);

		$or = $qbSelect->expr()->orx();
		$or->add($qbSelect->expr()->eq('configkey', $qbSelect->createNamedParameter('token', IQueryBuilder::PARAM_STR)));
		$or->add($qbSelect->expr()->eq('configkey', $qbSelect->createNamedParameter('refresh_token', IQueryBuilder::PARAM_STR)));
		$qbSelect->andWhere($or);

		$qbSelect->andWhere(
			$qbSelect->expr()->nonEmptyString('configvalue')
		)
			->andWhere(
				$qbSelect->expr()->isNotNull('configvalue')
			);
		$req = $qbSelect->executeQuery();
		while ($row = $req->fetch()) {
			try {
				$userId = $row['userid'];
				$configKey = $row['configkey'];
				$storedClearToken = $row['configvalue'];
				$encryptedToken = $this->crypto->encrypt($storedClearToken);
				$qbUpdate->setParameter('updateConfigKey', $configKey, IQueryBuilder::PARAM_STR);
				$qbUpdate->setParameter('updateValue', $encryptedToken, IQueryBuilder::PARAM_STR);
				$qbUpdate->setParameter('updateUserId', $userId, IQueryBuilder::PARAM_STR);
				$qbUpdate->executeStatement();
			} catch (\Exception|\Throwable $e) {
				$this->logger->warning('Failed to migrate token/refresh_token for user ' . $userId, ['exception' => $e]);
			}
		}
		$req->closeCursor();
	}
}
