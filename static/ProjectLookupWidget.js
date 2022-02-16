/**
 * Lookup element for project names
 *
 * @constructor
 * @param {object} config Configuration options
 */
 function ProjectLookupWidget(config) {
	ProjectLookupWidget.super.call(this, config);
	OO.ui.mixin.LookupElement.call(this, config);

	this.domain = config.domain;

	this.lookupMenu.connect(this, {
		choose: 'onProjectLookupMenuChoose'
	});
}

OO.inheritClass(ProjectLookupWidget, OO.ui.TextInputWidget);
OO.mixinClass(ProjectLookupWidget, OO.ui.mixin.LookupElement);

ProjectLookupWidget.prototype.getLookupRequest = function() {
	return $.get('projects/', {
		prefix: this.getValue()
	});
};

ProjectLookupWidget.prototype.getLookupCacheDataFromResponse = function(response) {
	return response || [];
};

ProjectLookupWidget.prototype.getLookupMenuOptionsFromData = function(data) {
	this.setDomain(data.exact);

	return data.projects.map(function(value) {
		return new OO.ui.MenuOptionWidget({
			data: value,
			label: value
		});
	});
};

ProjectLookupWidget.prototype.onProjectLookupMenuChoose = function(item) {
	this.setDomain(item.getData());
};

ProjectLookupWidget.prototype.getValue = function() {
	return this.value || 'en.wikipedia.org';
};

ProjectLookupWidget.prototype.getDomain = function() {
	return this.domain;
}

ProjectLookupWidget.prototype.setDomain = function(domain) {
	if (domain == this.domain) return;

	this.domain = domain;
	this.emit('domain', domain);
}

ProjectLookupWidget.prototype.getValidity = function() {
	var deferred = $.Deferred();

	if (this.domain) {
		deferred.resolve();
	} else {
		deferred.reject();
	}

	return deferred.promise();
}
