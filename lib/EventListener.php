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

namespace OCA\Files_Retention;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IDBConnection;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ManagerEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class EventListener implements IEventListener {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof ManagerEvent) {
			return;
		}

		$this->tagDeleted($event->getTag());
	}

	protected function tagDeleted(ISystemTag $tag): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete('retention')
			->where($qb->expr()->eq('tag_id', $qb->createNamedParameter($tag->getId(), IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT));

		$deleted = (bool) $qb->executeStatement();

		if ($deleted) {
			$this->logger->info('Deleting retention rule for tag #' . $tag->getId() . ' because the tag is deleted');
		}
	}
}
