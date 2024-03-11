<?php

namespace OCA\Slack\Tests;

use OC\Http\Client\ClientService;
use OC\L10N\L10N;
use OCA\Slack\AppInfo\Application;
use OCA\Slack\Service\NetworkService;
use OCA\Slack\Service\SlackAPIService;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Security\ICrypto;
use OCP\Share\IManager as ShareManager;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class SlackAPIServiceTest extends TestCase {
	private LoggerInterface $logger;
	private IL10N $l10n;
	private IConfig $config;
	private IRootFolder $root;
	private ShareManager $shareManager;
	private IURLGenerator $urlGenerator;
	private ICrypto $crypto;
	private NetworkService $networkService;
	private IClientService $clientService;

	private SlackAPIService $apiService;

	public function setUp(): void {
		parent::setUp();

		$this->setupDummies();
	}

	private function setupDummies(): void {
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->l10n = $this->createMock(L10N::class);
		$this->config = $this->createMock(IConfig::class);
		$this->root = $this->createMock(IRootFolder::class);
		$this->shareManager = $this->createMock(ShareManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->networkService = $this->createMock(NetworkService::class);
		$this->clientService = $this->createMock(ClientService::class);

		$this->apiService = new SlackAPIService(
			$this->logger,
			$this->l10n,
			$this->config,
			$this->root,
			$this->shareManager,
			$this->urlGenerator,
			$this->crypto,
			$this->networkService,
			$this->clientService
		);
	}

	public function testDummy() {
		$app = new Application();
		$this->assertEquals('integration_slack', $app::APP_ID);
	}

	public function testUsetAvatar() {
		$this->networkService->method('request')->willReturnCallback(function (
			string $userId,
			string $endPoint,
			array $params,
		) {
			if (isset($params['user']) && $params['user'] === 'slackid2') {
				return [ 'user' => [ 'real_name' => 'realname' ] ];
			}
			if (isset($params['user']) && $params['user'] === 'slackid3') {
				return [ 'user' => [ 'profile' => [ 'image_48' => 'image_location_url' ] ] ];
			}
			if ($endPoint === 'image_location_url') {
				return 'image_content';
			}

			return 'dummy';
		});

		$expected = $this->apiService->getUserAvatar('user', 'slackid1');
		$this->assertEquals([ 'displayName' => 'User' ], $expected);

		$expected = $this->apiService->getUserAvatar('user', 'slackid2');
		$this->assertEquals([ 'displayName' => 'realname' ], $expected);

		$expected = $this->apiService->getUserAvatar('user', 'slackid3');
		$this->assertEquals([ 'avatarContent' => 'image_content' ], $expected);
	}

	public function testChannelsAPI() {
		$this->networkService->method('request')->willReturnCallback(function (
			string $userId,
			string $endPoint,
			array $params,
		) {
			if ($endPoint === 'users.info') {
				if (!isset($params['user'])) {
					return [ 'error' => 'invalid request' ];
				}
				if ($params['user'] === 'U061F7PAK') {
					return [ 'error' => 'invalid user' ];
				}

				return [ 'user' => [ 'real_name' => 'realname' ] ];
			}

			if ($endPoint !== 'conversations.list') {
				return [ 'error' => 'invalid endpoint: ' . $endPoint ];
			}

			if (isset($params) && $userId === 'user') {
				return [ 'channels' => [
					[
						'id' => 'channelid1',
						'name' => 'channel1',
						'is_channel' => true,
						'is_group' => false,
						'is_im' => false,
						'is_mpim' => false,
						'updated' => 1678229664302,
						'topic' => [
							'value' => 'topic1',
							'creator' => 'U061F7AUR',
							'last_set' => 1678229664,
						],
						'purpose' => [
							'value' => 'purpose1',
							'creator' => 'U061F7AUR',
							'last_set' => 1678229664,
						],
					]
				] ];
			}

			if (isset($params) && $userId === 'user2') {
				return [ 'channels' => [
					[
						'id' => 'groupid1',
						'name' => 'group1',
						'is_channel' => false,
						'is_group' => true,
						'is_im' => false,
						'is_mpim' => false,
						'updated' => 1678229664400,
						'topic' => [
							'value' => 'topic2',
							'creator' => 'U061FSNAP',
							'last_set' => 1678229500,
						],
						'purpose' => [
							'value' => 'purpose2',
							'creator' => 'U061FSNAP',
							'last_set' => 1678229600,
						],
					],
					[
						'id' => 'U061FFLAT',
						'is_im' => true,
						'user' => 'U061FFLAT',
					],
					[
						'id' => 'U061F7PAK',
						'is_im' => true,
						'user' => 'U061F7PAK',
					],
				] ];
			}

			return [ 'error' => 'invalid request' ];
		});

		// test channels
		$expected = $this->apiService->getMyChannels('user');
		$this->assertEquals($expected, [
			[
				'id' => 'channelid1',
				'name' => 'channel1',
				'type' => 'channel',
				'updated' => 1678229664302,
			]
		]);

		// test groups and users
		$expected = $this->apiService->getMyChannels('user2');
		$this->assertEquals($expected, [
			[
				'id' => 'groupid1',
				'name' => 'topic2',
				'type' => 'group',
				'updated' => 1678229664400,
			],
			[
				'id' => 'U061FFLAT',
				'name' => 'realname',
				'type' => 'direct',
				'updated' => 0,
			],
			[
				'id' => 'U061F7PAK',
				'name' => 'U061F7PAK',
				'type' => 'direct',
				'updated' => 0,
			],
		]);
	}
}
