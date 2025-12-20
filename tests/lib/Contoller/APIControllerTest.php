<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

#[Group('DB')]
class APIControllerTest extends \Test\TestCase {
	private string $appName = 'files_retention';
	private IRequest&MockObject $request;
	private IDBConnection $db;
	private ISystemTagManager&MockObject $tagManager;
	private IJobList&MockObject $jobList;

	private APIController $api;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->db = \OCP\Server::get(IDBConnection::class);
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

	public function testAddRetentionInvalidTag(): void {
		$this->tagManager->expects($this->once())
			->method('getTagsByIds')
			->with('42')
			->will($this->throwException(new \InvalidArgumentException()));

		$response = $this->api->addRetention(42, Constants::UNIT_WEEK, 1);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testAddRetentionInvalidTimeUnit(): void {
		$response = $this->api->addRetention(42, -1, 1);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());

		$response = $this->api->addRetention(42, 4, 1);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testAddRetention(): void {
		$this->jobList->expects($this->once())
			->method('add')
			->with(RetentionJob::class, ['tag' => '42']);

		$response = $this->api->addRetention(42, Constants::UNIT_MONTH, 1);

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
			'timeunit' => Constants::UNIT_MONTH,
			'timeamount' => 1,
			'timeafter' => 0,
			'hasJob' => true,
		];
		$this->assertSame($expected, $response->getData());
	}

	public function testDeleteRetentionNotFound(): void {
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

	public static function dataGetRetentions(): array {
		return [
			[
				[]
			],
			[
				[
					['tagid' => 1, 'timeunit' => Constants::UNIT_DAY, 'timeamount' => 1, 'timeafter' => 0, 'hasJob' => true],
				]
			],
			[
				[
					['tagid' => 1, 'timeunit' => Constants::UNIT_DAY, 'timeamount' => 1, 'timeafter' => 0, 'hasJob' => true],
					['tagid' => 2, 'timeunit' => Constants::UNIT_WEEK, 'timeamount' => 2, 'timeafter' => 0, 'hasJob' => true],
					['tagid' => 3, 'timeunit' => Constants::UNIT_MONTH, 'timeamount' => 3, 'timeafter' => 1, 'hasJob' => true],
					['tagid' => 4, 'timeunit' => Constants::UNIT_YEAR, 'timeamount' => 4, 'timeafter' => 1, 'hasJob' => true],
				]
			],
			[
				[
					['tagid' => 1, 'timeunit' => Constants::UNIT_DAY, 'timeamount' => 1, 'timeafter' => 0, 'hasJob' => true],
					['tagid' => 2, 'timeunit' => Constants::UNIT_WEEK, 'timeamount' => 2, 'timeafter' => 0, 'hasJob' => true, 'expected' => false],
					['tagid' => 3, 'timeunit' => Constants::UNIT_MONTH, 'timeamount' => 3, 'timeafter' => 1, 'hasJob' => true, 'expected' => false],
					['tagid' => 4, 'timeunit' => Constants::UNIT_YEAR, 'timeamount' => 4, 'timeafter' => 1, 'hasJob' => true],
				],
				['2', '3'],
			],
		];
	}

	#[DataProvider('dataGetRetentions')]
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
