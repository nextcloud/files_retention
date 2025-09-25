/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import camelCase from 'camelcase'

import type { DAVResultResponseProps } from 'webdav'

import type { BaseTag, TagWithId } from './types.js'

export const defaultBaseTag: BaseTag = {
	userVisible: true,
	userAssignable: true,
	canAssign: true,
}

export const parseTags = (tags: { props: DAVResultResponseProps }[]): TagWithId[] => {
	return tags.map(({ props }) => Object.fromEntries(
		Object.entries(props)
			.map(([key, value]) => [camelCase(key), camelCase(key) === 'displayName' ? String(value) : value]),
	)) as TagWithId[]
}
