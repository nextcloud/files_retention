/**
 * SPDX-FileCopyrightText: Joas Schilling <coding@schilljs.com>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

import Vue from 'vue'
import Vuex from 'vuex'

import { generateFilePath } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'

import AdminSettings from './AdminSettings.vue'
import store from './store/index.js'

// Styles
import '@nextcloud/dialogs/style.css'

Vue.use(Vuex)

// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken())

// eslint-disable-next-line
__webpack_public_path__ = generateFilePath('files_retention', '', 'js/')

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

export default new Vue({
	el: '#files_retention',
	// eslint-disable-next-line vue/match-component-file-name
	name: 'AdminSettings',
	store,
	render: h => h(AdminSettings),
})
