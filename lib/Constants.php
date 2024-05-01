<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Retention;

class Constants {
	public const DAY = 0;
	public const WEEK = 1;
	public const MONTH = 2;
	public const YEAR = 3;

	public const CTIME = 0;
	public const MTIME = 1;
}
