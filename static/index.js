let langSelect = OO.ui.infuse($('#lang-layout')).fieldWidget,
	projectLookup = OO.ui.infuse($('#project-layout')).fieldWidget,
	pageLookup = OO.ui.infuse($('#page-layout'), {
		domain: projectLookup.getDomain()
	}).fieldWidget,
	namespacesInputLayout = OO.ui.infuse($('#namespaces-layout')),
	namespacesInput = namespacesInputLayout.fieldWidget,
	button = OO.ui.infuse($('#submit-layout')).fieldWidget,
	namespacesLookup = new NamespaceLookupWidget({
		value: namespacesInput.getValue().split(','),
		domain: projectLookup.getDomain()
	}),
	namespacesLookupLayout = new OO.ui.FieldLayout(namespacesLookup, {
		align: 'top',
		label: namespacesInputLayout.getLabel(),
	}),
	progressWidget = new OO.ui.ProgressBarWidget(),
	progressLayout = new OO.ui.FieldLayout(progressWidget, {
		align: 'top'
	}),
	out = $('#out'),
	request,
	currentSearch = location.search;

function submitForm(pushState) {
	let params = {
			project: projectLookup.getValue(),
			page: pageLookup.getValue(),
			namespaces: namespacesLookup.getValue().join(',')
		},
		query = Object.keys(params)
			.filter(param => params[param])
			.map(param => param + '=' + encodeURIComponent(params[param]))
			.join('&');

	if (pushState) {
		currentSearch = (query ? '?' : '') + query;
		history.pushState({}, null, currentSearch);
	}

	if (!query) {
		out.empty();
		return;
	}

	if (request) {
		request.wasReplaced = true;
		request.abort();
	}

	out.html(progressLayout.$element);

	request = $.get('output/?' + query);

	request.then(function(res) {
		document.title = res.title;
		out.html(res.html);
	}, function(req) {
		if (req.wasReplaced) return;

		out.html('<div class="error">Failed to send API request.</div>');
	});
}

projectLookup.on('domain', function (domain) {
	pageLookup.setDomain(domain);
	namespacesLookup.setDomain(domain);
});

button.on('click', function() {
	submitForm(true);
});

window.addEventListener('popstate', function() {
	if (currentSearch === location.search) return;
	currentSearch = location.search;

	let params = {};

	currentSearch.slice(1).split('&').forEach(param => {
		let chunks = param.split('='),
			key = chunks.shift(),
			value = decodeURIComponent(chunks.join('='));

		params[key] = value;
	});

	projectLookup.setValue(params.project || '');
	pageLookup.setValue(params.page || '');
	namespacesLookup.setValue((params.namespaces || '').split(','));

	submitForm(false);
});

$('#skip').on('click', function(e) {
	e.preventDefault();
	out.trigger('focus');
});

langSelect.on('change', function() {
	const input = langSelect.$input[0];
	input.form.action = './setlang/' + location.search
	input.form.submit();
}).$input.removeAttr('onchange');

namespacesInputLayout.$element.replaceWith(namespacesLookupLayout.$element);
