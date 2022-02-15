/**
 * Select element for namespaces of an object
 *
 * @constructor
 * @param {object} config Configuration options
 */
function NamespaceLookupWidget(config) {
	NamespaceLookupWidget.super.call(this, config);

	this.setValue(config.value);
	this.setProject(config.project);
}

OO.inheritClass(NamespaceLookupWidget, OO.ui.MenuTagMultiselectWidget);

NamespaceLookupWidget.prototype.setProject = function(project) {
	$.get('https://' + project + '/w/api.php', {
		action: 'query',
		meta: 'siteinfo',
		siprop: 'namespaces',
		format: 'json',
		origin: '*'
	}).then(function(re) {
		var oldValues = this.getValue();

		this.clearItems();
		this.getMenu().clearItems();

		for (var id in re.query.namespaces) {
			if (id < 0) continue; // Ignore virtual namespaces

			var info = re.query.namespaces[id];

			this.addOptions([{
				data: info.id.toString(),
				label: info['*'] || '(Article)'
			}]);
		}

		this.setValue(oldValues);

		if ($.contains(document, namespacesInput.$element[0])) {
			namespacesInput.$element.replaceWith(this.$element);
		}
	}.bind(this));
};
