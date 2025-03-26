<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Retention\Controller;

use OCA\Files_Retention\BackgroundJob\RetentionJob;
use OCA\Files_Retention\Constants;
use OCA\Files_Retention\ResponseDefinitions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\TagNotFoundException;

/**
 * @psalm-import-type Files_RetentionRule from ResponseDefinitions
 */
class APIController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IDBConnection $db,
		private readonly ISystemTagManager $tagManager,
		private readonly IJobList $jobList,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List retention rules
	 *
	 * @return DataResponse<Http::STATUS_OK, list<Files_RetentionRule>, array{}>
	 *
	 * 200: List retention rules
	 */
	public function getRetentions(): DataResponse {
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

		return new DataResponse($result);
	}

	/**
	 * Create a retention rule
	 *
	 * @param int $tagid Tag the retention is based on
	 * @param 0|1|2|3 $timeunit Time unit of the retention (days, weeks, months, years)
	 * @psalm-param Constants::UNIT_* $timeunit
	 * @param positive-int $timeamount Amount of time units that have to be passed
	 * @param 0|1 $timeafter Whether retention time is based creation time (0) or modification time (1)
	 * @psalm-param Constants::MODE_* $timeafter
	 * @return DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'tagid'|'timeunit'|'timeamount'|'timeafter'}, array{}>|DataResponse<Http::STATUS_CREATED, Files_RetentionRule, array{}>
	 *
	 * 201: Retention rule created
	 * 400: At least one of the parameters was invalid
	 */
	public function addRetention(int $tagid, int $timeunit, int $timeamount, int $timeafter = Constants::MODE_CTIME): DataResponse {
		try {
			$this->tagManager->getTagsByIds((string)$tagid);
		} catch (\InvalidArgumentException) {
			return new DataResponse(['error' => 'tagid'], Http::STATUS_BAD_REQUEST);
		}

		if ($timeunit < 0 || $timeunit > 3) {
			return new DataResponse(['error' => 'timeunit'], Http::STATUS_BAD_REQUEST);
		}
		if ($timeamount < 1) {
			return new DataResponse(['error' => 'timeamount'], Http::STATUS_BAD_REQUEST);
		}
		if ($timeafter < 0 || $timeafter > 1) {
			return new DataResponse(['error' => 'timeafter'], Http::STATUS_BAD_REQUEST);
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert('retention')
			->setValue('tag_id', $qb->createNamedParameter($tagid))
			->setValue('time_unit', $qb->createNamedParameter($timeunit))
			->setValue('time_amount', $qb->createNamedParameter($timeamount))
			->setValue('time_after', $qb->createNamedParameter($timeafter));

		$qb->executeStatement();
		$id = $qb->getLastInsertId();

		//Insert cronjob
		$this->jobList->add(RetentionJob::class, ['tag' => $tagid]);

		return new DataResponse([
			'id' => $id,
			'tagid' => $tagid,
			'timeunit' => $timeunit,
			'timeamount' => $timeamount,
			'timeafter' => $timeafter,
			'hasJob' => true,
		], Http::STATUS_CREATED);
	}

	/**
	 * Delete a retention rule
	 *
	 * @param int $id Retention rule to delete
	 * @return DataResponse<Http::STATUS_NO_CONTENT|Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 204: Retention rule deleted
	 * 404: Retention rule not found
	 */
	public function deleteRetention(int $id): DataResponse {
		$qb = $this->db->getQueryBuilder();

		// Fetch tag_id
		$qb->select('tag_id')
			->from('retention')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->setMaxResults(1);
		$cursor = $qb->executeQuery();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		// Remove from retention db
		$qb = $this->db->getQueryBuilder();
		$qb->delete('retention')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$qb->executeStatement();

		// Remove cronjob
		$this->jobList->remove(RetentionJob::class, ['tag' => (int)$data['tag_id']]);

		return new DataResponse([], Http::STATUS_NO_CONTENT);
	}
}
