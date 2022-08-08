/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import Vue from 'vue'
import {
	createRetentionRule,
	deleteRetentionRule,
	getRetentionRules,
} from '../services/retentionService'

const state = {
	retentionRules: {
	},
}

const getters = {
	getRetentionRules: state => () => Object.values(state.retentionRules),
	getTagIdsWithRule: state => () => Object.values(state.retentionRules).map((rule) => rule.tagid),
}

const mutations = {
	/**
	 * Adds a rule to the store
	 *
	 * @param {object} state current store state
	 * @param {object} rule the rule
	 */
	addRule(state, rule) {
		Vue.set(state.retentionRules, rule.id, rule)
	},

	/**
	 * Deletes a rule from the store
	 *
	 * @param {object} state current store state
	 * @param {number} id the rule id of the rule to delete
	 */
	deleteRule(state, id) {
		Vue.delete(state.retentionRules, id)
	},
}

const actions = {
	/**
	 * Load the retention rules from the backend
	 *
	 * @param {object} context default store context
	 */
	async loadRetentionRules(context) {
		const response = await getRetentionRules()
		response.data.forEach((rule) => {
			context.commit('addRule', rule)
		})
	},

	async deleteRetentionRule(context, ruleId) {
		await deleteRetentionRule(ruleId)
		context.commit('deleteRule', ruleId)
	},

	async createNewRule(context, rule) {
		const response = await createRetentionRule(rule)
		context.commit('addRule', response.data)
	},
}

export default { state, mutations, getters, actions }
