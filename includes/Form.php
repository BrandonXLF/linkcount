<?php

class Form implements HtmlProducer {
	public function getHtml() {
		$fields = [
			new OOUI\FieldLayout(
				new ProjectLookupWidget([
					'name' => 'project',
					'value' => get('project'),
					'default' => Config::get('default-project'),
					'autocomplete' => false,
				]), [
					'id' => 'project-layout',
					'align' => 'top',
					'label' => rawmsg('form-project'),
					'infusable' => true
				]
			),
			new OOUI\FieldLayout(
				new PageLookupWidget([
					'name' => 'page',
					'value' => get('page'),
					'autocomplete' => false
				]), [
					'id' => 'page-layout',
					'align' => 'top',
					'label' => rawmsg('form-page'),
					'infusable' => true
				]
			),
			new OOUI\FieldLayout(
				new OOUI\TextInputWidget([
					'name' => 'namespaces',
					'value' => get('namespaces'),
					'placeholder' => rawmsg('form-separate-using-commas')
				]), [
					'id' => 'namespaces-layout',
					'align' => 'top',
					'label' => rawmsg('form-namespaces'),
					'infusable' => true
				]
			),
			new OOUI\FieldLayout(
				new OOUI\ButtonInputWidget([
					'type' => 'submit',
					'label' => rawmsg('form-submit'),
					'flags' => ['primary', 'progressive'],
				]), [
					'id' => 'submit-layout',
					'align' => 'top',
					'infusable' => true
				]
			)
		];

		return (new OOUI\FormLayout([
			'items' => $fields,
			'id' => 'form'
		]))->toString();
	}
}
