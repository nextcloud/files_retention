<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
		private readonly IDBConnection $db,
		private readonly LoggerInterface $logger,
	) {
	}

	#[\Override]
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

		$deleted = (bool)$qb->executeStatement();

		if ($deleted) {
			$this->logger->info('Deleting retention rule for tag #' . $tag->getId() . ' because the tag is deleted');
		}
	}
}
