var projectLookup = OO.ui.infuse($('#project')),
	pageLookup = OO.ui.infuse($('#page')),
	namespacesInput = OO.ui.infuse($('#namespaces')),
	button = OO.ui.infuse($('#submit')),
	namespacesSelect = new OO.ui.MenuTagMultiselectWidget({
		allowArbitrary: false
	}),
	progressWidget = new OO.ui.ProgressBarWidget(),
	progressLayout = new OO.ui.FieldLayout(progressWidget, {
		align: 'top'
	}),
	outWidget = OO.ui.infuse($('#out')),
	outLayout = OO.ui.infuse($('#out-layout')),
	nsQueue = namespacesInput.getValue() ? namespacesInput.getValue().split(',') : [],
	request = undefined;

function getNamespaceOptions(project) {
	$.get('https://' + project + '/w/api.php', {
		action: 'query',
		meta: 'siteinfo',
		siprop: 'namespaces',
		format: 'json',
		origin: '*'
	}).then(function(re) {
		var oldValues = $.merge(namespacesSelect.getValue(), nsQueue || []);

		nsQueue = undefined;
		namespacesSelect.clearItems().getMenu().clearItems();

		for (var id in re.query.namespaces) {
			if (id < 0) continue; // Ignore virtual namespaces

			var info = re.query.namespaces[id];

			namespacesSelect.addOptions([{
				data: info.id.toString(),
				label: info['*'] || '(Article)'
			}]);
		}

		namespacesSelect.setValue(oldValues);

		if ($.contains(document, namespacesInput.$element[0])) {
			namespacesInput.$element.replaceWith(namespacesSelect.$element);
		}
	});
}

function submitForm(pushState) {
	var params = {
			project: projectLookup.getValue(),
			page: pageLookup.getValue(),
			namespaces: namespacesSelect.getValue().join(',')
		},
		query = Object.keys(params).map(param => {
			return param + '=' + params[param];
		}).join('&');

	if (pushState) {
		history.pushState(params, null, (query ? '?' : '') + query);
	}

	if (!query) {
		outWidget.$element.html('');
		return;
	}

	if (request) request.abort();

	outLayout.$element.replaceWith(progressLayout.$element);

	request = $.get('output/?' + query).always(function() {
		progressLayout.$element.replaceWith(outLayout.$element);
	}).done(function(res) {
		outWidget.$element.html(res);
	}).fail(function() {
		outWidget.$element.html('<div class="error">Failed to send API request.</div>');
	});
}

projectLookup.on('change', function(project) {
	project = project || 'en.wikipedia.org';

	getNamespaceOptions(project);
	pageLookup.setProject(project);
});

button.on('click', function() {
	submitForm(true);
});

window.addEventListener('popstate', function(e) {
	if (!e.state) return;

	projectLookup.setValue(e.state.project);
	pageLookup.setValue(e.state.page);
	namespacesSelect.setValue(e.state.namespaces.split(','));

	submitForm(false);
});

projectLookup.emit('change');
