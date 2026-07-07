/**
 * SPDX-FileCopyrightText: Joas Schilling <coding@schilljs.com>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

import { createStore } from 'vuex'
import retentionStore from './retentionStore.js'

const mutations = {}

export default createStore({
	modules: {
		retentionStore,
	},

	mutations,

	// eslint-disable-next-line no-undef -- Build-time constant
	strict: process.env.NODE_ENV !== 'production',
})
