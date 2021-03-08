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
    nsQueue = namespacesInput.getValue() ? namespacesInput.getValue().split(',') : [];

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
            var info = re.query.namespaces[id];
            namespacesSelect.addOptions([{
                data: info.id,
                label: info['*'] || '(Article)'
            }]);
        }

        oldValues.forEach(function(val) {
            var item = namespacesSelect.menu.findItemFromData(+val);
            if (!item) return;
            namespacesSelect.addTag(item.getData(), item.getLabel());
            namespacesSelect.menu.selectItem(item);
        });

        if ($.contains(document, namespacesInput.$element[0])) {
            namespacesInput.$element.replaceWith(namespacesSelect.$element);
        }
    });
}

function setProject(project) {
    getNamespaceOptions(project);
    pageLookup.setProject(project);
}

projectLookup.on('change', setProject);
setProject(projectLookup.getValue() || 'en.wikipedia.org');

button.on('click', function() {
    var params = [
            ['project', projectLookup.getValue()],
            ['page', pageLookup.getValue()],
            ['namespaces', namespacesSelect.getValue().join()]
        ],
        search = [];
    
    params.forEach(function(param) {
        if (param[1]) search.push(param[0] + '=' + param[1])
    });

    search = search.join('&');
    history.replaceState(null, null, location.pathname + (search ? '?' : '') + search);
    if (!search) return;

    outLayout.$element.replaceWith(progressLayout.$element);

    $.get('output?' + search).always(function() {
        progressLayout.$element.replaceWith(outLayout.$element);
    }).done(function(res) {
        outWidget.$element.html(res);
    }).fail(function() {
        outWidget.$element.html('<div class="error">Failed to send API request.</div>');
    });
});