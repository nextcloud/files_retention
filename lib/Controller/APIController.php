<?php
/**
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
 */

namespace OCA\Files_Retention\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\SystemTag\ISystemTagManager;

class APIController extends Controller {

	/** @var IDBConnection */
	private $db;

	/** @var ISystemTagManager */
	private $tagManager;

	/** @var IJobList */
	private $joblist;

	/**
	 * APIController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IDBConnection $db
	 * @param ISystemTagManager $tagManager
	 * @param IJobList $jobList
	 */
	public function __construct($appName,
								IRequest $request,
								IDBConnection $db,
								ISystemTagManager $tagManager,
								IJobList $jobList) {
		parent::__construct($appName, $request);

		$this->db = $db;
		$this->tagManager = $tagManager;
		$this->joblist = $jobList;
	}

	/**
	 * @return JSONResponse
	 */
	public function getRetentions() {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('retention')
			->orderBy('id');

		$cursor = $qb->execute();

		$result = [];

		while($data = $cursor->fetch()) {
			$result[] = [
				'id' => (int)$data['id'],
				'tagid' => (int)$data['tag_id'],
				'timeunit' => (int)$data['time_unit'],
				'timeamount' => (int)$data['time_amount'],
			];
		}

		$cursor->closeCursor();

		return new JSONResponse($result);
	}

	/**
	 * @param int $tagid
	 * @param int $timeunit
	 * @param int $timeamount
	 *
	 * @return Response
	 */
	public function addRetention($tagid, $timeunit, $timeamount) {
		$response = new Response();

		try {
			$this->tagManager->getTagsByIds($tagid);
		} catch (\InvalidArgumentException $e) {
			$response->setStatus(Http::STATUS_BAD_REQUEST);
			return $response;
		}

		if ($timeunit < 0 || $timeunit > 3 || $timeamount < 1) {
			$response->setStatus(Http::STATUS_BAD_REQUEST);
			return $response;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert('retention')
			->setValue('tag_id', $qb->createNamedParameter($tagid))
			->setValue('time_unit', $qb->createNamedParameter($timeunit))
			->setValue('time_amount', $qb->createNamedParameter($timeamount));

		$qb->execute();
		$id = $qb->getLastInsertId();

		//Insert cronjob
		$this->joblist->add('OCA\Files_Retention\BackgroundJob\RetentionJob', ['tag' => $tagid]);

		return new JSONResponse([
			'id' => $id,
			'tagid' => $tagid,
			'timeunit' => $timeunit,
			'timeamount' => $timeamount,
		], Http::STATUS_CREATED);
	}

	/**
	 * @param int $id
	 *
	 * @return Response
	 */
	public function deleteRetention($id) {
		$qb = $this->db->getQueryBuilder();

		// Fetch tag_id
		$qb->select('tag_id')
			->from('retention')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->setMaxResults(1);
		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			return new Http\NotFoundResponse();
		}

		// Remove from retention db
		$qb = $this->db->getQueryBuilder();
		$qb->delete('retention')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$qb->execute();

		// Remove cronjob
		$this->joblist->remove('OCA\Files_Retention\BackgroundJob\RetentionJob', ['tag' => $data['tag_id']]);

		$response = new Response();
		$response->setStatus(Http::STATUS_NO_CONTENT);
		return $response;
	}

	/**
	 * @param int $id
	 * @param int|null $timeunit
	 * @param int|null $timeamount
	 *
	 * @return Response
	 */
	public function editRetention($id, $timeunit = null, $timeamount = null) {
		if (($timeunit === null && $timeamount === null) ||
			($timeunit !== null && ($timeunit < 0 || $timeunit > 3)) ||
			($timeamount !== null && $timeamount < 1)) {
			$response = new Response();
			$response->setStatus(Http::STATUS_BAD_REQUEST);
			return $response;
		}

		$qb = $this->db->getQueryBuilder();

		// Fetch tag_id
		$qb->select('tag_id')
			->from('retention')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->setMaxResults(1);
		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			return new Http\NotFoundResponse();
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update('retention');

		if ($timeunit !== null) {
			$qb->set('time_unit', $qb->createNamedParameter($timeunit));
		}
		if ($timeamount !== null) {
			$qb->set('time_amount', $qb->createNamedParameter($timeamount));
		}
		$qb->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$qb->execute();

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('retention')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->setMaxResults(1);
		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		return new JSONResponse([
			'id' => $id,
			'tagid' => (int)$data['tag_id'],
			'timeunit' => (int)$data['time_unit'],
			'timeamount' => (int)$data['time_amount'],
		]);
	}
}
