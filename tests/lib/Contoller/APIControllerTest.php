<?php
/**
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
 */

namespace OCA\Files_Retention\Tests\Controller;

use OCA\Files_Retention\Constants;
use OCA\Files_Retention\Controller\APIController;
use OCP\AppFramework\Http;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\SystemTag\ISystemTagManager;

/**
 * Class APIControllerTest
 *
 * @package OCA\Files_Retention\Tests\Controller
 * @group DB
 */
class APIControllerTest extends \Test\TestCase {

	/** @var string */
	private $appName = 'files_retention';

	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;

	/** @var IDBConnection */
	private $db;

	/** @var ISystemTagManager|\PHPUnit_Framework_MockObject_MockObject */
	private $tagManager;

	/** @var IJobList|\PHPUnit_Framework_MockObject_MockObject */
	private $jobList;

	/** @var APIController */
	private $api;

	public function setUp() {
		parent::setUp();

		$this->request = $this->getMockBuilder('OCP\IRequest')
			->disableOriginalConstructor()->getMock();
		$this->db = \OC::$server->getDatabaseConnection();
		$this->getMockBuilder('OCP\IRequest')
			->disableOriginalConstructor()->getMock();
		$this->tagManager = $this->getMockBuilder('OCP\SystemTag\ISystemTagManager')
			->disableOriginalConstructor()->getMock();
		$this->jobList = $this->getMockBuilder('OCP\BackgroundJob\IJobList')
			->disableOriginalConstructor()->getMock();

		$this->api = new APIController(
			$this->appName,
			$this->request,
			$this->db,
			$this->tagManager,
			$this->jobList
		);
	}

	public function tearDown() {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('retention');
		$qb->execute();

		parent::tearDown();
	}

	public function testAddRetentionInvalidTag() {
		$this->tagManager->expects($this->once())
			->method('getTagsByIds')
			->with(42)
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
			->with('OCA\Files_Retention\BackgroundJob\RetentionJob', ['tag' => 42]);

		$response = $this->api->addRetention(42, Constants::MONTH, 1);
		$this->assertInstanceOf('OCP\AppFramework\Http\JSONResponse', $response);
		/** @var Http\JSONResponse $response */

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('retention');
		$cursor = $qb->execute();
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
		];
		$this->assertSame($expected, $response->getData());
	}

	public function testDeleteRetentionNotFound() {
		$response = $this->api->deleteRetention(42);

		$this->assertInstanceOf('OCP\AppFramework\Http\NotFoundResponse', $response);
	}

	public function testDeleteRetention() {
		$qb = $this->db->getQueryBuilder();

		$qb->insert('retention')
			->setValue('tag_id', $qb->createNamedParameter(42))
			->setValue('time_unit', $qb->createNamedParameter(1))
			->setValue('time_amount', $qb->createNamedParameter(2));
		$qb->execute();
		$id = $qb->getLastInsertId();

		$this->jobList->expects($this->once())
			->method('remove')
			->with('OCA\Files_Retention\BackgroundJob\RetentionJob', ['tag' => 42]);

		$response = $this->api->deleteRetention($id);

		$this->assertSame(Http::STATUS_NO_CONTENT, $response->getStatus());
	}

	public function dataGetRetentions() {
		return [
			[
				[]
			],
			[
				[
					[1, Constants::DAY, 1],
				]
			],
			[
				[
					[1, Constants::DAY, 1],
					[2, Constants::WEEK, 2],
					[3, Constants::MONTH, 3],
					[4, Constants::YEAR, 4],
				]
			],
		];
	}

	/**
	 * @dataProvider dataGetRetentions
	 * @param array $data
	 */
	public function testGetRetentions($data) {

		$expected = [];

		foreach ($data as $d) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('retention')
				->setValue('tag_id', $qb->createNamedParameter($d[0]))
				->setValue('time_unit', $qb->createNamedParameter($d[1]))
				->setValue('time_amount', $qb->createNamedParameter($d[2]));
			$qb->execute();

			$id = $qb->getLastInsertId();

			$expected[] = [
				'id' => $id,
				'tagid' => $d[0],
				'timeunit' => $d[1],
				'timeamount' => $d[2],
			];
		}

		$response = $this->api->getRetentions();

		$this->assertInstanceOf('OCP\AppFramework\Http\JSONResponse', $response);
		$this->assertSame($expected, $response->getData());
	}

	public function dataEditRetentionBadRequest() {
		return [
			[null, null],
			[null, 0],
			[Constants::DAY, 0],
			[-1, null],
			[4, null],
			[-1, 0],
		];
	}

	/**
	 * @dataProvider dataEditRetentionBadRequest
	 * @param int|null $timeunit
	 * @param int|null $timeamount
	 */
	public function testEditRetentionBadRequest($timeunit, $timeamount) {
		$response = $this->api->editRetention(42, $timeunit, $timeamount);

		$this->assertInstanceOf('OCP\AppFramework\Http\Response', $response);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testEditRetentionNoRetetion() {
		$response = $this->api->editRetention(42, Constants::DAY, 6);

		$this->assertInstanceOf('OCP\AppFramework\Http\NotFoundResponse', $response);
	}

	public function dataEditRetention() {
		return [
			[Constants::MONTH, null],
			[null, 2],
			[Constants::YEAR, 10],
		];
	}

	/**
	 * @dataProvider dataEditRetention
	 * @param int|null $timeunit
	 * @param int|null $timeamount
	 */
	public function testEditRetention($timeunit, $timeamount) {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('retention')
			->setValue('tag_id', $qb->createNamedParameter(42))
			->setValue('time_unit', $qb->createNamedParameter(Constants::DAY))
			->setValue('time_amount', $qb->createNamedParameter(1));
		$qb->execute();

		$id = $qb->getLastInsertId();

		$expected = [
			'id' => $id,
			'tagid' => 42,
			'timeunit' => $timeunit === null ? Constants::DAY : $timeunit,
			'timeamount' => $timeamount === null ? 1 : $timeamount,
		];

		$response = $this->api->editRetention($id, $timeunit, $timeamount);

		$this->assertInstanceOf('OCP\AppFramework\Http\JSONResponse', $response);
		/** @var Http\JSONResponse $response */
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($expected, $response->getData());
	}
}
