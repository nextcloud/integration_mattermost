<?php

namespace OCA\Mattermost\Tests;

use DateTime;
use OCA\Mattermost\AppInfo\Application;
use OCA\Mattermost\Service\MattermostAPIService;
use OCA\Mattermost\Service\NetworkService;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Security\ICrypto;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class MattermostAPIServiceTest extends TestCase {

	private MattermostAPIService $service;

	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var IL10N|MockObject */
	private $l10n;
	/** @var IConfig|MockObject */
	private $config;
	/** @var IRootFolder|MockObject */
	private $rootFolder;
	/** @var ShareManager|MockObject */
	private $shareManager;
	/** @var IURLGenerator|MockObject */
	private $urlGenerator;
	/** @var IClientService|MockObject */
	private $clientService;
	/** @var NetworkService|MockObject */
	private $networkService;

	private const MATTERMOST_URL = 'https://mattermost.example.com';
	private const MATTERMOST_USERID = 'else';

	public function setUp(): void {
		parent::setUp();

		// setup dummy objects
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->config = $this->createMock(IConfig::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->shareManager = $this->createMock(ShareManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->networkService = $this->createMock(NetworkService::class);
		$this->crypto = $this->createMock(ICrypto::class);

		$this->service = new MattermostAPIService(
			$this->logger,
			$this->l10n,
			$this->config,
			$this->rootFolder,
			$this->shareManager,
			$this->urlGenerator,
			$this->crypto,
			$this->networkService,
			$this->clientService,
		);
	}

	private static $apiData = [
		'channels' => [
			[
				'id' => '6pbxjz9mr7ddjb38hycxipxy1h',
				'create_at' => 1704715162015,
				'update_at' => 1704715162015,
				'delete_at' => 0,
				'team_id' => 'r369a9kujjyjjy16znrnmom8me',
				'type' => 'O',
				'display_name' => 'Off-Topic',
				'name' => 'off-topic',
				'header' => '',
				'purpose' => '',
				'last_post_at' => 1705306107804,
				'total_msg_count' => 2,
				'extra_update_at' => 0,
				'creator_id' => '',
				'scheme_id' => null,
				'props' => null,
				'group_constrained' => null,
				'shared' => null,
				'total_msg_count_root' => 2,
				'policy_id' => null,
				'last_root_post_at' => 1705306107804,
				'team_display_name' => 'int'
			],
			[
				'id' => 'htbhbfho3braxy1k75qhj9idey',
				'create_at' => 1705306120901,
				'update_at' => 1705306120901,
				'delete_at' => 0,
				'team_id' => '',
				'type' => 'D',
				'display_name' => 'else',
				'name' => '3xqcyc8rnbbj5pjoxhjc6je7ch__xmya6dt19bgsmfnwzum9qggdcw',
				'header' => '',
				'purpose' => '',
				'last_post_at' => 1705306156738,
				'total_msg_count' => 3,
				'extra_update_at' => 0,
				'creator_id' => '3xqcyc8rnbbj5pjoxhjc6je7ch',
				'scheme_id' => null,
				'props' => null,
				'group_constrained' => null,
				'shared' => false,
				'total_msg_count_root' => 3,
				'policy_id' => null,
				'last_root_post_at' => 1705306156738,
				'team_display_name' => null,
				'direct_message_display_name' => 'else',
				'direct_message_user_name' => 'else',
				'direct_message_user_id' => '3xqcyc8rnbbj5pjoxhjc6je7ch'
			],
			[
				'id' => 'mzgpsz4ryfn858s9n9herwkxnr',
				'create_at' => 1704715162008,
				'update_at' => 1704715162008,
				'delete_at' => 0,
				'team_id' => 'r369a9kujjyjjy16znrnmom8me',
				'type' => 'O',
				'display_name' => 'Town Square',
				'name' => 'town-square',
				'header' => '',
				'purpose' => '',
				'last_post_at' => 1705306107785,
				'total_msg_count' => 3,
				'extra_update_at' => 0,
				'creator_id' => '',
				'scheme_id' => null,
				'props' => null,
				'group_constrained' => null,
				'shared' => null,
				'total_msg_count_root' => 3,
				'policy_id' => null,
				'last_root_post_at' => 1705306107785,
				'team_display_name' => 'int'
			],
		],
		'teams' => [
			[
				'id' => 'r369a9kujjyjjy16znrnmom8me',
				'create_at' => 1704715161997,
				'update_at' => 1704715161997,
				'delete_at' => 0,
				'display_name' => 'int',
				'name' => 'int',
				'description' => '',
				'email' => 'someone@example.com',
				'type' => 'O',
				'company_name' => '',
				'allowed_domains' => '',
				'invite_id' => 'cxmzfoe7o3yn8pzdfajkgps1or',
				'allow_open_invite' => false,
				'scheme_id' => null,
				'group_constrained' => null,
				'policy_id' => null,
				'cloud_limits_archived' => false
			],
			[
				'id' => 'r369a9rakayjjy16znrnmom8me',
				'create_at' => 1704715162001,
				'update_at' => 1704715162001,
				'delete_at' => 0,
				'display_name' => 'egration',
				'name' => 'egration',
				'description' => '',
				'email' => 'egration@example.com',
				'type' => 'O',
				'company_name' => '',
				'allowed_domains' => '',
				'invite_id' => 'cxmzfoe7uyeh1pzdfajkgps1or',
				'allow_open_invite' => false,
				'scheme_id' => null,
				'group_constrained' => null,
				'policy_id' => null,
				'cloud_limits_archived' => false
			],
		],
		'posts' => [
			[
				'id' => 'd8krtr347jbx3c4kqjckadoh1e',
				'create_at' => 1704715235883,
				'update_at' => 1704715235883,
				'edit_at' => 0,
				'delete_at' => 0,
				'is_pinned' => false,
				'user_id' => 'xmya6dt19bgsmfnwzum9qggdcw',
				'channel_id' => 'mzgpsz4ryfn858s9n9herwkxnr',
				'root_id' => '',
				'original_id' => '',
				'message' => 'hello',
				'type' => '',
				'props' => [
					'disable_group_highlight' => true
				],
				'hashtags' => '',
				'pending_post_id' => '',
				'reply_count' => 0,
				'last_reply_at' => 0,
				'participants' => null,
				'metadata' => [],
			],
			[
				'id' => 'sr5nx8ra9jndfq66e8m3atw78r',
				'create_at' => 1705306124816,
				'update_at' => 1705306124816,
				'edit_at' => 0,
				'delete_at' => 0,
				'is_pinned' => false,
				'user_id' => '3xqcyc8rnbbj5pjoxhjc6je7ch',
				'channel_id' => 'htbhbfho3braxy1k75qhj9idey',
				'root_id' => '',
				'original_id' => '',
				'message' => 'hello mellow',
				'type' => '',
				'props' => [
					'disable_group_highlight' => true
				],
				'hashtags' => '',
				'pending_post_id' => '',
				'reply_count' => 0,
				'last_reply_at' => 0,
				'participants' => null,
				'metadata' => [],
			],
			[
				'id' => '5zbh7jwmyjyzpk1iuhw8mb1d8a',
				'create_at' => 1705310051563,
				'update_at' => 1705310051563,
				'edit_at' => 0,
				'delete_at' => 0,
				'is_pinned' => false,
				'user_id' => 'xmya6dt19bgsmfnwzum9qggdcw',
				'channel_id' => 'mzgpsz4ryfn858s9n9herwkxnr',
				'root_id' => '',
				'original_id' => '',
				'message' => 'hello @else, how are you doing?',
				'type' => '',
				'props' => [
					'disable_group_highlight' => true
				],
				'hashtags' => '',
				'pending_post_id' => '',
				'reply_count' => 0,
				'last_reply_at' => 0,
				'participants' => null,
				'metadata' => [],
			],
			[
				'id' => 'm8xk4o6xwfd9pd1xhs44j7adey',
				'create_at' => 1705309986249,
				'update_at' => 1705309986249,
				'edit_at' => 0,
				'delete_at' => 0,
				'is_pinned' => false,
				'user_id' => 'xmya6dt19bgsmfnwzum9qggdcw',
				'channel_id' => 'mzgpsz4ryfn858s9n9herwkxnr',
				'root_id' => '',
				'original_id' => '',
				'message' => 'hello @else ',
				'type' => '',
				'props' => [
					'disable_group_highlight' => true
				],
				'hashtags' => '',
				'pending_post_id' => '',
				'reply_count' => 0,
				'last_reply_at' => 0,
				'participants' => null,
				'metadata' => [],
			],
		],
		'users' => [
			[
				'id' => 'qrh9wyoew38o8e79j5zt88od3a',
				'create_at' => 1704706615316,
				'update_at' => 1704706615360,
				'delete_at' => 0,
				'username' => 'appsbot',
				'auth_data' => '',
				'auth_service' => '',
				'email' => 'appsbot@localhost',
				'nickname' => '',
				'first_name' => 'Mattermost Apps',
				'last_name' => '',
				'position' => '',
				'roles' => 'system_user',
				'last_picture_update' => 1704706615360,
				'locale' => 'en',
				'timezone' => [
					'automaticTimezone' => '',
					'manualTimezone' => '',
					'useAutomaticTimezone' => 'true'
				],
				'is_bot' => true,
				'bot_description' => 'Mattermost Apps Registry and API proxy.',
				'disable_welcome_email' => false
			],
			[
				'id' => 'jrcp7qshhbgjtgzpjeqt1zssny',
				'create_at' => 1704706616671,
				'update_at' => 1704706616671,
				'delete_at' => 0,
				'username' => 'boards',
				'auth_data' => '',
				'auth_service' => '',
				'email' => 'boards@localhost',
				'nickname' => '',
				'first_name' => 'Boards',
				'last_name' => '',
				'position' => '',
				'roles' => 'system_user',
				'locale' => 'en',
				'timezone' => [
					'automaticTimezone' => '',
					'manualTimezone' => '',
					'useAutomaticTimezone' => 'true'
				],
				'is_bot' => true,
				'bot_description' => 'Created by Boards plugin.',
				'disable_welcome_email' => false
			],
			[
				'id' => 'yijr8jpp7bd97rjrnc5w3zixmc',
				'create_at' => 1704706615772,
				'update_at' => 1704706615772,
				'delete_at' => 0,
				'username' => 'calls',
				'auth_data' => '',
				'auth_service' => '',
				'email' => 'calls@localhost',
				'nickname' => '',
				'first_name' => 'Calls',
				'last_name' => '',
				'position' => '',
				'roles' => 'system_user',
				'locale' => 'en',
				'timezone' => [
					'automaticTimezone' => '',
					'manualTimezone' => '',
					'useAutomaticTimezone' => 'true'
				],
				'is_bot' => true,
				'bot_description' => 'Calls Bot',
				'disable_welcome_email' => false
			],
			[
				'id' => 'tmjpcryz4igyjx8aqxw7upqhfw',
				'create_at' => 1704706615162,
				'update_at' => 1704706615162,
				'delete_at' => 0,
				'username' => 'channelexport',
				'auth_data' => '',
				'auth_service' => '',
				'email' => 'channelexport@localhost',
				'nickname' => '',
				'first_name' => 'Channel Export Bot',
				'last_name' => '',
				'position' => '',
				'roles' => 'system_user',
				'locale' => 'en',
				'timezone' => [
					'automaticTimezone' => '',
					'manualTimezone' => '',
					'useAutomaticTimezone' => 'true'
				],
				'is_bot' => true,
				'bot_description' => 'A bot account created by the channel export plugin.',
				'disable_welcome_email' => false
			],
			[
				'id' => '3xqcyc8rnbbj5pjoxhjc6je7ch',
				'create_at' => 1705306107679,
				'update_at' => 1705306108102,
				'delete_at' => 0,
				'username' => 'else',
				'auth_data' => '',
				'auth_service' => '',
				'email' => 'else@example.org',
				'nickname' => '',
				'first_name' => '',
				'last_name' => '',
				'position' => '',
				'roles' => 'system_user',
				'locale' => 'en',
				'timezone' => [
					'automaticTimezone' => 'Asia/Calcutta',
					'manualTimezone' => '',
					'useAutomaticTimezone' => 'true'
				],
				'disable_welcome_email' => false
			],
			[
				'id' => 'mabrgysqciby5gwecgjdyntarr',
				'create_at' => 1704706615172,
				'update_at' => 1704706615195,
				'delete_at' => 0,
				'username' => 'feedbackbot',
				'auth_data' => '',
				'auth_service' => '',
				'email' => 'feedbackbot@localhost',
				'nickname' => '',
				'first_name' => 'Feedbackbot',
				'last_name' => '',
				'position' => '',
				'roles' => 'system_user',
				'last_picture_update' => 1704706615195,
				'locale' => 'en',
				'timezone' => [
					'automaticTimezone' => '',
					'manualTimezone' => '',
					'useAutomaticTimezone' => 'true'
				],
				'is_bot' => true,
				'bot_description' => 'Feedbackbot collects user feedback to improve Mattermost. [Learn more](https://mattermost.com/pl/default-nps).',
				'disable_welcome_email' => false
			],
			[
				'id' => '6niit3gek7drfksusmde9j46ah',
				'create_at' => 1704706615519,
				'update_at' => 1704706615550,
				'delete_at' => 0,
				'username' => 'playbooks',
				'auth_data' => '',
				'auth_service' => '',
				'email' => 'playbooks@localhost',
				'nickname' => '',
				'first_name' => 'Playbooks',
				'last_name' => '',
				'position' => '',
				'roles' => 'system_user',
				'last_picture_update' => 1704706615550,
				'locale' => 'en',
				'timezone' => [
					'automaticTimezone' => '',
					'manualTimezone' => '',
					'useAutomaticTimezone' => 'true'
				],
				'is_bot' => true,
				'bot_description' => 'Playbooks bot.',
				'disable_welcome_email' => false
			],
			[
				'id' => 'xmya6dt19bgsmfnwzum9qggdcw',
				'create_at' => 1704715148238,
				'update_at' => 1704715162025,
				'delete_at' => 0,
				'username' => 'someone',
				'auth_data' => '',
				'auth_service' => '',
				'email' => 'someone@example.com',
				'nickname' => '',
				'first_name' => '',
				'last_name' => '',
				'position' => '',
				'roles' => 'system_admin system_user',
				'props' => [
					'last_search_pointer' => '3'
				],
				'locale' => 'en',
				'timezone' => [
					'automaticTimezone' => 'Asia/Calcutta',
					'manualTimezone' => '',
					'useAutomaticTimezone' => 'true'
				],
				'disable_welcome_email' => false,
			],
			[
				'id' => 'k3tc87yz9jbnfb8bq4dp6bttuo',
				'create_at' => 1704715200003,
				'update_at' => 1704715200003,
				'delete_at' => 0,
				'username' => 'system-bot',
				'auth_data' => '',
				'auth_service' => '',
				'email' => 'system-bot@localhost',
				'nickname' => '',
				'first_name' => 'System',
				'last_name' => '',
				'position' => '',
				'roles' => 'system_user',
				'locale' => 'en',
				'timezone' => [
					'automaticTimezone' => '',
					'manualTimezone' => '',
					'useAutomaticTimezone' => 'true',
				],
				'is_bot' => true,
				'disable_welcome_email' => false,
			],
		],
	];

	/**
	 * @param string $name Name of the resource
	 * @param string $id ID of the resource
	 * @return void
	 */
	private function getAPIData(string $name, ?string $id = null) {
		$data = static::$apiData[$name];

		if (is_null($id)) {
			return $data;
		}

		return array_values(array_filter($data, function ($d) use ($id) {
			return $d['id'] === $id;
		}))[0];
	}

	private function setupNetworkMock() {
		$this->networkService->method('request')->willReturnCallback(function ($userId, $mattermostUrl, $endPoint, $params) {
			if ($endPoint === 'users/' . static::MATTERMOST_USERID . '/teams') {
				return $this->getAPIData('teams');
			} elseif ($endPoint === 'users/' . static::MATTERMOST_USERID . '/channels') {
				return $this->getAPIData('channels');
			} elseif ($endPoint === 'posts/search') {
				if (isset($params['terms']) && str_starts_with($params['terms'], '@')) {
					return [
						'order' => [
							'5zbh7jwmyjyzpk1iuhw8mb1d8a',
							'm8xk4o6xwfd9pd1xhs44j7adey',
						],
						'posts' => [
							'5zbh7jwmyjyzpk1iuhw8mb1d8a' => $this->getAPIData('posts', '5zbh7jwmyjyzpk1iuhw8mb1d8a'),
							'm8xk4o6xwfd9pd1xhs44j7adey' => $this->getAPIData('posts', 'm8xk4o6xwfd9pd1xhs44j7adey'),
						],
						'next_post_id' => '',
						'prev_post_id' => '',
						'has_next' => false,
						'first_inaccessible_post_time' => 0,
						'matches' => null,
					];
				}

				return [
					'order' => [
						'sr5nx8ra9jndfq66e8m3atw78r',
					],
					'posts' => [
						'sr5nx8ra9jndfq66e8m3atw78r' => $this->getAPIData('posts', 'sr5nx8ra9jndfq66e8m3atw78r'),
					],
					'next_post_id' => '',
					'prev_post_id' => '',
					'has_next' => false,
					'first_inaccessible_post_time' => 0,
					'matches' => null,
				];
			} elseif (str_starts_with($endPoint, 'posts/')) {
				return $this->getAPIData('posts', substr($endPoint, strlen('posts/')));
			} elseif (str_starts_with($endPoint, 'channels/')) {
				return $this->getAPIData('channels', substr($endPoint, strlen('channels/')));
			} elseif (str_starts_with($endPoint, 'teams/')) {
				return $this->getAPIData('teams', substr($endPoint, strlen('teams/')));
			} elseif (str_starts_with($endPoint, 'users/')) {
				return $this->getAPIData('users', substr($endPoint, strlen('users/')));
			}
		});
	}

	private function setupCommonMocks(bool $mattermostTokenValid) {
		$this->config->method('getAppValue')->with(Application::APP_ID, 'oauth_instance_url')->willReturn(static::MATTERMOST_URL);
		$this->config->method('getUserValue')->willReturnCallback(function ($userId, $appId, $key) use ($mattermostTokenValid) {
			switch ($key) {
				case 'url':
					return static::MATTERMOST_URL;
				case 'user_id':
					return static::MATTERMOST_USERID;
				case 'refresh_token':
					return 'dummy_refresh_token';
				case 'token_expires_at':
					return $mattermostTokenValid ? time() + 3600 : time() - 3600;
				default:
					static::assertTrue(false, "Unexpected key $key");
			}
		});
	}

	public function testGetMyChannels() {
		$this->setupCommonMocks(true);
		$this->setupNetworkMock();

		$reponse = $this->service->getMyChannels('dummy');

		foreach ($reponse as $channel) {
			$this->assertArrayHasKey('id', $channel);
			$this->assertArrayHasKey('name', $channel);
			$this->assertArrayHasKey('display_name', $channel);
			$this->assertArrayHasKey('type', $channel);
			$this->assertArrayHasKey('team_id', $channel);
			$this->assertArrayHasKey('team_display_name', $channel);

			if ($channel['type'] === 'D') {
				$this->assertArrayHasKey('direct_message_display_name', $channel);
				$this->assertArrayHasKey('direct_message_user_name', $channel);
				$this->assertArrayHasKey('direct_message_user_id', $channel);
				$this->assertTrue($channel['direct_message_user_id'] === '3xqcyc8rnbbj5pjoxhjc6je7ch');
			} else {
				$this->assertTrue($channel['team_display_name'] === 'int');
			}
		}
	}

	public function testSearchMessages() {
		$this->setupCommonMocks(true);
		$this->setupNetworkMock();

		$reponse = $this->service->searchMessages('dummy', 'mellow');

		foreach ($reponse as $message) {
			$this->assertArrayHasKey('channel_id', $message);
			$this->assertArrayHasKey('channel_type', $message);
			$this->assertArrayHasKey('channel_name', $message);
			$this->assertArrayHasKey('channel_display_name', $message);
			$this->assertArrayHasKey('team_id', $message);
			$this->assertArrayHasKey('team_name', $message);
			$this->assertArrayHasKey('team_display_name', $message);

			$this->assertTrue($message['team_display_name'] === '');
			$this->assertTrue($message['channel_display_name'] === 'else');
			$this->assertTrue($message['user_name'] === 'else');
			$this->assertTrue($message['channel_type'] === 'D');
		}
	}

	public function testGetMentionsMe() {
		$this->setupCommonMocks(true);
		$this->setupNetworkMock();

		$reponse = $this->service->getMentionsMe('dummy', static::MATTERMOST_USERID);

		foreach ($reponse as $message) {
			$this->assertArrayHasKey('channel_id', $message);
			$this->assertArrayHasKey('channel_type', $message);
			$this->assertArrayHasKey('channel_name', $message);
			$this->assertArrayHasKey('channel_display_name', $message);
			$this->assertArrayHasKey('team_id', $message);
			$this->assertArrayHasKey('team_name', $message);
			$this->assertArrayHasKey('team_display_name', $message);

			$this->assertTrue($message['team_display_name'] === 'int');
			$this->assertTrue($message['user_name'] === 'someone');
			$this->assertTrue($message['channel_type'] === 'O');
		}
	}

	public static function apiTestBank() {
		return [
			[0, true, null, null],
			[3, false, '2024-01-01', null],
			[1, false, '2024-01-01', 'password'],
		];
	}

	/**
	 * @dataProvider apiTestBank
	 */
	public function testSendPublicLinks(int $nFiles, bool $editable, ?string $expirationDate, ?string $password) {
		$userId = 'dummy';
		$fileId = 123;
		$fileName = 'filename.txt';
		$fileIds = [$fileId];
		$channelId = 'channelId';
		$channelName = 'channelName';
		$comment = 'comment';
		$permission = $editable ? 'edit' : 'read';
		$expirationDateReceived = is_null($expirationDate) ? null : new Datetime($expirationDate);
		$password = 'password';
		$shareToken = 'token';
		$publicLink = 'https://example.com/' . $shareToken;

		$this->setupCommonMocks(true);

		$file = $this->createMock(File::class);
		$userFolder = $this->createMock(Folder::class);
		$share = $this->createMock(IShare::class);

		$this->config->method('getAppValue')->with(Application::APP_ID, 'oauth_instance_url')->willReturn(static::MATTERMOST_URL);
		$this->urlGenerator->method('linkToRoute')->with('files_sharing.Share.showShare', ['token' => $shareToken]);
		$this->urlGenerator->method('getAbsoluteURL')->willReturn($publicLink);

		$this->rootFolder->expects(static::once())->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('getById')->with($fileId)->willReturn(array_fill(0, $nFiles, $file));
		$file->method('getName')->willReturn($fileName);

		$this->shareManager->method('newShare')->willReturn($share);
		$this->shareManager->method('createShare')->willReturn($share);
		$this->shareManager->method('updateShare')->willReturn($share);
		$share->method('setPermissions')->with(
			$editable ? Constants::PERMISSION_READ | Constants::PERMISSION_UPDATE : Constants::PERMISSION_READ
		);
		$share->method('setShareType')->with(IShare::TYPE_LINK);
		$share->method('setSharedBy')->with($userId);
		$share->expects($nFiles === 0 ? static::never() : static::once())->method('setExpirationDate')->with($expirationDateReceived);
		$share->expects($password && $nFiles > 0 ? static::once() : static::never())->method('setPassword')->with($password);
		$share->method('getToken')->willReturn($shareToken);

		$this->networkService->method('request')->with($userId, $this->service->getMattermostUrl($userId), 'posts', [
			'channel_id' => $channelId,
			'message' => $comment . PHP_EOL . '```' . $fileName . '```: ' . $publicLink . PHP_EOL,
		], 'POST')->willReturn(['error' => null]);

		$result = $this->service->sendPublicLinks(
			$userId,
			$fileIds,
			$channelId,
			$channelName,
			$comment,
			$permission,
			$expirationDate,
			$password,
		);

		static::assertTrue(
			($nFiles === 0 && $result['error'])
			|| ($nFiles > 0 && !$result['error'])
		);
	}
}
