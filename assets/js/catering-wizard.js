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

	function escapeHtml(value) {
		return $('<div />').text((value || '').toString()).html();
	}

	function formatMultilineHtml(value) {
		return escapeHtml(value || '').replace(/\n/g, '<br />');
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
			customDescription: '',
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
		this.$submissionStatus = this.$root.find('.restaurant-wizard-submission-status');
		this.$submissionMessage = this.$root.find('.restaurant-wizard-submission-message');
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
		this.hydrateFromDraft();
		this.syncUI();
		this.toggleCustomOfferingSection();
		this.updatePricePreview();
		this.updateSidebar();
	};

	CateringWizard.prototype.hydrateFromDraft = function () {
		var cfg = this.getAjaxConfig();
		var payload = cfg && cfg.cateringDraftPayload ? cfg.cateringDraftPayload : null;

		if (!payload || typeof payload !== 'object' || !payload.draftId) {
			return;
		}

		this.state.eventType = (payload.eventType || '').toString();
		this.state.eventDate = (payload.eventDate || '').toString();
		this.state.guestCount = parseInt(payload.guestCount, 10) || 0;
		this.state.location = (payload.location || '').toString();
		this.state.servingStyle = (payload.servingStyle || '').toString();
		this.state.customDescription = (payload.customDescription || '').toString();
		this.state.specialRequests = (payload.specialRequests || '').toString();
		this.state.dietaryNeeds = (payload.dietaryNeeds || '').toString();
		this.state.menuQuantities = {};

		if (payload.menuQuantities && typeof payload.menuQuantities === 'object') {
			Object.keys(payload.menuQuantities).forEach(function (key) {
				var quantity = parseInt(payload.menuQuantities[key], 10) || 0;
				if (quantity > 0) {
					this.state.menuQuantities[key] = quantity;
				}
			}, this);
		}

		this.applyStateToForm();
		notify('Loaded your saved catering draft.', 'success');
	};

	CateringWizard.prototype.applyStateToForm = function () {
		this.$root.find('[name="event_type"]').val(this.state.eventType).trigger('change');
		this.$root.find('[name="event_date"]').val(this.state.eventDate);
		this.$root.find('[name="guest_count"]').val(this.state.guestCount > 0 ? this.state.guestCount : '');
		this.$root.find('[name="location"]').val(this.state.location);
		this.$root.find('[name="serving_style"]').val(this.state.servingStyle);
		this.$root.find('[name="custom_description"]').val(this.state.customDescription);
		this.$root.find('[name="special_requests"]').val(this.state.specialRequests);
		this.$root.find('[name="dietary_needs"]').val(this.state.dietaryNeeds);

		this.$root.find('.restaurant-menu-quantity').each(function () {
			var $input = $(this);
			var productId = ($input.data('product-id') || '').toString();
			var quantity = parseInt((this.state.menuQuantities[productId] || 0), 10) || 0;
			$input.val(quantity > 0 ? quantity : 0);
		}.bind(this));
	};

	CateringWizard.prototype.bindEvents = function () {
		var self = this;

		this.$root.on('change input', '[name="event_type"]', function () {
			self.state.eventType = ($(this).val() || '').toString();
			self.toggleCustomOfferingSection();
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

		this.$root.on('change input', 'textarea[name="custom_description"]', function () {
			self.state.customDescription = ($(this).val() || '').toString();
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
		var detailsHtml = '';
		var isCustomOffering = this.isCustomOffering();
		var customDescription = (this.state.customDescription || '').trim();

		if (isCustomOffering && customDescription) {
			detailsHtml = '<strong>Custom Design</strong><div class="restaurant-summary-sidebar__details-text">' + formatMultilineHtml(customDescription) + '</div>';
		}

		if (!isCustomOffering) {
			Object.keys(this.state.menuQuantities).forEach(function (key) {
				var qty = parseInt(self.state.menuQuantities[key], 10) || 0;
				if (qty <= 0) {
					return;
				}
				var $input = self.$root.find('.restaurant-menu-quantity[data-product-id="' + key + '"]');
				var name = ($input.data('product-name') || ('Item #' + key)).toString();
				items.push({ name: name, quantity: qty });
			});
		}

		this.summarySidebar.update({
			items: items,
			detailsHtml: detailsHtml,
			totalHtml: this.state.pricing.totalHtml || '$0.00'
		});
	};

	CateringWizard.prototype.validateStep = function (step) {
		this.$root.find('.restaurant-field-error').remove();
		this.$root.find('.has-error').removeClass('has-error');

		if (step === 1) {
			if (!this.state.eventType || !this.state.eventDate || this.state.guestCount <= 0 || !this.state.location) {
				if (!this.state.eventType) {
					setInlineError(this.$root.find('[name="event_type"]'), 'Event type is required.');
				}
				if (!this.state.eventDate) {
					setInlineError(this.$root.find('[name="event_date"]'), 'Event date is required.');
				}
				if (this.state.guestCount <= 0) {
					setInlineError(this.$root.find('[name="guest_count"]'), 'Guest count must be greater than 0.');
				}
				if (!this.state.location) {
					setInlineError(this.$root.find('[name="location"]'), 'Location is required.');
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
			var isCustomOffering = this.isCustomOffering();

			if (isCustomOffering) {
				// For custom offerings, require custom description
				if (!this.state.customDescription || this.state.customDescription.trim() === '') {
					setInlineError(this.$root.find('textarea[name="custom_description"]'), 'Please describe what you want.');
					notify('Please describe your custom catering needs.', 'error');
					return false;
				}
			} else {
				// For standard offerings, require menu items
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
					setInlineError(this.$root.find('.restaurant-catering-menu-list, .restaurant-meals-grid').first(), 'Please select at least one menu item quantity.');
					notify('Please select at least one menu item quantity.', 'error');
					return false;
				}
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
		this.$root.toggleClass('is-summary-step', this.currentStep === 4);

		if (this.currentStep === 4) {
			this.renderSummary();
		}
	};

	CateringWizard.prototype.renderSummary = function () {
		var self = this;
		var itemCount = 0;
		var qtyTotal = 0;
		var mealCards = '';
		var customDescription = (this.state.customDescription || '').trim();
		var isCustomOffering = this.isCustomOffering();
		var customSummary = '';

		if (isCustomOffering && customDescription) {
			customSummary = '<article class="restaurant-summary-item restaurant-summary-item--full-width"><strong>Custom Design</strong><span class="restaurant-summary-text">' + formatMultilineHtml(customDescription) + '</span></article>';
		}

		if (!isCustomOffering) {
			Object.keys(this.state.menuQuantities).forEach(function (key) {
				itemCount += 1;
				var qty = parseInt(self.state.menuQuantities[key], 10) || 0;
				qtyTotal += qty;
				if (qty > 0) {
					var $input = self.$root.find('.restaurant-menu-quantity[data-product-id="' + key + '"]');
					var name = ($input.data('product-name') || ('Item #' + key)).toString();
					mealCards += '<article class="restaurant-selected-meal-card">';
					mealCards += '<h6 class="restaurant-selected-meal-card__title">' + escapeHtml(name) + '</h6>';
					mealCards += '<strong class="restaurant-selected-meal-card__price">Qty: ' + qty + '</strong>';
					mealCards += '</article>';
				}
			}, this);

			if (mealCards) {
				mealCards = '<div class="restaurant-selected-meals-cards">' + mealCards + '</div>';
			} else {
				mealCards = '<p>-</p>';
			}
		}

		var html = '';
		html += '<section class="restaurant-summary-panel">';
		html += '<h4>Event Summary</h4>';
		html += '<div class="restaurant-summary-grid">';
		html += '<article class="restaurant-summary-item"><strong>Event Type</strong><span>' + escapeHtml(this.state.eventType || '-') + '</span></article>';
		html += '<article class="restaurant-summary-item"><strong>Event Date</strong><span>' + escapeHtml(this.state.eventDate || '-') + '</span></article>';
		html += '<article class="restaurant-summary-item"><strong>Guest Count</strong><span>' + escapeHtml((this.state.guestCount || '-').toString()) + '</span></article>';
		html += '<article class="restaurant-summary-item"><strong>Location</strong><span>' + escapeHtml(this.state.location || '-') + '</span></article>';
		html += '<article class="restaurant-summary-item"><strong>Service Option</strong><span>' + escapeHtml(this.state.servingStyle || '-') + '</span></article>';
		if (isCustomOffering) {
			html += '<article class="restaurant-summary-item"><strong>Offer Type</strong><span>Custom Design</span></article>';
			html += '<article class="restaurant-summary-item"><strong>Design Notes</strong><span>' + escapeHtml(customDescription || '-') .replace(/\n/g, '<br />') + '</span></article>';
		} else {
			html += '<article class="restaurant-summary-item"><strong>Menu Items</strong><span>' + escapeHtml((itemCount + ' items (' + qtyTotal + ' qty)').toString()) + '</span></article>';
		}
		html += '</div>';
		if (isCustomOffering) {
			html += '<div class="restaurant-selected-meals-summary"><h5>Custom Design</h5>' + (customSummary || '<p>-</p>') + '</div>';
		} else {
			html += '<div class="restaurant-selected-meals-summary"><h5>Selected Meals</h5>' + mealCards + '</div>';
		}
		html += '<div class="restaurant-summary-grid">';
		html += '<article class="restaurant-summary-item"><strong>Special Requests</strong><span>' + escapeHtml(this.state.specialRequests || '-') + '</span></article>';
		html += '<article class="restaurant-summary-item"><strong>Dietary Needs</strong><span>' + escapeHtml(this.state.dietaryNeeds || '-') + '</span></article>';
		html += '<article class="restaurant-summary-item"><h6>Subtotal</h6><span>' + this.state.pricing.subtotalHtml + '</span></article>';
		html += '<article class="restaurant-summary-item"><h6>Service Fee (' + escapeHtml(this.state.pricing.serviceFeePercent.toString()) + '%)</h6><span>' + this.state.pricing.serviceFeeHtml + '</span></article>';
		html += '<article class="restaurant-summary-item"><h6>Total</h6><span>' + this.state.pricing.totalHtml + '</span></article>';
		html += '</div>';
		html += '</section>';
		this.$summary.html(html);
	};

	CateringWizard.prototype.isCustomOffering = function () {
		var eventType = (this.state.eventType || '').toString().toLowerCase();
		return eventType.indexOf('custom') !== -1;
	};

	CateringWizard.prototype.toggleCustomOfferingSection = function () {
		var isCustom = this.isCustomOffering();
		var $listSection = this.$root.find('[data-menu-section="list"]');
		var $customSection = this.$root.find('[data-menu-section="custom"]');

		if (isCustom) {
			$listSection.hide();
			$customSection.show();
		} else {
			$listSection.show();
			$customSection.hide();
		}
	};

	CateringWizard.prototype.showSubmissionStatus = function (message, type, autoDismiss) {
		var self = this;
		var $status = this.$submissionStatus;
		var $messageSpan = this.$submissionMessage;

		if (!$status.length) {
			return;
		}

		// Remove any existing auto-dismiss timeout
		if (this.statusDismissTimer) {
			clearTimeout(this.statusDismissTimer);
		}

		// Update message and type
		$messageSpan.text(message);
		$status
			.removeClass('is-success is-error')
			.addClass('is-' + type)
			.show();

		// Auto-dismiss after specified time (in ms)
		if (autoDismiss && type !== 'loading') {
			this.statusDismissTimer = setTimeout(function () {
				$status.fadeOut(300);
			}, autoDismiss);
		}
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
		this.showSubmissionStatus('Saving your draft...', 'loading');

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
				custom_description: this.state.customDescription,
				special_requests: this.state.specialRequests,
				dietary_requirements: this.state.dietaryNeeds,
				dietary_needs: this.state.dietaryNeeds
			}
		})
			.done(function (response) {
				if (response && response.success) {
					var message = (response.data && response.data.message) ? response.data.message : 'Draft saved successfully.';
					self.showSubmissionStatus(message, 'success', 3000);
					notify(message, 'success');
					return;
				}
				var errorMsg = (response && response.data && response.data.message) ? response.data.message : 'Failed to save draft.';
				self.showSubmissionStatus(errorMsg, 'error', 5000);
				notify(errorMsg, 'error');
			})
			.fail(function () {
				var errorMsg = 'Unable to save draft right now. Please check your connection.';
				self.showSubmissionStatus(errorMsg, 'error', 5000);
				notify(errorMsg, 'error');
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

		if (!this.state.eventType && !this.state.eventDate && this.state.guestCount <= 0 && !this.state.location && Object.keys(this.state.menuQuantities).length === 0 && !this.state.specialRequests && !this.state.dietaryNeeds && !this.state.customDescription) {
			notify('Cannot submit an empty catering request.', 'error');
			return;
		}

		if (!cfg.ajaxUrl || !cfg.cateringSubmitNonce) {
			notify('Unable to submit request right now.', 'error');
			return;
		}

		this.$submit.prop('disabled', true).addClass('loading');
		this.showSubmissionStatus('Submitting your catering request...', 'loading');

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
				custom_description: this.state.customDescription,
				special_requests: this.state.specialRequests,
				dietary_requirements: this.state.dietaryNeeds,
				dietary_needs: this.state.dietaryNeeds
			}
		})
			.done(function (response) {
				if (response && response.success) {
					var message = (response.data && response.data.message) ? response.data.message : 'Catering request submitted successfully.';
					self.showSubmissionStatus(message, 'success');
					notify(message, 'success');
					if (response.data && response.data.request_id) {
						self.$root.attr('data-request-id', response.data.request_id);
					}
					return;
				}
				var errorMsg = (response && response.data && response.data.message) ? response.data.message : 'Failed to submit request.';
				self.showSubmissionStatus(errorMsg, 'error', 5000);
				notify(errorMsg, 'error');
			})
			.fail(function () {
				var errorMsg = 'Unable to submit request right now. Please check your connection.';
				self.showSubmissionStatus(errorMsg, 'error', 5000);
				notify(errorMsg, 'error');
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

