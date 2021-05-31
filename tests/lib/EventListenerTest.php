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
namespace OCA\Files_Retention\Tests;

use OCA\Files_Retention\AppInfo\Application;
use OCA\Files_Retention\Constants;
use OCP\IDBConnection;
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

	protected function setUp(): void {
		parent::setUp();

		$this->db = \OC::$server->getDatabaseConnection();
		$this->dispatcher = \OC::$server->getEventDispatcher();
		$this->tagManager = \OC::$server->getSystemTagManager();

		$app = new Application();
		$app->registerEventListener();
	}

	protected function tearDown(): void {
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
