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
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\SystemTag\ISystemTagManager;

class APIController extends Controller {

	/** @var IDBConnection */
	private $db;

	/** @var ISystemTagManager */
	private $tagManager;

	public function __construct($appName,
								IRequest $request,
								IDBConnection $db,
								ISystemTagManager $tagManager) {
		parent::__construct($appName, $request);

		$this->db = $db;
		$this->tagManager = $tagManager;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
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
				'id' => $data['id'],
				'tagid' => $data['tag_id'],
				'timeunit' => $data['time_type'],
				'timeamount' => $data['time_amount'],
			];
		}

		$cursor->closeCursor();

		return new JSONResponse($result);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
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
			->setValue('time_type', $qb->createNamedParameter($timeunit))
			->setValue('time_amount', $qb->createNamedParameter($timeamount));

		$qb->execute();

		$id = $qb->getLastInsertId();

		return new JSONResponse([
			'id' => $id,
			'tagid' => $tagid,
			'timeunit' => $timeunit,
			'timeamount' => $timeamount,
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $id
	 *
	 * @return Response
	 */
	public function deleteRetention($id) {
		$qb = $this->db->getQueryBuilder();

		$qb->delete('retention')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		$qb->execute();

		return new Response();
	}

	/**
	 * @param int $id
	 *
	 * @return Response
	 */
	public function editRetention($id) {

	}
}