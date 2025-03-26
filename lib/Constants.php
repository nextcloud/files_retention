<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Retention;

class Constants {
	public const UNIT_DAY = 0;
	public const UNIT_WEEK = 1;
	public const UNIT_MONTH = 2;
	public const UNIT_YEAR = 3;

	public const MODE_CTIME = 0;
	public const MODE_MTIME = 1;
}
