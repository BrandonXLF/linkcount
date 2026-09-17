<?php

class SelfSubmittingDropdownInputWidget extends OOUI\DropdownInputWidget {
	public function __construct(array $config = []) {
		parent::__construct($config);
		$this->input->setAttributes([
			'onchange' => 'this.form.submit();'
		]);
	}
}
