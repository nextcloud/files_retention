<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_Retention\Event;

use OCP\EventDispatcher\Event;

/**
 * Event that can be emitted by apps to delete a new retention rule.
 */
class DeleteRetentionRuleEvent extends Event {

	private bool $success = false;

	public function __construct(
		public readonly int $id,
	) {
		parent::__construct();
	}

	/**
	 * @return bool false if the deletion was not successful
	 */
	public function isSuccessful(): bool {
		return $this->success;
	}

	public function setSuccess(bool $success): void {
		$this->success = $success;
	}
}
