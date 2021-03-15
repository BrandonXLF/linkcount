<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/LinkCount.php';
require __DIR__ . '/Widgets.php';

class Form {
    public static function html() {
        $out = '';
        
        OOUI\Theme::setSingleton(new OOUI\WikimediaUITheme);

        $out .= new OOUI\FieldLayout(
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

        $out .= new OOUI\FieldLayout(
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

        $out .= new OOUI\FieldLayout(
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

        $out .= new OOUI\FieldLayout(
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

        $out .= new OOUI\FieldLayout(
            new HTMLWidget([
                'id' => 'out',
                'html' => (new LinkCount())->html(),
                'infusable' => true
            ]), [
                'id' => 'out-layout',
                'align' => 'top',
                'infusable' => true
            ]
        );
        
        return $out;
    }
}