<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Retention\BackgroundJob;

use Exception;
use OC\Files\Filesystem;
use OCA\Files_Retention\AppInfo\Application;
use OCA\Files_Retention\Constants;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\Config\ICachedMountFileInfo;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Notification\IManager as NotificationManager;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use Psr\Log\LoggerInterface;

class RetentionJob extends TimedJob {
	public function __construct(
		ITimeFactory $timeFactory,
		private readonly ISystemTagManager $tagManager,
		private readonly ISystemTagObjectMapper $tagMapper,
		private readonly IUserMountCache $userMountCache,
		private readonly IDBConnection $db,
		private readonly IRootFolder $rootFolder,
		private readonly IJobList $jobList,
		private readonly LoggerInterface $logger,
		private readonly NotificationManager $notificationManager,
		private readonly IConfig $config,
	) {
		parent::__construct($timeFactory);
		// Run once a day
		$this->setInterval(24 * 60 * 60);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	#[\Override]
	public function run($argument): void {
		// Validate if tag still exists
		$tag = $argument['tag'];
		try {
			$this->tagManager->getTagsByIds((string)$tag);
		} catch (\InvalidArgumentException $e) {
			// tag is invalid remove backgroundjob and exit
			$this->jobList->remove($this, $argument);
			$this->logger->debug("Background job was removed, because tag $tag is invalid", [
				'exception' => $e,
			]);
			return;
		} catch (TagNotFoundException $e) {
			// tag no longer exists remove backgroundjob and exit
			$this->jobList->remove($this, $argument);
			$this->logger->debug("Background job was removed, because tag $tag no longer exists", [
				'exception' => $e,
			]);
			return;
		}

		// Validate if there is an entry in the DB
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('retention')
			->where($qb->expr()->eq('tag_id', $qb->createNamedParameter($tag)));

		$cursor = $qb->executeQuery();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			// No entry anymore in the retention db
			$this->jobList->remove($this, $argument);
			$this->logger->debug("Background job was removed, because tag $tag has no retention configured");
			return;
		}

		// Do we notify the user before
		$notifyDayBefore = $this->config->getAppValue(Application::APP_ID, 'notify_before', 'no') === 'yes';

		// Calculate before date only once
		$deleteBefore = $this->getBeforeDate((int)$data['time_unit'], (int)$data['time_amount']);
		$notifyBefore = $this->getNotifyBeforeDate($deleteBefore);

		if ($notifyDayBefore) {
			$this->logger->debug("Running retention for Tag $tag with delete before " . $deleteBefore->format(\DateTimeInterface::ATOM) . ' and notify before ' . $notifyBefore->format(\DateTimeInterface::ATOM));
		} else {
			$this->logger->debug("Running retention for Tag $tag with delete before " . $deleteBefore->format(\DateTimeInterface::ATOM));
		}

		$timeAfter = (int)$data['time_after'];

		$offset = '';
		$limit = 1000;
		while ($offset !== null) {
			$fileIds = $this->tagMapper->getObjectIdsForTags((string)$tag, 'files', $limit, $offset);
			$this->logger->debug('Checking retention for ' . count($fileIds) . ' files in this chunk');

			foreach ($fileIds as $fileId) {
				$fileId = (int)$fileId;
				try {
					$node = $this->checkFileId($fileId);
				} catch (NotFoundException $e) {
					$this->logger->debug("Node with id $fileId was not found", [
						'exception' => $e,
					]);
					continue;
				}

				$deleted = $this->expireNode($node, $deleteBefore, $timeAfter);

				if ($notifyDayBefore && !$deleted) {
					$this->notifyNode($node, $notifyBefore, $timeAfter);
				}
			}

			if (empty($fileIds) || count($fileIds) < $limit) {
				break;
			}

			$offset = (string)array_pop($fileIds);
		}
	}

	/**
	 * Get a node for the given fileid.
	 *
	 * @param int $fileId
	 * @return Node
	 * @throws NotFoundException
	 */
	private function checkFileId(int $fileId): Node {
		$mountPoints = $this->userMountCache->getMountsForFileId($fileId);

		if (empty($mountPoints)) {
			throw new NotFoundException("No mount points found for file $fileId");
		}

		foreach ($mountPoints as $mountPoint) {
			try {
				return $this->getDeletableNodeFromMountPoint($mountPoint, $fileId);
			} catch (NotPermittedException $e) {
				// Check the next mount point
				$this->logger->debug('Mount point ' . ($mountPoint->getMountId() ?? 'null') . ' has no delete permissions for file ' . $fileId);
			} catch (NotFoundException $e) {
				// Already logged explicitly inside
			}
		}

		throw new NotFoundException("No mount point with delete permissions found for file $fileId");
	}

	protected function getDeletableNodeFromMountPoint(ICachedMountFileInfo $mountPoint, int $fileId): Node {
		try {
			$userId = $mountPoint->getUser()->getUID();
			$userFolder = $this->rootFolder->getUserFolder($userId);
			if (!Filesystem::$loaded) {
				// Filesystem wasn't loaded for anyone,
				// so we boot it up in order to make hooks in the View work.
				Filesystem::init($userId, '/' . $userId . '/files');
			}
		} catch (Exception $e) {
			$this->logger->debug($e->getMessage(), [
				'exception' => $e,
			]);
			throw new NotFoundException('Could not get user', 0, $e);
		}

		$nodes = $userFolder->getById($fileId);
		if (empty($nodes)) {
			throw new NotFoundException('No node for file ' . $fileId . ' and user ' . $userId);
		}

		foreach ($nodes as $node) {
			if ($node->isDeletable()) {
				return $node;
			}
			$this->logger->debug('Mount point ' . ($mountPoint->getMountId() ?? 'null') . ' has access to node ' . $node->getId() . ' but permissions are ' . $node->getPermissions());
		}

		throw new NotPermittedException();
	}

	protected function getDateFromNode(Node $node, int $timeAfter): \DateTime {
		$time = new \DateTime();

		// Fallback is the mtime
		$time->setTimestamp($node->getMTime());

		// Use the upload time if we have it
		if ($timeAfter === Constants::MODE_CTIME && $node->getUploadTime() !== 0) {
			$time->setTimestamp($node->getUploadTime());
		} elseif ($timeAfter === Constants::MODE_MTIME && $node->getMTime() < $node->getUploadTime()) {
			// Use the upload time if it's newer than the modification time
			$time->setTimestamp($node->getUploadTime());
			$this->logger->debug('Upload time of file ' . $node->getId() . ' is newer than modification time, continuing with that');
		}

		return $time;
	}

	private function expireNode(Node $node, \DateTime $deleteBefore, int $timeAfter): bool {
		$time = $this->getDateFromNode($node, $timeAfter);

		if ($time < $deleteBefore) {
			$this->logger->debug('Expiring file ' . $node->getId());
			try {
				$node->delete();
				return true;
			} catch (Exception $e) {
				$this->logger->debug($e->getMessage(), [
					'exception' => $e,
				]);
			}
		} else {
			$this->logger->debug('Skipping file ' . $node->getId() . ' from expiration');
		}

		return false;
	}

	private function notifyNode(Node $node, \DateTime $notifyBefore, int $timeAfter): void {
		$time = $this->getDateFromNode($node, $timeAfter);

		if ($time < $notifyBefore) {
			$this->logger->debug('Notifying about retention tomorrow for file ' . $node->getId());
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
			} catch (Exception $e) {
				$this->logger->error($e->getMessage(), [
					'exception' => $e,
				]);
			}
		}
	}

	private function getBeforeDate(int $timeunit, int $timeAmount): \DateTime {
		$spec = 'P' . $timeAmount;

		if ($timeunit === Constants::UNIT_DAY) {
			$spec .= 'D';
		} elseif ($timeunit === Constants::UNIT_WEEK) {
			$spec .= 'W';
		} elseif ($timeunit === Constants::UNIT_MONTH) {
			$spec .= 'M';
		} elseif ($timeunit === Constants::UNIT_YEAR) {
			$spec .= 'Y';
		}

		$delta = new \DateInterval($spec);
		$currentDate = new \DateTime();
		$currentDate->setTimestamp($this->time->getTime());

		return $currentDate->sub($delta);
	}

	private function getNotifyBeforeDate(\DateTime $retentionDate): \DateTime {
		$spec = 'P1D';

		$delta = new \DateInterval($spec);
		$retentionDate = clone $retentionDate;
		return $retentionDate->add($delta);
	}
}
