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

use DateTime;
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
			$today = (new Datetime())->format('Y-m-d');
			$jobResult = $this->mattermostAPIService->userDailySummaryWebhook($userId, $today);
			$nbEvents = $jobResult['nb_events'];
			if ($nbEvents === null) {
				$output->writeln('[' . $userId . '] ' . $jobResult['message']);
				return 0;
			}
			$output->writeln('[' . $userId . '] ' . $nbEvents . ' events sent');
		} else {
			$output->writeln('Trigger daily summary for all users');
			foreach ($this->mattermostAPIService->dailySummaryWebhook() as $userResult) {
				$userId = $userResult['user_id'];
				$jobResult = $userResult['job_info'];
				if ($jobResult['nb_events'] === null) {
					$output->writeln('[' . $userId . '] ' . $jobResult['message']);
				} else {
					$nbEvents = $jobResult['nb_events'];
					$output->writeln('[' . $userId . '] ' . $nbEvents . ' events sent');
				}
			}
		}
		return 0;
	}
}
