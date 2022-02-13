<?php

class Form implements ProducesHtml {
	public function getHtml() {
		$fields = [
			new OOUI\FieldLayout(
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
			),
			new OOUI\FieldLayout(
				new PageLookupWidget([
					'name' => 'page',
					'id' => 'page',
					'value' => get('page'),
					'infusable' => true
				]), [
					'align' => 'top',
					'label' => 'Page'
				]
			),
			new OOUI\FieldLayout(
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
			),
			new OOUI\FieldLayout(
				new OOUI\ButtonInputWidget([
					'id' => 'submit',
					'type' => 'submit',
					'label' => 'Submit',
					'flags' => ['primary', 'progressive'],
					'infusable' => true
				]), [
					'align' => 'top'
				]
			)
		];

		return (new OOUI\FormLayout([
			'items' => $fields,
			'id' => 'form'
		]))->toString();
	}
}
