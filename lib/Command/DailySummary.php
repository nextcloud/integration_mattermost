<?php

/**
 * Nextcloud - Mattermost
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Mattermost\Command;

use OCA\Mattermost\Service\MattermostAPIService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DailySummary extends Command {

	/**
	 * @var MattermostAPIService
	 */
	private $mattermostAPIService;

	public function __construct(MattermostAPIService $mattermostAPIService) {
		parent::__construct();
		$this->mattermostAPIService = $mattermostAPIService;
	}

	protected function configure() {
		$this->setName('mattermost:daily-summary-webhook')
			->setDescription('Manually trigger daily summary webhook')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL,
				'The user for which the webhook should be triggered'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$userId = $input->getArgument('user_id');
		if ($userId !== null) {
			$nbEvents = $this->mattermostAPIService->userDailySummaryWebhook($userId);
			if ($nbEvents === null) {
				$output->writeln('Daily summary webhook is disabled for user ' . $userId);
				return 0;
			}
			$output->writeln($nbEvents . ' events sent for user ' . $userId);
		} else {
			$output->writeln('Trigger daily summary for all users');
			foreach ($this->mattermostAPIService->dailySummaryWebhook() as $userResult) {
				$userId = $userResult['user_id'];
				if ($userResult['nb_events'] === null) {
					$output->writeln('Daily summary webhook is disabled for user ' . $userId);
				} else {
					$nbEvents = $userResult['nb_events'];
					$output->writeln($nbEvents . ' events sent for user ' . $userId);
				}
			}
		}
		return 0;
	}
}
