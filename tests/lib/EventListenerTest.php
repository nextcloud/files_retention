<?php

namespace OCA\Files_Retention\Tests;

use OCA\Files_Retention\AppInfo\Application;
use OCA\Files_Retention\Constants;
use OCP\IDBConnection;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


/**
 * Class EventListenerTest
 *
 * @package OCA\Files_Retention\Tests
 * @group DB
 */
class EventListenerTest extends \Test\TestCase {
	/** @var IDBConnection */
	private $db;

	/** @var EventDispatcherInterface */
	private $dispatcher;

	/** @var ISystemTagManager */
	private $tagManager;

	public function setUp() {
		parent::setUp();

		$this->db = \OC::$server->getDatabaseConnection();
		$this->dispatcher = \OC::$server->getEventDispatcher();
		$this->tagManager = \OC::$server->getSystemTagManager();

		$app = new Application();
		$app->registerEventListener();
	}

	public function tearDown() {
		// Clear retention DB
		$qb = $this->db->getQueryBuilder();
		$qb->delete('retention');
		$qb->execute();
	}

	private function addTag($tagId, $timeunit, $timeamount) {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('retention')
			->setValue('tag_id', $qb->createNamedParameter($tagId))
			->setValue('time_unit', $qb->createNamedParameter($timeunit))
			->setValue('time_amount', $qb->createNamedParameter($timeamount));
		$qb->execute();
	}

	public function testTagDeleted() {
		$tag = $this->tagManager->createTag('foo', true, true);
		$this->addTag($tag->getId(), 1, Constants::DAY);
		$this->tagManager->deleteTags($tag->getId());

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('retention');
		$cursor = $qb->execute();
		$data = $cursor->fetchAll();
		$cursor->closeCursor();

		$this->assertCount(0, $data);
	}
}
