<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'ocs' => [
		['name' => 'API#getRetentions', 'url' => '/api/v1/retentions', 'verb' => 'GET'],
		['name' => 'API#addRetention', 'url' => '/api/v1/retentions', 'verb' => 'POST'],
		['name' => 'API#deleteRetention', 'url' => '/api/v1/retentions/{id}', 'verb' => 'DELETE'],
	],
];
