/* global Backbone */

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
