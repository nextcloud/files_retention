<?php

declare(strict_types=1);
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

use OCA\Files_Retention\Constants;
use OCA\Files_Retention\EventListener;
use OCP\IDBConnection;
use OCP\SystemTag\ISystemTagManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

#[Group('DB')]
class EventListenerTest extends \Test\TestCase {
	private IDBConnection $db;
	private LoggerInterface&MockObject $logger;
	private ISystemTagManager $tagManager;

	protected function setUp(): void {
		parent::setUp();

		$this->db = \OCP\Server::get(IDBConnection::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->tagManager = \OCP\Server::get(ISystemTagManager::class);
	}

	protected function tearDown(): void {
		// Clear retention DB
		$qb = $this->db->getQueryBuilder();
		$qb->delete('retention');
		$qb->executeStatement();
	}

	private function addTag(int $tagId, int $timeunit, int $timeamount): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('retention')
			->setValue('tag_id', $qb->createNamedParameter($tagId))
			->setValue('time_unit', $qb->createNamedParameter($timeunit))
			->setValue('time_amount', $qb->createNamedParameter($timeamount));
		$qb->executeStatement();
	}

	public function testTagDeleted(): void {
		$tag = $this->tagManager->createTag(self::getUniqueID('foo'), true, true);
		$this->tagManager->deleteTags($tag->getId());
		$this->addTag((int)$tag->getId(), 1, Constants::UNIT_DAY);

		$eventListener = new EventListener($this->db, $this->logger);
		self::invokePrivate($eventListener, 'tagDeleted', [$tag]);

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('retention');
		$cursor = $qb->executeQuery();
		$data = $cursor->fetchAll();
		$cursor->closeCursor();

		$this->assertCount(0, $data);
	}
}
