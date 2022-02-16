/**
 * Select element for namespaces of an object
 *
 * @constructor
 * @param {object} config Configuration options
 */
 function NamespaceLookupWidget(config) {
	config.allowArbitrary = true;
	NamespaceLookupWidget.super.call(this, config);

	this.setValue(config.value);
	this.setDomain(config.domain);
}

OO.inheritClass(NamespaceLookupWidget, OO.ui.MenuTagMultiselectWidget);

NamespaceLookupWidget.prototype.clearMenu = function() {
	var oldValues = this.getValue();

	this.clearItems();
	this.getMenu().clearItems();

	this.allowArbitrary = true;
	this.setValue(oldValues);
}

NamespaceLookupWidget.prototype.setDomain = function(domain) {
	if (!domain) {
		this.clearMenu();
		return;
	}

	return $.get('https://' + domain + '/w/api.php', {
		action: 'query',
		meta: 'siteinfo',
		siprop: 'namespaces',
		format: 'json',
		origin: '*',
		formatversion: 2
	}).then(function(res) {
		var oldValues = this.getValue(),
			options = [];

		this.clearItems();
		this.getMenu().clearItems();

		for (var id in res.query.namespaces) {
			if (id < 0) continue; // Ignore virtual namespaces

			var info = res.query.namespaces[id];

			options.push({
				data: info.id.toString(),
				label: info.name || '(Article)'
			});
		}

		this.allowArbitrary = false;

		this.addOptions(options);
		this.setValue(oldValues);
	}.bind(this), this.clearMenu.bind(this));
}

NamespaceLookupWidget.prototype.isAllowedData = function(data) {
	return NamespaceLookupWidget.super.prototype.isAllowedData.call(this, data) && /^-?[0-9]+$/.test(data);
}
