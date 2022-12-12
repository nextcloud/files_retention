<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
 * @copyright Copyright (c) 2017 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Joas Schilling <coding@schilljs.com>
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
namespace OCA\Files_Retention\Controller;

use OCA\Files_Retention\BackgroundJob\RetentionJob;
use OCA\Files_Retention\Constants;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\TagNotFoundException;

class APIController extends OCSController {
	private IDBConnection $db;
	private ISystemTagManager $tagManager;
	private IJobList $jobList;

	public function __construct(string $appName,
								IRequest $request,
								IDBConnection $db,
								ISystemTagManager $tagManager,
								IJobList $jobList) {
		parent::__construct($appName, $request);

		$this->db = $db;
		$this->tagManager = $tagManager;
		$this->jobList = $jobList;
	}

	public function getRetentions(): DataResponse {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('retention')
			->orderBy('id');

		$cursor = $qb->executeQuery();

		$result = $tagIds = [];
		while ($data = $cursor->fetch()) {
			$tagIds[] = (string) $data['tag_id'];
			$hasJob = $this->jobList->has(RetentionJob::class, ['tag' => (int)$data['tag_id']]);

			$result[] = [
				'id' => (int)$data['id'],
				'tagid' => (int)$data['tag_id'],
				'timeunit' => (int)$data['time_unit'],
				'timeamount' => (int)$data['time_amount'],
				'timeafter' => (int)$data['time_after'],
				'hasJob' => $hasJob,
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

	public function addRetention(int $tagid, int $timeunit, int $timeamount, int $timeafter = Constants::CTIME): DataResponse {
		try {
			$this->tagManager->getTagsByIds((string) $tagid);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($timeunit < 0 || $timeunit > 3 || $timeamount < 1 || $timeafter < 0 || $timeafter > 1) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
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
		$qb->execute();

		// Remove cronjob
		$this->jobList->remove(RetentionJob::class, ['tag' => (int)$data['tag_id']]);

		return new DataResponse([], Http::STATUS_NO_CONTENT);
	}
}
