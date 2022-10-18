/**
 * SPDX-FileCopyrightText: Joas Schilling <coding@schilljs.com>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * @param {object} rule The retention rule to add
 * @return {object} The axios response
 */
const createRetentionRule = async function(rule) {
	return axios.post(generateUrl('/apps/files_retention/api/v1/retentions'), rule)
}

/**
 * @param {number} ruleId The retention rule to delete
 * @return {object} The axios response
 */
const deleteRetentionRule = async function(ruleId) {
	return axios.delete(generateUrl('/apps/files_retention/api/v1/retentions/' + ruleId))
}

/**
 * @return {object} The axios response
 */
const getRetentionRules = async function() {
	return axios.get(generateUrl('/apps/files_retention/api/v1/retentions'))
}

export {
	createRetentionRule,
	deleteRetentionRule,
	getRetentionRules,
}
