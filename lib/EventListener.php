<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_Retention;

use OCA\Files_Retention\Event\AddRetentionRuleEvent;
use OCA\Files_Retention\Event\DeleteRetentionRuleEvent;
use OCA\Files_Retention\Service\RetentionService;
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
		private readonly RetentionService $retentionService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof ManagerEvent) {
			$this->tagDeleted($event->getTag());
		}

		if ($event instanceof AddRetentionRuleEvent) {
			$id = $this->retentionService->addRetention($event->tagId, $event->timeUnit, $event->timeAmount, $event->timeAfter);
			$event->setId($id);
		}

		if ($event instanceof DeleteRetentionRuleEvent) {
			$this->retentionService->deleteRetention($event->id);
			$event->setSuccess(true);
		}
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
