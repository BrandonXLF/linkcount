<?php

class Form {
	public static function html() {
		$out = '';

		$out .= new OOUI\FieldLayout(
			new ProjectLookupWidget([
				'name' => 'project',
				'id' => 'project',
				'value' => get('project'),
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
				'value' => get('page'),
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
				'value' => get('namespaces'),
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
				'html' => (new LinkCount(get('page'), get('project'), get('namespaces')))->html(),
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
