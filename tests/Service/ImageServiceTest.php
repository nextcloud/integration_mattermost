<?php

namespace OCA\Mattermost\Tests;

use OCA\Mattermost\AppInfo\Application;
use OCA\Mattermost\Service\ImageService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IPreview;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ImageServiceTest extends TestCase {

	private ImageService $service;

	/** @var IRootFolder|MockObject */
	private $rootFolder;
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var IPreview|MockObject */
	private $preview;
	/** @var IMimeTypeDetector|MockObject */
	private $mimeTypeDetector;

	public function setUp(): void {
		parent::setUp();

		// setup dummy objects
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->preview = $this->createMock(IPreview::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);

		$this->service = new ImageService(
			$this->rootFolder,
			$this->logger,
			$this->preview,
			$this->mimeTypeDetector,
		);
	}

	public function testDummy() {
		$this->assertEquals('integration_mattermost', Application::APP_ID);
	}

	public static function previewBank(): array {
		return [
			[0, false, false],
			[0, true, false],
			[1, false, true],
			[1, true, true],
			[3, true, true],
		];
	}

	/**
	 * @dataProvider previewBank
	 */
	public function testValidPreview(int $nFileMatches, bool $isMimeSupported, bool $isPreviewAvailable) {
		$userId = 'dummy';
		$fileId = 421;
		$mimeType = 'image/png';
		$mimeTypeIcon = 'somethingsomethingicon';

		$file = $this->createMock(File::class);
		$userFolder = $this->createMock(Folder::class);
		$previewFile = $this->createMock(ISimpleFile::class);

		$this->rootFolder->method('getUserFolder')->with($userId)->willReturn($userFolder);
		$userFolder->method('getById')->with($fileId)->willReturn(array_fill(0, $nFileMatches, $file));
		$file->method('getMimeType')->willReturn($mimeType);

		$this->preview->method('isMimeSupported')->with($mimeType)->willReturn($isMimeSupported);
		$this->preview->method('getPreview')->willReturn($previewFile);
		$this->mimeTypeDetector->method('mimeTypeIcon')->with($mimeType)->willReturn($mimeTypeIcon);

		$returnedFilePreview = $this->service->getFilePreviewFile($fileId, $userId);
		if ($isPreviewAvailable) {
			if ($isMimeSupported) {
				static::assertNotNull($returnedFilePreview);
				static::assertEquals('file', $returnedFilePreview['type']);
				static::assertEquals($previewFile, $returnedFilePreview['file']);
			} else {
				static::assertNotNull($returnedFilePreview);
				static::assertEquals('icon', $returnedFilePreview['type']);
				static::assertEquals($mimeTypeIcon, $returnedFilePreview['icon']);
			}
		} else {
			static::assertNull($returnedFilePreview);
		}
	}
}
