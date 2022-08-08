/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
