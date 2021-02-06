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
namespace OCA\Files_Retention\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OC\Files\Filesystem;
use OCA\Files_Retention\AppInfo\Application;
use OCA\Files_Retention\Constants;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Config\IUserMountCache;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\IRootFolder;
use OCP\ILogger;
use OCP\Notification\IManager as NotificationManager;
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

	/** @var ILogger */
	private $logger;

	/** @var NotificationManager */
	private $notificationManager;
	/** @var IConfig */
	private $config;

	public function __construct(ISystemTagManager $tagManager,
								ISystemTagObjectMapper $tagMapper,
								IUserMountCache $userMountCache,
								IDBConnection $db,
								IRootFolder $rootFolder,
								ITimeFactory $timeFactory,
								IJobList $jobList,
								ILogger $logger,
								NotificationManager $notificationManager,
								IConfig $config) {
		// Run once a day
		$this->setInterval(24 * 60 * 60);

		$this->tagManager = $tagManager;
		$this->tagMapper = $tagMapper;
		$this->userMountCache = $userMountCache;
		$this->db = $db;
		$this->rootFolder = $rootFolder;
		$this->timeFactory = $timeFactory;
		$this->jobList = $jobList;
		$this->logger = $logger;
		$this->notificationManager = $notificationManager;
		$this->config = $config;
	}

	public function run($argument) {
		// Validate if tag still exists
		$tag = $argument['tag'];
		try {
			$this->tagManager->getTagsByIds($tag);
		} catch (\InvalidArgumentException $e) {
			// tag is invalid remove backgroundjob and exit
			$this->jobList->remove($this, $argument);
			$this->logger->logException($e, ['message' => "Background job was removed, because tag $tag is invalid", 'level' => ILogger::DEBUG]);
			return;
		} catch (TagNotFoundException $e) {
			// tag no longer exists remove backgroundjob and exit
			$this->jobList->remove($this, $argument);
			$this->logger->logException($e, ['message' => "Background job was removed, because tag $tag no longer exists", 'level' => ILogger::DEBUG]);
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

		// Do we notify the user before
		$notifyDayBefore = $this->config->getAppValue(Application::APP_ID, 'notify_before', 'no') === 'yes';

		// Calculate before date only once
		$deleteBefore = $this->getBeforeDate((int)$data['time_unit'], (int)$data['time_amount']);
		$notifyBefore = $this->getNotifyBeforeDate($deleteBefore);

		$timeAfter = (int)$data['time_after'];

		$offset = '';
		$limit = 1000;
		while ($offset !== null) {
			$fileids = $this->tagMapper->getObjectIdsForTags($tag, 'files', $limit, $offset);

			foreach ($fileids as $fileid) {
				try {
					$node = $this->checkFileId($fileid);
				} catch (NotFoundException $e) {
					$this->logger->logException($e, ['message' => "Node with id $fileid was not found", 'level' => ILogger::DEBUG]);
					continue;
				}

				$deleted = $this->expireNode($node, $deleteBefore, $timeAfter);

				if ($notifyDayBefore && !$deleted) {
					$this->notifyNode($node, $notifyBefore);
				}
			}

			if (empty($fileids) || count($fileids) < $limit) {
				break;
			}

			$offset = array_pop($fileids);
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

		if (empty($mountPoints)) {
			throw new NotFoundException();
		}

		$mountPoint = array_shift($mountPoints);

		try {
			$userId = $mountPoint->getUser()->getUID();
			$userFolder = $this->rootFolder->getUserFolder($userId);
			if (!Filesystem::$loaded) {
				// Filesystem wasn't loaded for anyone,
				// so we boot it up in order to make hooks in the View work.
				Filesystem::init($userId, '/' . $userId . '/files');
			}
		} catch (\Exception $e) {
			$this->logger->logException($e, ['level' => ILogger::DEBUG]);
			throw new NotFoundException('Could not get user');
		}

		$nodes = $userFolder->getById($fileid);
		if (empty($nodes)) {
			throw new NotFoundException();
		}

		return array_shift($nodes);
	}

	/**
	 * @param Node $node
	 * @param \DateTime $deleteBefore
	 */
	private function expireNode(Node $node, \DateTime $deleteBefore, int $timeAfter) {
		$mtime = new \DateTime();

		// Fallback is the mtime
		$mtime->setTimestamp($node->getMTime());

		// Use the upload time if we have it
		if ($timeAfter === Constants::CTIME && $node->getUploadTime() !== 0) {
			$mtime->setTimestamp($node->getUploadTime());
		}

		if ($mtime < $deleteBefore) {
			try {
				$node->delete();
				return true;
			} catch (NotPermittedException $e) {
				//LOG?
			}
		}

		return false;
	}

	private function notifyNode(Node $node, \DateTime $notifyBefore) {
		$mtime = new \DateTime();

		// Fallback is the mtime
		$mtime->setTimestamp($node->getMTime());

		// Use the upload time if we have it
		if ($node->getUploadTime() !== 0) {
			$mtime->setTimestamp($node->getUploadTime());
		}

		if ($mtime < $notifyBefore) {
			try {
				$notification = $this->notificationManager->createNotification();
				$notification->setApp(Application::APP_ID)
					->setUser($node->getOwner()->getUID())
					->setDateTime(new \DateTime())
					->setObject('retention', (string)$node->getId())
					->setSubject('deleteTomorrow', [
						'fileId' => $node->getId(),
					]);

				$this->notificationManager->notify($notification);
			} catch (\Exception $e) {
				$this->logger->logException($e);
			}
		}
	}

	/**
	 * @param int $timeunit
	 * @param int $timeamount
	 * @return \DateTime
	 */
	private function getBeforeDate(int $timeunit, int $timeamount): \DateTime {
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

	private function getNotifyBeforeDate(\DateTime $retentionDate): \DateTime {
		$spec = 'P1D';

		$delta = new \DateInterval($spec);
		$retentionDate = clone $retentionDate;
		return $retentionDate->add($delta);
	}
}
