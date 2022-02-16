/**
 * Lookup element for pages of a project
 * setProject must be called for lookup to start
 *
 * @constructor
 * @param {object} config Configuration options
 */
 function PageLookupWidget(config) {
	PageLookupWidget.super.call(this, config);
	OO.ui.mixin.LookupElement.call(this, config);

	this.setDomain(config.domain);
}

OO.inheritClass(PageLookupWidget, OO.ui.TextInputWidget);
OO.mixinClass(PageLookupWidget, OO.ui.mixin.LookupElement);

PageLookupWidget.prototype.getLookupRequest = function() {
	return this.domain ? $.get('https://' + this.domain + '/w/api.php', {
		action: 'query',
		generator: 'prefixsearch',
		gpssearch: this.getValue(),
		gpslimit: 10,
		origin: '*',
		format: 'json'
	}) : $.Deferred().resolve({
		query: {
			pages: []
		}
	}).promise();
};

PageLookupWidget.prototype.getLookupCacheDataFromResponse = function(res) {
	return res.query.pages || [];
};

PageLookupWidget.prototype.getLookupMenuOptionsFromData = function(data) {
	var titles = [];

	for (var id in data) {
		titles.push(data[id]);
	}

	titles.sort(function(a, b) {
		return a.index - b.index;
	});

	return titles.map(function(value) {
		return new OO.ui.MenuOptionWidget({
			data: value.title,
			label: value.title
		});
	})
};

PageLookupWidget.prototype.setDomain = function(domain) {
	this.domain = domain;
};
