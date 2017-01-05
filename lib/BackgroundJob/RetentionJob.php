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
 *
 */
namespace OCA\Files_Retention\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OCA\Files_Retention\Constants;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Config\IUserMountCache;
use OCP\IDBConnection;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\IRootFolder;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;

class RetentionJob extends TimedJob {
	/** @var ISystemTagManager */
	private $tagManager;

	/** @var ISystemTagObjectMapper */
	private $tagMapper;

	/** @var IUserMountCache */
	private $userMountCache;

	/** @var IDBConnection */
	private $db;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var IJobList */
	private $jobList;

	/**
	 * RetentionJob constructor.
	 *
	 * @param ISystemTagManager $tagManager
	 * @param ISystemTagObjectMapper $tagMapper
	 * @param IUserMountCache $userMountCache
	 * @param IDBConnection $db
	 * @param IRootFolder $rootFolder
	 * @param ITimeFactory $timeFactory
	 * @param IJobList $jobList
	 */
	public function __construct(ISystemTagManager $tagManager,
								ISystemTagObjectMapper $tagMapper,
								IUserMountCache $userMountCache,
								IDBConnection $db,
								IRootFolder $rootFolder,
								ITimeFactory $timeFactory,
								IJobList $jobList) {
		// Run once a day
		$this->setInterval(24 * 60 * 60);

		$this->tagManager = $tagManager;
		$this->tagMapper = $tagMapper;
		$this->userMountCache = $userMountCache;
		$this->db = $db;
		$this->rootFolder = $rootFolder;
		$this->timeFactory = $timeFactory;
		$this->jobList = $jobList;
	}

	public function run($argument) {
		// Validate if tag still exists
		$tag = $argument['tag'];
		try {
			$this->tagManager->getTagsByIds($tag);
		} catch (\InvalidArgumentException $e) {
			// tag is invalid remove backgroundjob and exit
			$this->jobList->remove($this, $argument);
			return;
		} catch (TagNotFoundException $e) {
			// tag no longer exists remove backgroundjob and exit
			$this->jobList->remove($this, $argument);
			return;
		}

		// Validate if there is an entry in the DB
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('retention')
			->where($qb->expr()->eq('tag_id', $qb->createNamedParameter($tag)));

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			// No entry anymore in the retention db
			$this->jobList->remove($this, $argument);
			return;
		}

		// Calculate before date only once
		$deleteBefore = $this->getBeforeDate((int)$data['time_unit'], (int)$data['time_amount']);

		$offset = 0;
		$limit = 1000;
		while(true) {
			$fileids = $this->tagMapper->getObjectIdsForTags($tag, 'files', $limit, $offset);

			foreach ($fileids as $fileid) {
				try {
					$node = $this->checkFileId($fileid);
				} catch (NotFoundException $e) {
					continue;
				}

				$this->expireNode($node, $deleteBefore);
			}

			if (empty($fileids) || count($fileids) < $limit) {
				break;
			}

			$offset += $limit;
		}
	}

	/**
	 * Get a node for the given fileid.
	 *
	 * @param int $fileid
	 * @return Node
	 * @throws NotFoundException
	 */
	private function checkFileId($fileid) {
		$mountPoints = $this->userMountCache->getMountsForFileId($fileid);

		if (count($mountPoints) === 0) {
			throw new NotFoundException();
		}

		$mountPoint = $mountPoints[0];

		$userFolder = $this->rootFolder->getUserFolder($mountPoint->getUser()->getUID());

		$nodes = $userFolder->getById($fileid);
		if (count($nodes) === 0) {
			throw new NotFoundException();
		}

		return $nodes[0];
	}

	/**
	 * @param Node $node
	 * @param \DateTime $deleteBefore
	 */
	private function expireNode(Node $node, \DateTime $deleteBefore) {
		$mtime = new \DateTime();
		$mtime->setTimestamp($node->getMTime());

		if ($mtime < $deleteBefore) {
			try {
				$node->delete();
			} catch (NotPermittedException $e) {
				//LOG?
			}
		}

	}

	/**
	 * @param int $timeunit
	 * @param int $timeamount
	 * @return \DateTime
	 */
	private function getBeforeDate($timeunit, $timeamount) {
		$spec = 'P' . $timeamount;

		if ($timeunit === Constants::DAY) {
			$spec .= 'D';
		} else if ($timeunit === Constants::WEEK) {
			$spec .= 'W';
		} else if ($timeunit === Constants::MONTH) {
			$spec .= 'M';
		} else if ($timeunit === Constants::YEAR) {
			$spec .= 'Y';
		}

		$delta = new \DateInterval($spec);
		$currentDate = new \DateTime();
		$currentDate->setTimestamp($this->timeFactory->getTime());

		return $currentDate->sub($delta);
	}
}
