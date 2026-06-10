<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_Retention\Event;

use OCA\Files_Retention\Constants;
use OCP\EventDispatcher\Event;

/**
 * Event that can be emitted by apps to register a new retention rule.
 */
class AddRetentionRuleEvent extends Event {

	private ?int $id = null;

	/**
	 * @param int $tagid Tag the retention is based on
	 * @param int $timeunit Time unit of the retention (0 = days, 1 = weeks, 2 = months, 3 = years)
	 * @psalm-param Constants::UNIT_* $timeunit
	 * @param positive-int $timeamount Amount of time units that have to be passed
	 * @param int $timeafter Whether retention time is based creation time (0) or modification time (1)
	 * @psalm-param Constants::MODE_* $timeafter
	 */
	public function __construct(
		public readonly int $tagId,
		public readonly int $timeUnit,
		public readonly int $timeAmount,
		public readonly int $timeAfter,
	) {
		parent::__construct();
	}

	/**
	 * @return ?int the ID of the retention rule if created, null otherwise
	 */
	public function getId(): ?int {
		return $this->id;
	}

	public function setId(int $id): void {
		$this->id = $id;
	}

}
