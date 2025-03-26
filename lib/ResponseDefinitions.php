<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_Retention;

/**
 * @psalm-type Files_RetentionRule = array{
 *     id: positive-int,
 *     tagid: positive-int,
 *     // 0 days, 1 weeks, 2 months, 3 years
 *     timeunit: 0|1|2|3,
 *     timeamount: positive-int,
 *     // 0 creation time, 1 modification time
 *     timeafter: 0|1,
 *     hasJob: bool,
 * }
 */
class ResponseDefinitions {
}
