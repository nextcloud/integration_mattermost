<?php

/**
 * Nextcloud - Mattermost
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Mattermost\Command;

use OCA\Mattermost\Service\WebhookService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImminentEvents extends Command {

	public function __construct(
		private WebhookService $webhookService,
	) {
		parent::__construct();
	}

	protected function configure() {
		$this->setName('mattermost:imminent-events-webhook')
			->setDescription('Manually trigger the "imminent events" webhook')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL,
				'The user for which the webhook should be triggered'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = $input->getArgument('user_id');
		if ($userId !== null) {
			$jobResult = $this->webhookService->userImminentEventsWebhook($userId);
			$nbEvents = $jobResult['nb_events'];
			if ($nbEvents === null) {
				$output->writeln('[' . $userId . '] ' . $jobResult['message']);
				return 0;
			}
			$output->writeln('[' . $userId . '] ' . $nbEvents . ' events sent');
		} else {
			$output->writeln('Trigger imminent events for all users');
			foreach ($this->webhookService->imminentEventsWebhook() as $userResult) {
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
