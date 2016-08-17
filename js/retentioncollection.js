/* global Backbone */

(function() {

	OCA.File_Retention = OCA.File_Retention || {};

	var RetentionCollection = OC.Backbone.Collection.extend({
		model: OCA.File_Retention.RetentionModel,

		url: OC.generateUrl('/apps/files_retention/api/v1/retentions')

	});

	OCA.File_Retention.RetentionCollection = RetentionCollection;
})();
