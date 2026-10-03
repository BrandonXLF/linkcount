function SelfSubmittingDropdownInputWidget(config) {
	SelfSubmittingDropdownInputWidget.super.call(this, config);
}

OO.inheritClass(SelfSubmittingDropdownInputWidget, OO.ui.DropdownInputWidget);

SelfSubmittingDropdownInputWidget.prototype.enhance = function(listener) {
	this.on('change', listener);
	this.$input.removeAttr('onchange');
	return this;
};
