let projectLookup = OO.ui.infuse($('#project')),
	pageLookup = OO.ui.infuse($('#page'), {
		domain: projectLookup.getDomain()
	}),
	namespacesInput = OO.ui.infuse($('#namespaces')),
	button = OO.ui.infuse($('#submit')),
	namespacesSelect = new NamespaceLookupWidget({
		value: namespacesInput.getValue() && namespacesInput.getValue().split(','),
		domain: projectLookup.getDomain()
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
			namespaces: namespacesSelect.getValue().join(',')
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
	namespacesSelect.setDomain(domain);
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
	namespacesSelect.setValue((params.namespaces || '').split(','));

	submitForm(false);
});

$('#skip').on('click', function(e) {
	e.preventDefault();
	out.trigger('focus');
});

$('.lang-select-cnt select').on('change', function() {
	this.form.action = './setlang/' + location.search
	this.form.submit();
});

$('.lang-select-form').on('submit', function(e) {
	e.preventDefault();
	let form = this,
		params = {
			project: form.project.value,
			page: form.page.value,
			namespaces: form.namespaces.value
		},
		query = Object.keys(params)
			.filter(param => params[param])
			.map(param => param + '=' + encodeURIComponent(params[param]))
			.join('&');

	if (query) {
		location.search = query;
	} else {
		location.href = location.pathname;
	}
});

$('.lang-select-form .oo-ui-buttonInputWidget').remove();

namespacesInput.$element.replaceWith(namespacesSelect.$element);
