/**
 * Lookup element for project names
 *
 * @constructor
 * @param {object} config Configuration options
 */
 function ProjectLookupWidget(config) {
	ProjectLookupWidget.super.call(this, config);
	OO.ui.mixin.LookupElement.call(this, config)
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
	return data.map(function(value){
		return new OO.ui.MenuOptionWidget({
			data: value,
			label: value
		});
	});
};
