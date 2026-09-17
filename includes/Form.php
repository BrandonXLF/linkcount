<?php

class Form implements HtmlProducer {
	public function getHtml() {
		$fields = [
			new OOUI\FieldLayout(
				new ProjectLookupWidget([
					'name' => 'project',
					'id' => 'project',
					'value' => get('project'),
					'default' => Config::get('default-project'),
					'autocomplete' => false,
					'infusable' => true
				]), [
					'align' => 'top',
					'label' => rawmsg('form-project')
				]
			),
			new OOUI\FieldLayout(
				new PageLookupWidget([
					'name' => 'page',
					'id' => 'page',
					'value' => get('page'),
					'autocomplete' => false,
					'infusable' => true
				]), [
					'align' => 'top',
					'label' => rawmsg('form-page')
				]
			),
			new OOUI\FieldLayout(
				new OOUI\TextInputWidget([
					'name' => 'namespaces',
					'id' => 'namespaces',
					'value' => get('namespaces'),
					'placeholder' => rawmsg('form-separate-using-commas'),
					'infusable' => true
				]), [
					'align' => 'top',
					'label' => rawmsg('form-namespaces')
				]
			),
			new OOUI\FieldLayout(
				new OOUI\ButtonInputWidget([
					'id' => 'submit',
					'type' => 'submit',
					'label' => rawmsg('form-submit'),
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
