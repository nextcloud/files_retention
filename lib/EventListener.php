<?php

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
