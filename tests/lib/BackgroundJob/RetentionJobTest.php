<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Retention\Tests\BackgroundJob;

use OCA\Files_Retention\BackgroundJob\RetentionJob;
use OCA\Files_Retention\Constants;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Config\ICachedMountFileInfo;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\Notification\IManager;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[Group('DB')]
class RetentionJobTest extends TestCase {
	private ISystemTagManager&MockObject $tagManager;
	private ISystemTagObjectMapper&MockObject $tagMapper;
	private IUserMountCache&MockObject $userMountCache;
	private IDBConnection $db;
	private IRootFolder&MockObject $rootFolder;
	private ITimeFactory&MockObject $timeFactory;
	private IJobList&MockObject $jobList;
	private RetentionJob $retentionJob;
	private int $timestampbase;

	protected function setUp(): void {
		parent::setUp();

		$this->timestampbase = 1000000000;

		$this->tagManager = $this->createMock(ISystemTagManager::class);
		$this->tagMapper = $this->createMock(ISystemTagObjectMapper::class);
		$this->userMountCache = $this->createMock(IUserMountCache::class);
		$this->db = \OCP\Server::get(IDBConnection::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->jobList = $this->createMock(IJobList::class);

		$this->timeFactory->method('getTime')->willReturn($this->timestampbase);

		$this->retentionJob = new RetentionJob(
			$this->timeFactory,
			$this->tagManager,
			$this->tagMapper,
			$this->userMountCache,
			$this->db,
			$this->rootFolder,
			$this->jobList,
			$this->createMock(LoggerInterface::class),
			$this->createMock(IManager::class),
			$this->createMock(IConfig::class)
		);
	}

	protected function tearDown(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('retention');
		$qb->executeStatement();

		parent::tearDown();
	}

	private function addTag(int $tagId, int $timeunit, int $timeamount, int $timeafter = 0): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('retention')
			->setValue('tag_id', $qb->createNamedParameter($tagId))
			->setValue('time_unit', $qb->createNamedParameter($timeunit))
			->setValue('time_amount', $qb->createNamedParameter($timeamount))
			->setValue('time_after', $qb->createNamedParameter($timeafter));
		$qb->executeStatement();
	}

	public static function dataDeleteTest(): array {
		return [
			[[1, Constants::UNIT_DAY],   [0, Constants::UNIT_DAY], false, Constants::MODE_CTIME],
			[[2, Constants::UNIT_WEEK],  [0, Constants::UNIT_DAY], false, Constants::MODE_CTIME],
			[[3, Constants::UNIT_MONTH], [0, Constants::UNIT_DAY], false, Constants::MODE_MTIME],
			[[4, Constants::UNIT_YEAR],  [0, Constants::UNIT_DAY], false, Constants::MODE_MTIME],

			[[1, Constants::UNIT_DAY],   [2, Constants::UNIT_DAY], true, Constants::MODE_CTIME],
			[[2, Constants::UNIT_WEEK],  [2, Constants::UNIT_DAY], false, Constants::MODE_CTIME],
			[[3, Constants::UNIT_MONTH], [2, Constants::UNIT_DAY], false, Constants::MODE_MTIME],
			[[4, Constants::UNIT_YEAR],  [2, Constants::UNIT_DAY], false, Constants::MODE_MTIME],

			[[1, Constants::UNIT_DAY],   [21, Constants::UNIT_DAY], true, Constants::MODE_CTIME],
			[[2, Constants::UNIT_WEEK],  [21, Constants::UNIT_DAY], true, Constants::MODE_CTIME],
			[[3, Constants::UNIT_MONTH], [21, Constants::UNIT_DAY], false, Constants::MODE_MTIME],
			[[4, Constants::UNIT_YEAR],  [21, Constants::UNIT_DAY], false, Constants::MODE_MTIME],

			[[1, Constants::UNIT_DAY],   [180, Constants::UNIT_DAY], true, Constants::MODE_CTIME],
			[[2, Constants::UNIT_WEEK],  [180, Constants::UNIT_DAY], true, Constants::MODE_CTIME],
			[[3, Constants::UNIT_MONTH], [180, Constants::UNIT_DAY], true, Constants::MODE_MTIME],
			[[4, Constants::UNIT_YEAR],  [180, Constants::UNIT_DAY], false, Constants::MODE_MTIME],

			[[1, Constants::UNIT_DAY],   [10000, Constants::UNIT_DAY], true, Constants::MODE_CTIME],
			[[2, Constants::UNIT_WEEK],  [10000, Constants::UNIT_DAY], true, Constants::MODE_CTIME],
			[[3, Constants::UNIT_MONTH], [10000, Constants::UNIT_DAY], true, Constants::MODE_MTIME],
			[[4, Constants::UNIT_YEAR],  [10000, Constants::UNIT_DAY], true, Constants::MODE_MTIME],

			[[2, Constants::UNIT_WEEK],  ['mtime' => 10, 'ctime' => 10], false, Constants::MODE_CTIME],
			[[2, Constants::UNIT_WEEK],  ['mtime' => 10, 'ctime' => 16], true, Constants::MODE_CTIME],
			[[2, Constants::UNIT_WEEK],  ['mtime' => 16, 'ctime' => 16], true, Constants::MODE_CTIME],
			[[2, Constants::UNIT_WEEK],  ['mtime' => 10, 'ctime' => 10], false, Constants::MODE_MTIME],
			[[2, Constants::UNIT_WEEK],  ['mtime' => 10, 'ctime' => 16], false, Constants::MODE_MTIME],
			[[2, Constants::UNIT_WEEK],  ['mtime' => 16, 'ctime' => 16], true, Constants::MODE_MTIME],
		];
	}

	#[DataProvider('dataDeleteTest')]
	public function testDeleteFile(array $retentionTime, array $fileTime, bool $delete, int $after): void {
		$this->addTag(42, $retentionTime[1], $retentionTime[0], $after);

		$this->tagMapper->expects($this->once())
			->method('getObjectIdsForTags')
			->with(42, 'files')
			->willReturn([1337]);

		$mountPoint = $this->createMock(ICachedMountFileInfo::class);
		$this->userMountCache->expects($this->once())
			->method('getMountsForFileId')
			->with(1337)
			->willReturn([$mountPoint]);

		$user = $this->createMock(IUser::class);
		$mountPoint->method('getUser')
			->willReturn($user);

		$user->method('getUID')
			->willReturn('admin');

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with('admin')
			->willReturn($userFolder);

		$node = $this->createMock(Node::class);
		$userFolder->expects($this->once())
			->method('getById')
			->with(1337)
			->willReturn([$node]);

		if (isset($fileTime['mtime'])) {
			$delta = new \DateInterval('P' . $fileTime['mtime'] . 'D');
			$now = new \DateTime();
			$now->setTimestamp($this->timestampbase);
			$mtime = $now->sub($delta);

			$delta = new \DateInterval('P' . $fileTime['ctime'] . 'D');
			$now = new \DateTime();
			$now->setTimestamp($this->timestampbase);
			$ctime = $now->sub($delta);
		} else {
			$delta = new \DateInterval('P' . $fileTime[0] . 'D');
			$now = new \DateTime();
			$now->setTimestamp($this->timestampbase);
			$mtime = $ctime = $now->sub($delta);
		}

		$node->method('getMTime')
			->willReturn($mtime->getTimestamp());

		$node->method('getUploadTime')
			->willReturn($ctime->getTimestamp());

		$node->method('isDeletable')
			->willReturn(true);

		if ($delete) {
			$node->expects($this->once())
				->method('delete');
		} else {
			$node->expects($this->never())
				->method('delete');
		}

		$this->retentionJob->run(['tag' => 42]);
	}

	public function testInvalidTag(): void {
		$this->tagManager->expects($this->once())
			->method('getTagsByIds')
			->will($this->throwException(new \InvalidArgumentException()));

		$this->jobList->expects($this->once())
			->method('remove')
			->with($this->equalTo($this->retentionJob), $this->equalTo(['tag' => 42]));

		$this->retentionJob->run(['tag' => 42]);
	}

	public function testNoSuchTag(): void {
		$this->tagManager->expects($this->once())
			->method('getTagsByIds')
			->will($this->throwException(new TagNotFoundException()));

		$this->jobList->expects($this->once())
			->method('remove')
			->with($this->equalTo($this->retentionJob), $this->equalTo(['tag' => 42]));

		$this->retentionJob->run(['tag' => 42]);
	}

	public function testNoSuchRetention(): void {
		// Tag exists
		$this->tagManager->expects($this->once())
			->method('getTagsByIds');

		$this->jobList->expects($this->once())
			->method('remove')
			->with($this->equalTo($this->retentionJob), $this->equalTo(['tag' => 42]));

		$this->retentionJob->run(['tag' => 42]);
	}

	public function testCantDelete(): void {
		$this->addTag(42, 1, Constants::UNIT_DAY);

		$this->tagMapper->expects($this->once())
			->method('getObjectIdsForTags')
			->with(42, 'files')
			->willReturn([1337]);

		$mountPoint = $this->createMock(ICachedMountFileInfo::class);
		$this->userMountCache->expects($this->once())
			->method('getMountsForFileId')
			->with(1337)
			->willReturn([$mountPoint]);

		$user = $this->createMock(IUser::class);
		$mountPoint->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$user->expects($this->once())
			->method('getUID')
			->willReturn('user');

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user')
			->willReturn($userFolder);

		$node = $this->createMock(Node::class);
		$userFolder->expects($this->once())
			->method('getById')
			->with(1337)
			->willReturn([$node]);

		$delta = new \DateInterval('P' . 2 . 'D');
		$now = new \DateTime();
		$now->setTimestamp($this->timestampbase);
		$mtime = $now->sub($delta);

		$node->expects($this->once())
			->method('getMTime')
			->willReturn($mtime->getTimestamp());

		$node->expects($this->once())
			->method('delete')
			->will($this->throwException(new NotPermittedException()));

		$node->method('isDeletable')
			->willReturn(true);

		$this->retentionJob->run(['tag' => 42]);
	}

	public function testNoDeletePermissions(): void {
		$this->addTag(42, 1, Constants::UNIT_DAY);

		$this->tagMapper->expects($this->once())
			->method('getObjectIdsForTags')
			->with(42, 'files')
			->willReturn([1337]);

		$mountPoint = $this->createMock(ICachedMountFileInfo::class);
		$this->userMountCache->expects($this->once())
			->method('getMountsForFileId')
			->with(1337)
			->willReturn([$mountPoint]);

		$user = $this->createMock(IUser::class);
		$mountPoint->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$user->expects($this->once())
			->method('getUID')
			->willReturn('user');

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user')
			->willReturn($userFolder);

		$node = $this->createMock(Node::class);
		$userFolder->expects($this->once())
			->method('getById')
			->with(1337)
			->willReturn([$node]);

		$node->method('isDeletable')
			->willReturn(false);

		$this->retentionJob->run(['tag' => 42]);
	}

	public function testNoDeletePermissionsOnFirstMountPointButOnSecond(): void {
		$this->addTag(42, 1, Constants::UNIT_DAY);

		$this->tagMapper->expects($this->once())
			->method('getObjectIdsForTags')
			->with(42, 'files')
			->willReturn([1337]);

		$mountPoint1 = $this->createMock(ICachedMountFileInfo::class);
		$mountPoint2 = $this->createMock(ICachedMountFileInfo::class);
		$this->userMountCache->expects($this->once())
			->method('getMountsForFileId')
			->with(1337)
			->willReturn([$mountPoint1, $mountPoint2]);

		$user1 = $this->createMock(IUser::class);
		$user2 = $this->createMock(IUser::class);
		$mountPoint1->expects($this->once())
			->method('getUser')
			->willReturn($user1);
		$mountPoint2->expects($this->once())
			->method('getUser')
			->willReturn($user2);

		$user1->expects($this->once())
			->method('getUID')
			->willReturn('user1');
		$user2->expects($this->once())
			->method('getUID')
			->willReturn('user2');

		$userFolder1 = $this->createMock(Folder::class);
		$userFolder2 = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->willReturnMap([
				['user1', $userFolder1],
				['user2', $userFolder2],
			]);

		$node1 = $this->createMock(Node::class);
		$node2 = $this->createMock(Node::class);
		$userFolder1->expects($this->once())
			->method('getById')
			->with(1337)
			->willReturn([$node1]);
		$userFolder2->expects($this->once())
			->method('getById')
			->with(1337)
			->willReturn([$node2]);

		$node1->method('isDeletable')
			->willReturn(false);

		$delta = new \DateInterval('P2D');
		$now = new \DateTime();
		$now->setTimestamp($this->timestampbase);
		$mtime = $now->sub($delta);

		$node2->expects($this->once())
			->method('getMTime')
			->willReturn($mtime->getTimestamp());

		$node2->expects($this->once())
			->method('delete')
			->will($this->throwException(new NotPermittedException()));

		$node2->method('isDeletable')
			->willReturn(true);

		$node2->expects($this->once())
			->method('delete');

		$this->retentionJob->run(['tag' => 42]);
	}

	public function testNoMountPoint(): void {
		$this->addTag(42, 1, Constants::UNIT_DAY);

		$this->tagMapper->expects($this->once())
			->method('getObjectIdsForTags')
			->with(42, 'files')
			->willReturn([1337]);

		$this->userMountCache->expects($this->once())
			->method('getMountsForFileId')
			->with(1337)
			->willReturn([]);

		$this->retentionJob->run(['tag' => 42]);
	}

	public function testNoFileIds(): void {
		$this->addTag(42, 1, Constants::UNIT_DAY);

		$this->tagMapper->expects($this->once())
			->method('getObjectIdsForTags')
			->with(42, 'files')
			->willReturn([1337]);

		$mountPoint = $this->createMock(ICachedMountFileInfo::class);
		$this->userMountCache->expects($this->once())
			->method('getMountsForFileId')
			->with(1337)
			->willReturn([$mountPoint]);

		$user = $this->createMock(IUser::class);
		$mountPoint->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$user->expects($this->once())
			->method('getUID')
			->willReturn('user');

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user')
			->willReturn($userFolder);

		$userFolder->expects($this->once())
			->method('getById')
			->with(1337)
			->willReturn([]);

		$this->retentionJob->run(['tag' => 42]);
	}

	public function testsPagination(): void {
		$this->addTag(42, 1, Constants::UNIT_DAY);

		$withConsecutive = [
			[
				'args' => ['42', 'files', 1000, ''],
				'return' => array_fill(0, 1000, 1337),
			],
			[
				'args' => ['42', 'files', 1000, '1337'],
				'return' => [],
			],
		];

		$i = 0;
		$this->tagMapper->expects($this->exactly(count($withConsecutive)))
			->method('getObjectIdsForTags')
			->willReturnCallback(function () use ($withConsecutive, &$i) {
				$this->assertArrayHasKey($i, $withConsecutive);
				$this->assertSame($withConsecutive[$i]['args'], func_get_args());
				$return = $withConsecutive[$i]['return'];
				$i++;
				return $return;
			});

		$this->userMountCache->expects($this->exactly(1000))
			->method('getMountsForFileId')
			->with(1337)
			->willReturn([]);

		$this->retentionJob->run(['tag' => 42]);
	}
}
