<?php
/**
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Files_Retention\Tests\BackgroundJob\RententionJobTest;

use OCA\Files_Retention\BackgroundJob\RetentionJob;
use OCA\Files_Retention\Constants;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Config\IUserMountCache;
use OCP\IDBConnection;
use OCP\Files\IRootFolder;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;

/**
 * @group DB
 */
class RetentionJobTest extends \Test\TestCase {

	/** @var ISystemTagManager|\PHPUnit_Framework_MockObject_MockObject */
	private $tagManager;

	/** @var ISystemTagObjectMapper|\PHPUnit_Framework_MockObject_MockObject */
	private $tagMapper;

	/** @var IUserMountCache|\PHPUnit_Framework_MockObject_MockObject */
	private $userMountCache;

	/** @var IDBConnection */
	private $db;

	/** @var IRootFolder|\PHPUnit_Framework_MockObject_MockObject */
	private $rootFolder;

	/** @var ITimeFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $timeFactory;

	/** @var RetentionJob */
	private $retentionJob;

	/** @var int */
	private $timestampbase;

	public function setUp() {
		 parent::setUp();

		$this->timestampbase = 1000000000;

		$this->tagManager = $this->getMockBuilder('OCP\SystemTag\ISystemTagManager')
			->disableOriginalConstructor()->getMock();
		$this->tagMapper = $this->getMockBuilder('OCP\SystemTag\ISystemTagObjectMapper')
			->disableOriginalConstructor()->getMock();
		$this->userMountCache = $this->getMockBuilder('OCP\Files\Config\IUserMountCache')
			->disableOriginalConstructor()->getMock();
		$this->db = \OC::$server->getDatabaseConnection();
		$this->rootFolder = $this->getMockBuilder('OCP\Files\IRootFolder')
			->disableOriginalConstructor()->getMock();
		$this->timeFactory = $this->getMockBuilder('OCP\AppFramework\Utility\ITimeFactory')
			->disableOriginalConstructor()->getMock();

		$this->timeFactory->method('getTime')->willReturn($this->timestampbase);

		$this->retentionJob = new RetentionJob(
			$this->tagManager,
			$this->tagMapper,
			$this->userMountCache,
			$this->db,
			$this->rootFolder,
			$this->timeFactory
		);
	}

	public function tearDown() {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('retention');
		$qb->execute();

		parent::tearDown();
	}

	private function addTag($tagId, $timeunit, $timeamount) {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('retention')
			->setValue('tag_id', $qb->createNamedParameter($tagId))
			->setValue('time_unit', $qb->createNamedParameter($timeunit))
			->setValue('time_amount', $qb->createNamedParameter($timeamount));
		$qb->execute();
	}

	public function deleteTestCases() {
		return [
			[[1, Constants::DAY],   [0, Constants::DAY], false],
			[[2, Constants::WEEK],  [0, Constants::DAY], false],
			[[3, Constants::MONTH], [0, Constants::DAY], false],
			[[4, Constants::YEAR],  [0, Constants::DAY], false],

			[[1, Constants::DAY],   [2, Constants::DAY], true],
			[[2, Constants::WEEK],  [2, Constants::DAY], false],
			[[3, Constants::MONTH], [2, Constants::DAY], false],
			[[4, Constants::YEAR],  [2, Constants::DAY], false],

			[[1, Constants::DAY],   [21, Constants::DAY], true],
			[[2, Constants::WEEK],  [21, Constants::DAY], true],
			[[3, Constants::MONTH], [21, Constants::DAY], false],
			[[4, Constants::YEAR],  [21, Constants::DAY], false],

			[[1, Constants::DAY],   [180, Constants::DAY], true],
			[[2, Constants::WEEK],  [180, Constants::DAY], true],
			[[3, Constants::MONTH], [180, Constants::DAY], true],
			[[4, Constants::YEAR],  [180, Constants::DAY], false],

			[[1, Constants::DAY],   [10000, Constants::DAY], true],
			[[2, Constants::WEEK],  [10000, Constants::DAY], true],
			[[3, Constants::MONTH], [10000, Constants::DAY], true],
			[[4, Constants::YEAR],  [10000, Constants::DAY], true],
		];
	}

	/**
	 * @dataProvider deleteTestCases
	 *
	 * @param array $retentionTime
	 * @param array $fileTime
	 * @param array $delete
	 */
	public function testDeleteFile($retentionTime, $fileTime, $delete) {
		$this->addTag(42, $retentionTime[1], $retentionTime[0]);

		$this->tagMapper->expects($this->once())
			->method('getObjectIdsForTags')
			->with(42, 'files')
			->willReturn([1337]);

		$mountPoint = $this->getMockBuilder('OCP\Files\Config\ICachedMountInfo')
			->disableOriginalConstructor()->getMock();
		$this->userMountCache->expects($this->once())
			->method('getMountsForFileId')
			->with(1337)
			->willReturn([$mountPoint]);

		$user = $this->getMockBuilder('OCP\IUser')
			->disableOriginalConstructor()->getMock();
		$mountPoint->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$user->expects($this->once())
			->method('getUID')
			->willReturn('user');

		$userFolder = $this->getMockBuilder('OCP\Files\Folder')
			->disableOriginalConstructor()->getMock();
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user')
			->willReturn($userFolder);

		$node = $this->getMockBuilder('OCP\Files\Node')
			->disableOriginalConstructor()->getMock();
		$userFolder->expects($this->once())
			->method('getById')
			->with(1337)
			->willReturn([$node]);

		$delta = new \DateInterval('P' . $fileTime[0] . 'D');
		$now = new \DateTime();
		$now->setTimestamp($this->timestampbase);
		$mtime = $now->sub($delta);

		$node->expects($this->once())
			->method('getMTime')
			->willReturn($mtime->getTimestamp());

		if ($delete) {
			$node->expects($this->once())
				->method('delete');
		} else {
			$node->expects($this->never())
				->method('delete');
		}

		$this->retentionJob->run(['tag' => 42]);
	}

}