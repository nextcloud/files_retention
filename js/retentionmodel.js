/* global Backbone */

/**
 * @copyright 2017, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
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

(function() {

	OCA.File_Retention = _.extend(OCA.File_Retention || {}, {
		RETENTION_UNIT_DAY: 0,
		RETENTION_UNIT_WEEK: 1,
		RETENTION_UNIT_MONTH: 2,
		RETENTION_UNIT_YEAR: 3,

		RETENTION_UNIT_MAP: {
			0:'days',
			1: 'weeks',
			2: 'months',
			3: 'years'
		}
	});

	var RetentionModel = OC.Backbone.Model.extend({
	});


	OCA.File_Retention.RetentionModel = RetentionModel;
})();
