/* global Handlebars, moment */

(function () {

	OCA.File_Retention = OCA.File_Retention || {};

	var TEMPLATE_RETENTION =
		'<tr data-id="{{id}}">'
		+ '<td><span>{{tagName}}</span></td>'
		+ '<td><span>{{timeAmount}}</span></td>'
		+ '<td><span>{{timeUnit}}</span></td>'
		+ '<td><a class="icon-delete has-tooltip" title="' + t('files_retention', 'Delete') + '"></a></td>'
		+ '<tr>';

	var RetentionView = OC.Backbone.View.extend({
		collection: null,
		tagCollection: null,

		initialize: function(options) {
			this.collection = options.collection;
			this.tagCollection = options.tagCollection;

			var $el = $('#retention-list');
			$el.on('click', 'a.icon-delete', _.bind(this._onDeleteRetention, this));
		},

		template: function (data) {
			if (_.isUndefined(this._template)) {
				this._template = Handlebars.compile(TEMPLATE_RETENTION);
			}

			return this._template(data);
		},
		
		render: function () {
			var _this = this;
			var list = $('#retention-list');
			list.html('');

			if (this.collection.length > 0) {
				$('#retention-list-header').toggleClass('hidden', false);
			} else {
				$('#retention-list-header').toggleClass('hidden', true);
			}

			this.collection.forEach(function (model) {
				var data = {
					id: model.attributes.id,
					tagName: _this.tagCollection.get(model.attributes.tagid).attributes.name,
					timeAmount: model.attributes.timeamount,
					timeUnit: OCA.File_Retention.RETENTION_UNIT_MAP[model.attributes.timeunit]
				};
				var html = _this.template(data);
				var $html = $(html);
				list.append($html);
			});
		},

		_onDeleteRetention: function(event) {
			var $target = $(event.target);
			var $row = $target.closest('tr');
			var id = $row.data('id');

			var retention = this.collection.get(id);

			if (_.isUndefined(retention)) {
				// Ignore event
				return;
			}

			var destroyingRetention = retention.destroy();

			$row.find('.icon-delete').tooltip('hide');

			var _this = this;
			$.when(destroyingRetention).fail(function () {
				OC.Notification.showTemporary(t('files_retention', 'Error while deleting the retention rule'));
			});
			$.when(destroyingRetention).always(function () {
				_this.render();
			});
		}
	});

	OCA.File_Retention.RetentionView = RetentionView;
})();
