<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Link Count</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <script src="node_modules/jquery/dist/jquery.min.js"></script>
        <script src="node_modules/oojs/dist/oojs.min.js"></script>
        <script src="node_modules/oojs-ui/dist/oojs-ui.min.js"></script>
        <script src="node_modules/oojs-ui/dist/oojs-ui-wikimediaui.min.js"></script>
        <script src="static/widgets.js"></script>
        <link rel="stylesheet" href="node_modules/oojs-ui/dist/oojs-ui-wikimediaui.min.css">
        <link rel="stylesheet" href="static/index.css">
        <link rel="shortcut icon" type="image/png" href="static/icon.png">
    </head>
    <body>
        <main>
            <h1>Link Count</h1>
            <form>
<?php

require 'vendor/autoload.php';
require 'includes/global.php';
require 'includes/widgets.php';

OOUI\Theme::setSingleton(new OOUI\WikimediaUITheme);

echo new OOUI\FieldLayout(
    new ProjectLookupWidget([
        'name' => 'project',
        'id' => 'project',
        'value' => $_GET['project'] ?? '',
        'placeholder' => 'en.wikipedia.org',
        'infusable' => true
    ]), [
        'align' => 'top',
        'label' => 'Project'
    ]
);

echo new OOUI\FieldLayout(
    new PageLookupWidget([
        'name' => 'page',
        'id' => 'page',
        'value' => $_GET['page'] ?? '',
        'infusable' => true
    ]), [
        'align' => 'top',
        'label' => 'Page'
    ]
);

echo new OOUI\FieldLayout(
    new OOUI\TextInputWidget([
        'name' => 'namespaces',
        'id' => 'namespaces',
        'value' => $_GET['namespaces'] ?? '',
        'placeholder' => 'Separate using commas',
        'infusable' => true
    ]), [
        'align' => 'top',
        'label' => 'Namespaces'
    ]
);

echo new OOUI\FieldLayout(
    new OOUI\ButtonInputWidget([
        'id' => 'submit',
        'type' => 'submit',
        'label' => 'Submit',
        'flags' => ['primary', 'progressive'],
        'infusable' => true
    ]), [
        'align' => 'top'
    ]
);

echo new OOUI\FieldLayout(
    new HTMLWidget([
        'id' => 'out',
        'html' => get_output_html(),
        'infusable' => true
    ]), [
        'id' => 'out-layout',
        'align' => 'top',
        'infusable' => true
    ]
);

?>
            </form>
        </main>
        <footer>
            Checkout the <a href="api">API</a>.
            View source on <a href="https://github.com/BrandonXLF/linkcount">GitHub</a>.
            Created by <a href="https://en.wikipedia.org/wiki/User:BrandonXLF">BrandonXLF</a>.
        </footer>
        <script src="static/index.js"></script>
    </body>
</html>