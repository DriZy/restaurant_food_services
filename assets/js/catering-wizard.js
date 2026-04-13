(function ($) {
	'use strict';

	function getUI() {
		return window.RestaurantFoodServicesUI || {};
	}

	function notify(message, type) {
		if (getUI().showToast) {
			getUI().showToast(message, type);
		}
	}

	function setInlineError($el, message) {
		if (!$el || !$el.length) {
			return;
		}
		$el.addClass('has-error');
		$el.last().after($('<p class="restaurant-field-error" />').text(message));
	}

	function isFutureDate(dateStr) {
		if (!dateStr) {
			return false;
		}
		var today = new Date();
		today.setHours(0, 0, 0, 0);
		var selected = new Date(dateStr + 'T00:00:00');
		if (Number.isNaN(selected.getTime())) {
			return false;
		}
		return selected.getTime() > today.getTime();
	}

	function CateringWizard($root) {
		this.$root = $root;
		this.currentStep = 1;
		this.state = {
			eventType: '',
			eventDate: '',
			guestCount: 0,
			location: '',
			servingStyle: '',
			menuQuantities: {},
			specialRequests: '',
			dietaryNeeds: '',
			pricing: {
				subtotal: 0,
				serviceFeePercent: 0,
				serviceFeeAmount: 0,
				total: 0,
				subtotalHtml: '$0.00',
				serviceFeeHtml: '$0.00',
				totalHtml: '$0.00'
			}
		};

		this.$panels = this.$root.find('.restaurant-wizard-panel');
		this.$steps = this.$root.find('.restaurant-wizard-steps li');
		this.$next = this.$root.find('.restaurant-wizard-next');
		this.$prev = this.$root.find('.restaurant-wizard-prev');
		this.$summary = this.$root.find('.restaurant-wizard-summary');
		this.$pricePreview = this.$root.find('.restaurant-catering-price-preview');
		this.$saveDraft = this.$root.find('.restaurant-catering-save-draft');
		this.$submit = this.$root.find('.restaurant-catering-submit');
		this.$sidebar = this.$root.find('[data-summary-sidebar="catering"]');
		this.summarySidebar = null;
		this.priceRequestTimer = null;
	}

	CateringWizard.prototype.init = function () {
		var ui = getUI();
		var SummarySidebar = ui.SummarySidebar;

		if (this.$sidebar.length && SummarySidebar) {
			this.summarySidebar = new SummarySidebar(this.$sidebar);
		}

		this.bindEvents();
		this.syncUI();
		this.updatePricePreview();
		this.updateSidebar();
	};

	CateringWizard.prototype.bindEvents = function () {
		var self = this;

		this.$root.on('change input', '[name="event_type"]', function () {
			self.state.eventType = ($(this).val() || '').toString();
		});

		this.$root.on('change', 'input[name="event_date"]', function () {
			self.state.eventDate = ($(this).val() || '').toString();
		});

		this.$root.on('change input', 'input[name="guest_count"]', function () {
			self.state.guestCount = parseInt($(this).val(), 10) || 0;
		});

		this.$root.on('change input', 'input[name="location"]', function () {
			self.state.location = ($(this).val() || '').toString();
		});

		this.$root.on('change', 'select[name="serving_style"]', function () {
			self.state.servingStyle = ($(this).val() || '').toString();
		});

		this.$root.on('change input', '.restaurant-menu-quantity', function () {
			var productId = parseInt($(this).data('product-id'), 10) || 0;
			var quantity = parseInt($(this).val(), 10) || 0;
			if (productId > 0) {
				if (quantity > 0) {
					self.state.menuQuantities[productId] = quantity;
				} else {
					delete self.state.menuQuantities[productId];
				}
			}

			self.schedulePricePreview();
			self.updateSidebar();
		});

		this.$root.on('change input', 'textarea[name="special_requests"]', function () {
			self.state.specialRequests = ($(this).val() || '').toString();
		});

		this.$root.on('change input', 'textarea[name="dietary_needs"]', function () {
			self.state.dietaryNeeds = ($(this).val() || '').toString();
		});

		this.$next.on('click', function () {
			if (!self.validateStep(self.currentStep)) {
				return;
			}
			self.currentStep = Math.min(4, self.currentStep + 1);
			self.syncUI();
		});

		this.$prev.on('click', function () {
			self.currentStep = Math.max(1, self.currentStep - 1);
			self.syncUI();
		});

		this.$saveDraft.on('click', function () {
			self.saveDraft();
		});

		this.$submit.on('click', function () {
			self.submit();
		});
	};

	CateringWizard.prototype.schedulePricePreview = function () {
		var self = this;

		if (this.priceRequestTimer) {
			window.clearTimeout(this.priceRequestTimer);
		}

		this.priceRequestTimer = window.setTimeout(function () {
			self.updatePricePreview();
		}, 250);
	};

	CateringWizard.prototype.updatePricePreview = function () {
		var cfg = this.getAjaxConfig();
		var self = this;

		if (!cfg.ajaxUrl || !cfg.cateringPriceNonce) {
			return;
		}

		$.ajax({
			url: cfg.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'restaurant_calculate_catering_price',
				nonce: cfg.cateringPriceNonce,
				menu_quantities: this.state.menuQuantities
			}
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				return;
			}

			self.state.pricing = {
				subtotal: parseFloat(response.data.subtotal || 0),
				serviceFeePercent: parseFloat(response.data.service_fee_percent || 0),
				serviceFeeAmount: parseFloat(response.data.service_fee_amount || 0),
				total: parseFloat(response.data.total || 0),
				subtotalHtml: response.data.subtotal_html || '$0.00',
				serviceFeeHtml: response.data.service_fee_html || '$0.00',
				totalHtml: response.data.total_html || '$0.00'
			};

			self.$pricePreview.find('.restaurant-price-subtotal').html(self.state.pricing.subtotalHtml);
			self.$pricePreview.find('.restaurant-price-service-fee').html(self.state.pricing.serviceFeeHtml + ' (' + self.state.pricing.serviceFeePercent + '%)');
			self.$pricePreview.find('.restaurant-price-total').html(self.state.pricing.totalHtml);
			self.updateSidebar();
		});
	};

	CateringWizard.prototype.updateSidebar = function () {
		if (!this.summarySidebar) {
			return;
		}

		var self = this;
		var items = [];

		Object.keys(this.state.menuQuantities).forEach(function (key) {
			var qty = parseInt(self.state.menuQuantities[key], 10) || 0;
			if (qty <= 0) {
				return;
			}
			var $input = self.$root.find('.restaurant-menu-quantity[data-product-id="' + key + '"]');
			var name = ($input.data('product-name') || ('Item #' + key)).toString();
			items.push({ name: name, quantity: qty });
		});

		this.summarySidebar.update({
			items: items,
			totalHtml: this.state.pricing.totalHtml || '$0.00'
		});
	};

	CateringWizard.prototype.validateStep = function (step) {
		this.$root.find('.restaurant-field-error').remove();
		this.$root.find('.has-error').removeClass('has-error');

		if (step === 1) {
			if (!this.state.eventType || !this.state.eventDate || this.state.guestCount <= 0 || !this.state.location) {
				if (!this.state.eventType) {
					setInlineError(this.$root.find('input[name="event_type"]'), 'Event type is required.');
				}
				if (!this.state.eventDate) {
					setInlineError(this.$root.find('input[name="event_date"]'), 'Event date is required.');
				}
				if (this.state.guestCount <= 0) {
					setInlineError(this.$root.find('input[name="guest_count"]'), 'Guest count must be greater than 0.');
				}
				if (!this.state.location) {
					setInlineError(this.$root.find('input[name="location"]'), 'Location is required.');
				}
				notify('Please complete all event details.', 'error');
				return false;
			}

			if (!isFutureDate(this.state.eventDate)) {
				setInlineError(this.$root.find('input[name="event_date"]'), 'Event date must be in the future.');
				notify('Please choose a future event date.', 'error');
				return false;
			}
		}

		if (step === 2) {
			var hasInvalidQuantity = false;
			this.$root.find('.restaurant-menu-quantity').each(function () {
				var raw = parseInt($(this).val(), 10);
				if (!Number.isNaN(raw) && raw < 0) {
					hasInvalidQuantity = true;
					$(this).val(0);
					setInlineError($(this), 'Quantity cannot be negative.');
				}
			});

			if (hasInvalidQuantity) {
				notify('Please correct invalid quantities.', 'error');
				return false;
			}

			if (Object.keys(this.state.menuQuantities).length === 0) {
				setInlineError(this.$root.find('.restaurant-meals-grid').first(), 'Please select at least one menu item quantity.');
				notify('Please select at least one menu item quantity.', 'error');
				return false;
			}
		}

		if (step === 3 && !this.state.servingStyle) {
			setInlineError(this.$root.find('select[name="serving_style"]'), 'Please select a service option.');
			notify('Please select a service option.', 'error');
			return false;
		}

		return true;
	};

	CateringWizard.prototype.syncUI = function () {
		var self = this;

		this.$panels.removeClass('is-active').filter('[data-step="' + this.currentStep + '"]').addClass('is-active');

		this.$steps.each(function () {
			var $step = $(this);
			var stepNum = parseInt($step.data('step'), 10) || 0;
			$step.toggleClass('is-active', stepNum === self.currentStep);
			$step.toggleClass('is-complete', stepNum < self.currentStep);
		});

		this.$prev.prop('disabled', this.currentStep === 1);
		this.$next.toggle(this.currentStep < 4);

		if (this.currentStep === 4) {
			this.renderSummary();
		}
	};

	CateringWizard.prototype.renderSummary = function () {
		var itemCount = 0;
		var qtyTotal = 0;
		Object.keys(this.state.menuQuantities).forEach(function (key) {
			itemCount += 1;
			qtyTotal += parseInt(this.state.menuQuantities[key], 10) || 0;
		}, this);

		var html = '';
		html += '<p><strong>Event Type:</strong> ' + (this.state.eventType || '-') + '</p>';
		html += '<p><strong>Event Date:</strong> ' + (this.state.eventDate || '-') + '</p>';
		html += '<p><strong>Guest Count:</strong> ' + (this.state.guestCount || '-') + '</p>';
		html += '<p><strong>Location:</strong> ' + (this.state.location || '-') + '</p>';
		html += '<p><strong>Service Option:</strong> ' + (this.state.servingStyle || '-') + '</p>';
		html += '<p><strong>Menu Items:</strong> ' + itemCount + ' (' + qtyTotal + ' total qty)</p>';
		html += '<p><strong>Special Requests:</strong> ' + (this.state.specialRequests || '-') + '</p>';
		html += '<p><strong>Dietary Needs:</strong> ' + (this.state.dietaryNeeds || '-') + '</p>';
		html += '<p><strong>Subtotal:</strong> ' + this.state.pricing.subtotalHtml + '</p>';
		html += '<p><strong>Service Fee (' + this.state.pricing.serviceFeePercent + '%):</strong> ' + this.state.pricing.serviceFeeHtml + '</p>';
		html += '<p><strong>Total:</strong> ' + this.state.pricing.totalHtml + '</p>';
		this.$summary.html(html);
	};

	CateringWizard.prototype.getAjaxConfig = function () {
		return window.RestaurantFoodServicesPublic || {};
	};

	CateringWizard.prototype.saveDraft = function () {
		var cfg = this.getAjaxConfig();
		var self = this;

		if (!cfg.ajaxUrl || !cfg.cateringDraftNonce) {
			notify('Unable to save draft right now.', 'error');
			return;
		}

		this.$saveDraft.prop('disabled', true).addClass('loading');

		$.ajax({
			url: cfg.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'restaurant_save_catering_draft',
				nonce: cfg.cateringDraftNonce,
				event_type: this.state.eventType,
				event_date: this.state.eventDate,
				guest_count: this.state.guestCount,
				location: this.state.location,
				serving_style: this.state.servingStyle,
				menu_quantities: this.state.menuQuantities,
				special_requests: this.state.specialRequests,
				dietary_requirements: this.state.dietaryNeeds,
				dietary_needs: this.state.dietaryNeeds
			}
		})
			.done(function (response) {
				if (response && response.success) {
					notify((response.data && response.data.message) ? response.data.message : 'Draft saved.', 'success');
					return;
				}
				notify((response && response.data && response.data.message) ? response.data.message : 'Failed to save draft.', 'error');
			})
			.fail(function () {
				notify('Unable to save draft right now.', 'error');
			})
			.always(function () {
				self.$saveDraft.prop('disabled', false).removeClass('loading');
			});
	};

	CateringWizard.prototype.submit = function () {
		var cfg = this.getAjaxConfig();
		var self = this;

		if (!this.validateStep(1)) {
			this.currentStep = 1;
			this.syncUI();
			return;
		}

		if (!this.validateStep(2)) {
			this.currentStep = 2;
			this.syncUI();
			return;
		}

		if (!this.validateStep(3)) {
			this.currentStep = 3;
			this.syncUI();
			return;
		}

		if (!this.state.eventType && !this.state.eventDate && this.state.guestCount <= 0 && !this.state.location && Object.keys(this.state.menuQuantities).length === 0 && !this.state.specialRequests && !this.state.dietaryNeeds) {
			notify('Cannot submit an empty catering request.', 'error');
			return;
		}

		if (!cfg.ajaxUrl || !cfg.cateringSubmitNonce) {
			notify('Unable to submit request right now.', 'error');
			return;
		}

		this.$submit.prop('disabled', true).addClass('loading');

		$.ajax({
			url: cfg.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'restaurant_submit_catering_request',
				nonce: cfg.cateringSubmitNonce,
				event_type: this.state.eventType,
				event_date: this.state.eventDate,
				guest_count: this.state.guestCount,
				location: this.state.location,
				serving_style: this.state.servingStyle,
				menu_quantities: this.state.menuQuantities,
				special_requests: this.state.specialRequests,
				dietary_requirements: this.state.dietaryNeeds,
				dietary_needs: this.state.dietaryNeeds
			}
		})
			.done(function (response) {
				if (response && response.success) {
					notify((response.data && response.data.message) ? response.data.message : 'Catering request submitted.', 'success');
					if (response.data && response.data.request_id) {
						self.$root.attr('data-request-id', response.data.request_id);
					}
					return;
				}
				notify((response && response.data && response.data.message) ? response.data.message : 'Failed to submit request.', 'error');
			})
			.fail(function () {
				notify('Unable to submit request right now.', 'error');
			})
			.always(function () {
				self.$submit.prop('disabled', false).removeClass('loading');
			});
	};

	$(function () {
		$('.restaurant-catering-wizard').each(function () {
			var wizard = new CateringWizard($(this));
			wizard.init();
		});
	});
})(jQuery);

