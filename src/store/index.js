/**
 * SPDX-FileCopyrightText: Joas Schilling <coding@schilljs.com>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

import Vue from 'vue'
import Vuex, { Store } from 'vuex'
import retentionStore from './retentionStore.js'

Vue.use(Vuex)

const mutations = {}

export default new Store({
	modules: {
		retentionStore,
	},

	mutations,

	strict: process.env.NODE_ENV !== 'production',
})
