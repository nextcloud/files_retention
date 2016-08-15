(function() {
	if (!OCA.File_Retention) {
		/**
		 * @namespace
		 */
		OCA.File_Retention = {};
	}

	OCA.File_Retention.Admin = {

		tagCollection: null,

		collection: null,
		view: null,

		currentTagId: null,

		init: function() {
			var self = this;

			this.tagCollection = OC.SystemTags.collection;
			var loadingTags = this.tagCollection.fetch({
				success: function() {
					$('#retention_tag').select2(_.extend(self.select2));
				}
			});

			this.collection = new OCA.File_Retention.RetentionCollection();
			var loadingRetentions = this.collection.fetch();

			$.when(loadingTags, loadingRetentions).done(function () {
				self.view = new OCA.File_Retention.RetentionView({
					collection: self.collection,
					tagCollection: self.tagCollection
				});

				self.view.render();
			});

			$('#retention_submit').on('click', _.bind(this._onClickSubmit, this));
		},

		/**
		 * Selecting a systemtag in select2
		 *
		 * @param {OC.SystemTags.SystemTagModel} tag
		 */
		onSelectTag: function (tag) {
			this.currentTagId = parseInt(tag.id, 10);

			$('#retention_submit').prop('disabled', false);
		},

		/**
		 * Clicking the Create button
		 */
		_onClickSubmit: function () {
			var $el = $('#retention_amount');
			var amount = $el.val();
			if (!/^\d+$/.test(amount)) {
				$el.tooltip({
						title: t('files_retention', 'Not a number'),
						placement: 'bottom',
						trigger: 'manual'
					})
					.tooltip('show');
				return;
			}
			$el.tooltip('hide');

			var unit = parseInt($('#retention_unit').val(), 10);
			amount = parseInt(amount, 10);

			if (isNaN(amount)) {
				amount = 10;
			}

			this.collection.create({
				tagid: this.currentTagId,
				timeunit: unit,
				timeamount: amount
			});

			$('#retention_submit').prop('disabled', true);
			$('#retention_tag').select2('val', '');
			this.view.render();
		},

		/**
		 * Select2 options for the SystemTag dropdown
		 */
		select2: {
			allowClear: false,
			multiple: false,
			placeholder: t('files_retention', 'Select tag…'),
			query: _.debounce(function(query) {
				// Filter tag list by search
				var tags = OCA.File_Retention.Admin.tagCollection.filterByName(query.term);

				// Get all the tags already in used for retention rules
				var usedTagIds = OCA.File_Retention.Admin.collection.map(function(retention) {
					return retention.attributes.tagid;
				});

				// Filter tag list by tags already in use
				tags = tags.filter(function(tag) {
					return $.inArray(parseInt(tag.id, 10), usedTagIds) === -1;
				});

				query.callback({
					results: tags
				});
			}, 100, true),
			id: function(element) {
				return element;
			},
			initSelection: function(element, callback) {
				var selection = ($(element).val() || []).split('|').sort();
				callback(selection);
			},
			formatResult: function (tag) {
				return OC.SystemTags.getDescriptiveTag(tag);
			},
			formatSelection: function (tag) {
				OCA.File_Retention.Admin.onSelectTag(tag);
				return OC.SystemTags.getDescriptiveTag(tag);
			},
			escapeMarkup: function(m) {
				return m;
			}
		}
	};
})();

$(document).ready(function() {
	OCA.File_Retention.Admin.init();
});

