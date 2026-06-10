<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_Retention\Service;

use OCA\Files_Retention\BackgroundJob\RetentionJob;
use OCA\Files_Retention\Exception\AddRuleException;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\TagNotFoundException;

class RetentionService {

	public function __construct(
		private readonly IDBConnection $db,
		private readonly ISystemTagManager $tagManager,
		private readonly IJobList $jobList,
	) {
	}

	/**
	 * @return int ID of the inserted retention rule
	 * @throws AddRuleException when a rule has an invalid value
	 */
	public function addRetention(int $tagId, int $timeUnit, int $timeAmount, int $timeAfter): int {
		try {
			$this->tagManager->getTagsByIds((string)$tagId);
		} catch (\InvalidArgumentException) {
			throw new AddRuleException('tagid');
		}

		if ($timeUnit < 0 || $timeUnit > 3) {
			throw new AddRuleException('timeunit');
		}
		if ($timeAmount < 1 || $timeAmount > 32_000) {
			throw new AddRuleException('timeamount');
		}
		if ($timeAfter < 0 || $timeAfter > 1) {
			throw new AddRuleException('timeafter');
		}

		// Fetch tag_id
		$qb = $this->db->getQueryBuilder();
		$qb->select('tag_id')
			->from('retention')
			->where($qb->expr()->eq('tag_id', $qb->createNamedParameter($tagId)))
			->setMaxResults(1);
		$cursor = $qb->executeQuery();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data !== false) {
			throw new AddRuleException('tagid');
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert('retention')
			->setValue('tag_id', $qb->createNamedParameter($tagId))
			->setValue('time_unit', $qb->createNamedParameter($timeUnit))
			->setValue('time_amount', $qb->createNamedParameter($timeAmount))
			->setValue('time_after', $qb->createNamedParameter($timeAfter));

		$qb->executeStatement();
		$id = $qb->getLastInsertId();

		//Insert cronjob
		$this->jobList->add(RetentionJob::class, ['tag' => $tagId]);

		return $id;
	}

	/**
	 * @param int $id ID of the retention rule to remove
	 * @return bool true if the retention was removed, false otherwise
	 */
	public function deleteRetention(int $id): bool {
		// Fetch tag_id
		$qb = $this->db->getQueryBuilder();
		$qb->select('tag_id')
			->from('retention')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->setMaxResults(1);
		$cursor = $qb->executeQuery();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			return false;
		}

		// Remove from retention db
		$qb = $this->db->getQueryBuilder();
		$qb->delete('retention')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$qb->executeStatement();

		// Remove cronjob
		$this->jobList->remove(RetentionJob::class, ['tag' => (int)$data['tag_id']]);

		return true;
	}

	/**
	 * @return list<array{id: int, tagid: int, timeunit: int, timeamount: int, timeafter: int, hasJob: true}>
	 */
	public function getRetentions(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('retention')
			->orderBy('id');

		$cursor = $qb->executeQuery();

		$result = $tagIds = [];
		while ($data = $cursor->fetch()) {
			$tagIds[] = (string)$data['tag_id'];
			$hasJob = $this->jobList->has(RetentionJob::class, ['tag' => (int)$data['tag_id']]);
			if (!$hasJob) {
				$this->jobList->add(RetentionJob::class, ['tag' => (int)$data['tag_id']]);
			}

			$result[] = [
				'id' => (int)$data['id'],
				'tagid' => (int)$data['tag_id'],
				'timeunit' => (int)$data['time_unit'],
				'timeamount' => (int)$data['time_amount'],
				'timeafter' => (int)$data['time_after'],
				'hasJob' => true,
			];
		}
		$cursor->closeCursor();

		try {
			$this->tagManager->getTagsByIds($tagIds);
		} catch (TagNotFoundException $e) {
			$missingTags = array_map('intval', $e->getMissingTags());

			$result = array_values(array_filter($result, static function (array $rule) use ($missingTags): bool {
				return !in_array($rule['tagid'], $missingTags, true);
			}));
		}

		return $result;
	}

}
