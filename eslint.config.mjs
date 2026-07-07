/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommended } from '@nextcloud/eslint-config'

export default [
	...recommended,
	{
		rules: {
			// TODO: Re-enable after migration to transpile-only TS build (or migrating from Webpack)
			'import-extensions/extensions': 'off',
		},
	},
]
