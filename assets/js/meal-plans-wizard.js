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

	function setCartCount(count) {
		if (getUI().updateCartCount) {
			getUI().updateCartCount(count);
		}
	}

	function formatPrice(amount) {
		var value = parseFloat(amount || 0);
		return '$' + value.toFixed(2);
	}

	function escapeHtml(value) {
		return $('<div />').text((value || '').toString()).html();
	}

	function setInlineError($el, message) {
		if (!$el || !$el.length) {
			return;
		}
		var $error = $('<p class="restaurant-field-error" />').text(message);
		$el.addClass('has-error');
		$el.last().after($error);
	}


	function MealPlansWizard($root) {
		this.$root = $root;
		this.currentStep = 1;
		this.state = {
			planType: '',
			mealsPerWeek: 0,
			selectedMeals: [],
			deliveryLocation: {
				formattedAddress: '',
				latitude: '',
				longitude: ''
			},
			deliveryDays: [],
			deliveryTime: 'morning'
		};

		this.$panels = this.$root.find('.restaurant-wizard-panel');
		this.$steps = this.$root.find('.restaurant-wizard-steps li');
		this.$next = this.$root.find('.restaurant-wizard-next');
		this.$prev = this.$root.find('.restaurant-wizard-prev');
		this.$submit = this.$root.find('.restaurant-wizard-submit');
		this.$summary = this.$root.find('.restaurant-wizard-summary');
		this.$sidebar = this.$root.find('[data-summary-sidebar="meal-plans"]');
		this.$locationInput = this.$root.find('input[name="delivery_location"]');
		this.$locationLatitude = this.$root.find('input[name="delivery_latitude"]');
		this.$locationLongitude = this.$root.find('input[name="delivery_longitude"]');
		this.$locationSuggestions = this.$root.find('.restaurant-location-suggestions');
		this.summarySidebar = null;
		this.locationSearchTimer = null;
		this.locationRequest = null;
	}

	MealPlansWizard.prototype.init = function () {
		var ui = getUI();
		var SummarySidebar = ui.SummarySidebar;

		if (this.$sidebar.length && SummarySidebar) {
			this.summarySidebar = new SummarySidebar(this.$sidebar);
		}

		this.bindEvents();
		this.syncUI();
		this.updateSidebar();
	};

	MealPlansWizard.prototype.bindEvents = function () {
		var self = this;

		this.$root.on('change', 'input[name="plan_type"]', function () {
			self.state.planType = ($(this).val() || '').toString();
		});

		this.$root.on('change', 'select[name="meals_per_week"]', function () {
			self.state.mealsPerWeek = parseInt($(this).val(), 10) || 0;
		});

		this.$root.on('change input', '.restaurant-meal-plan-quantity', function () {
			var qty = parseInt($(this).val(), 10);

			if (!Number.isNaN(qty) && qty < 0) {
				qty = 0;
			}

			$(this).val(Number.isNaN(qty) ? 0 : qty);
			self.state.selectedMeals = self.collectSelectedMeals();
			self.updateSidebar();
		});

		this.$root.on('change', '.restaurant-delivery-day', function () {
			var days = [];
			self.$root.find('.restaurant-delivery-day:checked').each(function () {
				days.push(($(this).val() || '').toString());
			});
			self.state.deliveryDays = days;
			self.updateSidebar();
		});

		this.$root.on('change', 'select[name="delivery_time"]', function () {
			self.state.deliveryTime = ($(this).val() || 'morning').toString();
			self.updateSidebar();
		});

		this.$root.on('input', 'input[name="delivery_location"]', function () {
			var query = ($(this).val() || '').toString().trim();
			self.state.deliveryLocation.formattedAddress = query;
			self.state.deliveryLocation.latitude = '';
			self.state.deliveryLocation.longitude = '';
			self.$locationLatitude.val('');
			self.$locationLongitude.val('');

			if (self.locationSearchTimer) {
				window.clearTimeout(self.locationSearchTimer);
			}

			if (query.length < 3) {
				self.renderLocationSuggestions([]);
				self.updateSidebar();
				return;
			}

			self.locationSearchTimer = window.setTimeout(function () {
				self.searchLocations(query);
			}, 250);
			self.updateSidebar();
		});

		this.$root.on('click', '.restaurant-location-suggestion', function () {
			var $item = $(this);
			self.state.deliveryLocation.formattedAddress = ($item.data('address') || '').toString();
			self.state.deliveryLocation.latitude = ($item.data('lat') || '').toString();
			self.state.deliveryLocation.longitude = ($item.data('lng') || '').toString();
			self.$locationInput.val(self.state.deliveryLocation.formattedAddress);
			self.$locationLatitude.val(self.state.deliveryLocation.latitude);
			self.$locationLongitude.val(self.state.deliveryLocation.longitude);
			self.renderLocationSuggestions([]);
			self.updateSidebar();
		});

		this.$root.on('change', 'select[name="meals_per_week"]', function () {
			self.updateSidebar();
		});

		this.$next.on('click', function () {
			if (!self.validateStep(self.currentStep)) {
				return;
			}

			self.currentStep = Math.min(5, self.currentStep + 1);
			self.syncUI();
		});

		this.$prev.on('click', function () {
			self.currentStep = Math.max(1, self.currentStep - 1);
			self.syncUI();
		});

		this.$submit.on('click', function () {
			self.submit();
		});
	};

	MealPlansWizard.prototype.validateStep = function (step) {
		this.$root.find('.restaurant-field-error').remove();
		this.$root.find('.has-error').removeClass('has-error');

		if (step === 1 && !this.state.planType) {
			setInlineError(this.$root.find('input[name="plan_type"]').last().closest('label'), 'Please choose a plan type.');
			notify('Please choose a plan type.', 'error');
			return false;
		}

		if (step === 2 && this.state.mealsPerWeek <= 0) {
			setInlineError(this.$root.find('select[name="meals_per_week"]'), 'Please select meals per week.');
			notify('Please select meals per week.', 'error');
			return false;
		}

		if (step === 3 && this.state.selectedMeals.length === 0) {
			this.state.selectedMeals = this.collectSelectedMeals();
		}

		if (step === 3 && this.state.selectedMeals.length === 0) {
			setInlineError(this.$root.find('.restaurant-meals-grid').first(), 'Please enter quantity for at least one meal.');
			notify('Please enter quantity for at least one meal.', 'error');
			return false;
		}

		if (step === 4 && this.state.deliveryDays.length === 0) {
			setInlineError(this.$root.find('.restaurant-delivery-days').first(), 'Please select delivery days.');
			notify('Please select delivery days.', 'error');
			return false;
		}

		if (step === 4 && !this.state.deliveryLocation.formattedAddress) {
			setInlineError(this.$locationInput, 'Please provide a delivery location.');
			notify('Please provide a delivery location.', 'error');
			return false;
		}

		if (step === 4 && ('' === this.state.deliveryLocation.latitude || '' === this.state.deliveryLocation.longitude)) {
			setInlineError(this.$locationInput, 'Please select a location from suggestions.');
			notify('Please select a location from suggestions.', 'error');
			return false;
		}

		return true;
	};

	MealPlansWizard.prototype.searchLocations = function (query) {
		var self = this;
		var cfg = window.RestaurantFoodServicesPublic || {};
		if (!cfg.ajaxUrl || !cfg.locationSearchNonce) {
			self.renderLocationSuggestions([]);
			return;
		}

		if (self.locationRequest && self.locationRequest.readyState !== 4) {
			self.locationRequest.abort();
		}

		self.locationRequest = $.ajax({
			url: cfg.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'restaurant_location_autocomplete',
				nonce: cfg.locationSearchNonce,
				q: query
			}
		}).done(function (response) {
			if (!response || !response.success || !response.data || !Array.isArray(response.data.items)) {
				self.renderLocationSuggestions([]);
				return;
			}

			self.renderLocationSuggestions(response.data.items);
		}).fail(function () {
			self.renderLocationSuggestions([]);
		});
	};

	MealPlansWizard.prototype.renderLocationSuggestions = function (suggestions) {
		if (!this.$locationSuggestions.length) {
			return;
		}

		if (!Array.isArray(suggestions) || !suggestions.length) {
			this.$locationSuggestions.empty().attr('hidden', true);
			return;
		}

		var html = '<ul class="restaurant-location-suggestions__list">';
		suggestions.forEach(function (item) {
			if (!item || !item.formatted_address) {
				return;
			}

			var address = escapeHtml(item.formatted_address.toString());
			var lat = escapeHtml((item.latitude || '').toString());
			var lng = escapeHtml((item.longitude || '').toString());
			html += '<li><button type="button" class="restaurant-location-suggestion" data-address="' + address + '" data-lat="' + lat + '" data-lng="' + lng + '">' + address + '</button></li>';
		});
		html += '</ul>';

		this.$locationSuggestions.html(html).attr('hidden', false);
	};

	MealPlansWizard.prototype.updateSidebar = function () {
		if (!this.summarySidebar) {
			return;
		}

		this.state.selectedMeals = this.collectSelectedMeals();

		var total = 0;
		var items = [];

		this.state.selectedMeals.forEach(function (meal) {
			var qty = parseInt(meal.quantity, 10) || 0;
			var price = parseFloat(meal.price || 0);
			if (qty <= 0) {
				return;
			}

			total += (price * qty);
			items.push({ name: meal.name, quantity: qty });
		});

		this.summarySidebar.update({
			items: items,
			totalHtml: formatPrice(total)
		});
	};

	MealPlansWizard.prototype.syncUI = function () {
		var self = this;

		this.$panels.removeClass('is-active').filter('[data-step="' + this.currentStep + '"]').addClass('is-active');

		this.$steps.each(function () {
			var $step = $(this);
			var stepNum = parseInt($step.data('step'), 10) || 0;
			$step.toggleClass('is-active', stepNum === self.currentStep);
			$step.toggleClass('is-complete', stepNum < self.currentStep);
		});

		this.$prev.prop('disabled', this.currentStep === 1);
		this.$next.toggle(this.currentStep < 5);
		this.$submit.toggle(this.currentStep === 5);
		this.$root.toggleClass('is-summary-step', this.currentStep === 5);

		if (this.currentStep === 5) {
			this.renderSummary();
		}
	};

	MealPlansWizard.prototype.renderSummary = function () {
		var selectedMeals = this.collectSelectedMeals();
		var selectedMealsTotal = 0;

		selectedMeals.forEach(function (meal) {
			var qty = parseInt(meal.quantity, 10) || 0;
			var price = parseFloat(meal.price || 0);
			if (qty > 0) {
				selectedMealsTotal += qty;
			}
		});

		var mealCardsHtml = '<p>-</p>';
		if (selectedMeals.length) {
			mealCardsHtml = '<div class="restaurant-selected-meals-cards">' + selectedMeals.map(function (meal) {
				var qty = parseInt(meal.quantity, 10) || 0;
				var lineTotal = (parseFloat(meal.price || 0) * qty);
				return '<article class="restaurant-selected-meal-card">' +
					'<h4 class="restaurant-selected-meal-card__title">' + escapeHtml(meal.name) + '</h4>' +
					'<p class="restaurant-selected-meal-card__price">' + formatPrice(meal.price) + ' x ' + escapeHtml(qty.toString()) + '</p>' +
					'<p class="restaurant-selected-meal-card__price">' + formatPrice(lineTotal) + '</p>' +
				'</article>';
			}).join('') + '</div>';
		}

		var html = '';
		html += '<section class="restaurant-summary-panel">';
		html += '<h4>Meal Plan Summary</h4>';
		html += '<div class="restaurant-summary-grid">';
		html += '<article class="restaurant-summary-item"><strong>Plan Type</strong><span>' + escapeHtml(this.state.planType || '-') + '</span></article>';
		html += '<article class="restaurant-summary-item"><strong>Meals Per Week</strong><span>' + escapeHtml((this.state.mealsPerWeek || '-').toString()) + '</span></article>';
		html += '<article class="restaurant-summary-item"><strong>Delivery Location</strong><span>' + escapeHtml(this.state.deliveryLocation.formattedAddress || '-') + '</span></article>';
		html += '<article class="restaurant-summary-item"><strong>Delivery Days</strong><span>' + escapeHtml(this.state.deliveryDays.join(', ') || '-') + '</span></article>';
		html += '<article class="restaurant-summary-item"><strong>Delivery Time</strong><span>' + escapeHtml(this.state.deliveryTime || '-') + '</span></article>';
		html += '<article class="restaurant-summary-item"><strong>Meals Selected</strong><span>' + escapeHtml(selectedMeals.length.toString()) + ' (' + escapeHtml(selectedMealsTotal.toString()) + ' qty)</span></article>';
		html += '</div>';
		html += '<div class="restaurant-selected-meals-summary"><h5>Selected Meals</h5>' + mealCardsHtml + '</div>';
		html += '</section>';
		this.$summary.html(html);
	};

	MealPlansWizard.prototype.collectSelectedMeals = function () {
		var selectedMeals = [];

		this.$root.find('.restaurant-meal-plan-quantity').each(function () {
			var $input = $(this);
			var productId = parseInt($input.data('product-id'), 10) || 0;
			var quantity = parseInt($input.val(), 10) || 0;

			if (productId <= 0 || quantity <= 0) {
				return;
			}

			selectedMeals.push({
				product_id: productId,
				quantity: quantity,
				name: ($input.data('product-name') || 'Meal').toString(),
				price: parseFloat($input.data('product-price') || 0)
			});
		});

		return selectedMeals;
	};

	MealPlansWizard.prototype.submit = function () {
		var self = this;
		var cfg = window.RestaurantFoodServicesPublic || {};

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

		if (!this.validateStep(4)) {
			this.currentStep = 4;
			this.syncUI();
			return;
		}

		if (!cfg.ajaxUrl || !cfg.mealPlanSubmitNonce) {
			notify('Unable to submit meal plan right now.', 'error');
			return;
		}

		this.state.selectedMeals = this.collectSelectedMeals();

		this.$submit.prop('disabled', true).addClass('loading');

		$.ajax({
			url: cfg.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'restaurant_submit_meal_plan_selection',
				nonce: cfg.mealPlanSubmitNonce,
				plan_type: this.state.planType,
				meals_per_week: this.state.mealsPerWeek,
				selected_meals: this.state.selectedMeals,
				delivery_location: this.state.deliveryLocation.formattedAddress,
				delivery_latitude: this.state.deliveryLocation.latitude,
				delivery_longitude: this.state.deliveryLocation.longitude,
				delivery_days: this.state.deliveryDays,
				delivery_time: this.state.deliveryTime
			}
		})
			.done(function (response) {
				if (response && response.success) {
					if (response.data && typeof response.data.cart_count !== 'undefined') {
						setCartCount(response.data.cart_count);
					}
					notify((response.data && response.data.message) ? response.data.message : 'Meal plan saved.', 'success');
								if (response.data && response.data.checkout_url) {
									window.location.href = response.data.checkout_url;
								}
					return;
				}

				notify((response && response.data && response.data.message) ? response.data.message : 'Unable to submit meal plan.', 'error');
			})
			.fail(function () {
				notify('Unable to submit meal plan right now.', 'error');
			})
			.always(function () {
				self.$submit.prop('disabled', false).removeClass('loading');
			});
	};

	$(function () {
		$('.restaurant-meal-plans-wizard').each(function () {
			var wizard = new MealPlansWizard($(this));
			wizard.init();
		});
	});
})(jQuery);

