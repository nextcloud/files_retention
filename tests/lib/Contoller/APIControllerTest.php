<?php
/**
 * @copyright 2017, Roeland Jago Douma <roeland@famdouma.nl>
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
namespace OCA\Files_Retention\Tests\Controller;

use OCA\Files_Retention\BackgroundJob\RetentionJob;
use OCA\Files_Retention\Constants;
use OCA\Files_Retention\Controller\APIController;
use OCP\AppFramework\Http;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\TagNotFoundException;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class APIControllerTest
 *
 * @package OCA\Files_Retention\Tests\Controller
 * @group DB
 */
class APIControllerTest extends \Test\TestCase {

	/** @var string */
	private $appName = 'files_retention';

	/** @var IRequest|MockObject */
	private $request;

	/** @var IDBConnection */
	private $db;

	/** @var ISystemTagManager|MockObject */
	private $tagManager;

	/** @var IJobList|MockObject */
	private $jobList;

	private APIController $api;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->db = \OC::$server->getDatabaseConnection();
		$this->tagManager = $this->createMock(ISystemTagManager::class);
		$this->jobList = $this->createMock(IJobList::class);

		$this->api = new APIController(
			$this->appName,
			$this->request,
			$this->db,
			$this->tagManager,
			$this->jobList
		);
	}

	protected function tearDown(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('retention');
		$qb->executeStatement();

		parent::tearDown();
	}

	public function testAddRetentionInvalidTag() {
		$this->tagManager->expects($this->once())
			->method('getTagsByIds')
			->with('42')
			->will($this->throwException(new \InvalidArgumentException()));

		$response = $this->api->addRetention(42, Constants::WEEK, 1);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testAddRetentionInvalidTimeUnit() {
		$response = $this->api->addRetention(42, -1, 1);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());

		$response = $this->api->addRetention(42, 4, 1);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testAddRetention() {
		$this->jobList->expects($this->once())
			->method('add')
			->with(RetentionJob::class, ['tag' => '42']);

		$response = $this->api->addRetention(42, Constants::MONTH, 1);

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('retention');
		$cursor = $qb->executeQuery();
		$data = $cursor->fetchAll();
		$cursor->closeCursor();

		$this->assertCount(1, $data);
		$data = $data[0];

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());

		$expected = [
			'id' => (int)$data['id'],
			'tagid' => 42,
			'timeunit' => Constants::MONTH,
			'timeamount' => 1,
			'timeafter' => 0,
			'hasJob' => true,
		];
		$this->assertSame($expected, $response->getData());
	}

	public function testDeleteRetentionNotFound() {
		$response = $this->api->deleteRetention(42);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testDeleteRetention(): void {
		$qb = $this->db->getQueryBuilder();

		$qb->insert('retention')
			->setValue('tag_id', $qb->createNamedParameter(42))
			->setValue('time_unit', $qb->createNamedParameter(1))
			->setValue('time_amount', $qb->createNamedParameter(2));
		$qb->executeStatement();
		$id = $qb->getLastInsertId();

		$this->jobList->expects($this->once())
			->method('remove')
			->with(RetentionJob::class, ['tag' => 42]);

		$response = $this->api->deleteRetention($id);

		$this->assertSame(Http::STATUS_NO_CONTENT, $response->getStatus());
	}

	public function dataGetRetentions(): array {
		return [
			[
				[]
			],
			[
				[
					['tagid' => 1, 'timeunit' => Constants::DAY, 'timeamount' => 1, 'timeafter' => 0, 'hasJob' => true],
				]
			],
			[
				[
					['tagid' => 1, 'timeunit' => Constants::DAY, 'timeamount' => 1, 'timeafter' => 0, 'hasJob' => true],
					['tagid' => 2, 'timeunit' => Constants::WEEK, 'timeamount' => 2, 'timeafter' => 0, 'hasJob' => true],
					['tagid' => 3, 'timeunit' => Constants::MONTH, 'timeamount' => 3, 'timeafter' => 1, 'hasJob' => true],
					['tagid' => 4, 'timeunit' => Constants::YEAR, 'timeamount' => 4, 'timeafter' => 1, 'hasJob' => true],
				]
			],
			[
				[
					['tagid' => 1, 'timeunit' => Constants::DAY, 'timeamount' => 1, 'timeafter' => 0, 'hasJob' => true],
					['tagid' => 2, 'timeunit' => Constants::WEEK, 'timeamount' => 2, 'timeafter' => 0, 'hasJob' => true, 'expected' => false],
					['tagid' => 3, 'timeunit' => Constants::MONTH, 'timeamount' => 3, 'timeafter' => 1, 'hasJob' => true, 'expected' => false],
					['tagid' => 4, 'timeunit' => Constants::YEAR, 'timeamount' => 4, 'timeafter' => 1, 'hasJob' => true],
				],
				['2', '3'],
			],
		];
	}

	/**
	 * @dataProvider dataGetRetentions
	 * @param array $data
	 * @param array $missingTags
	 */
	public function testGetRetentions(array $data, array $missingTags = []): void {
		$expected = [];

		foreach ($data as $d) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('retention')
				->setValue('tag_id', $qb->createNamedParameter($d['tagid']))
				->setValue('time_unit', $qb->createNamedParameter($d['timeunit']))
				->setValue('time_amount', $qb->createNamedParameter($d['timeamount']))
				->setValue('time_after', $qb->createNamedParameter($d['timeafter']));
			$qb->executeStatement();

			$id = $qb->getLastInsertId();

			if ($d['expected'] ?? true) {
				unset($d['expected']);
				$expected[] = array_merge([
					'id' => $id,
				], $d);
			}
		}

		$this->tagManager->method('getTagsByIds')
			->willThrowException(new TagNotFoundException('', 0, null, $missingTags));

		$response = $this->api->getRetentions();

		$this->assertSame($expected, $response->getData());
	}
}
