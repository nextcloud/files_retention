/**
 * SPDX-FileCopyrightText: Joas Schilling <coding@schilljs.com>
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import {
	createRetentionRule,
	deleteRetentionRule,
	getRetentionRules,
} from '../services/retentionService.js'

const state = () => ({
	retentionRules: {
	},
})

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
		state.retentionRules[rule.id] = rule
	},

	/**
	 * Deletes a rule from the store
	 *
	 * @param {object} state current store state
	 * @param {number} id the rule id of the rule to delete
	 */
	deleteRule(state, id) {
		delete state.retentionRules[id]
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
		response.data.ocs.data.forEach((rule) => {
			context.commit('addRule', rule)
		})
	},

	async deleteRetentionRule(context, ruleId) {
		await deleteRetentionRule(ruleId)
		context.commit('deleteRule', ruleId)
	},

	async createNewRule(context, rule) {
		const response = await createRetentionRule(rule)
		context.commit('addRule', response.data.ocs.data)
	},
}

export default { state, mutations, getters, actions }
