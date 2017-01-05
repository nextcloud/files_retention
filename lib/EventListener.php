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

namespace OCA\Files_Retention;

use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\SystemTag\ISystemTag;

class EventListener {
	/** @var IDBConnection */
	private $db;

	/** @var IJobList */
	private $jobList;

	public function __construct(
		IDBConnection $db,
		IJobList $jobList
	) {
		$this->db = $db;
		$this->jobList = $jobList;
	}

	public function tagDeleted(ISystemTag $tag) {
		$qb = $this->db->getQueryBuilder();

		$qb->delete('retention')
			->where($qb->expr()->eq('tag_id', $qb->createNamedParameter($tag->getId())));

		$qb->execute();
	}
}
