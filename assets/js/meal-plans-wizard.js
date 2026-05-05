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
		
		var $card = $el.closest('.restaurant-wizard-field-card');
		if ($card.length) {
			$card.addClass('has-error');
			$card.append($('<p class="restaurant-field-error" />').text(message));
		} else {
			var $error = $('<p class="restaurant-field-error" />').text(message);
			$el.addClass('has-error');
			$el.last().after($error);
		}
	}


	function MealPlansWizard($root) {
		this.$root = $root;
		this.currentStep = 1;
		this.state = {
			planType: 'individual',
			familySize: 1,
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
		this.$familySizeField = this.$root.find('.restaurant-family-size-field');
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

		this.reset();
		this.bindEvents();
		this.syncUI();
		this.updateSidebar();

		var self = this;
		$(window).on('pageshow', function (event) {
			if (event.originalEvent.persisted) {
				self.reset();
				self.syncUI();
			}
		});
	};

	MealPlansWizard.prototype.reset = function () {
		this.currentStep = 1;
		this.state = {
			planType: 'individual',
			familySize: 1,
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

		this.$root.find('input[type="radio"], input[type="checkbox"]').prop('checked', false);
		var $defaultPlan = this.$root.find('input[name="plan_type"][value="individual"]');
		if ($defaultPlan.length) {
			$defaultPlan.prop('checked', true);
		}
		
		this.$root.find('input[type="text"], input[type="number"], input[type="hidden"], textarea').val('');
		this.$root.find('select').val('');
		this.$root.find('#family_size').val(2);
		this.$root.find('.restaurant-meal-plan-quantity').val(0);
		this.$root.find('.restaurant-field-error').remove();
		this.$root.find('.has-error').removeClass('has-error');

		this.toggleFamilySizeField();
		this.updateSidebar();
	};

	MealPlansWizard.prototype.bindEvents = function () {
		var self = this;

		this.$root.on('change', 'input[name="plan_type"]', function () {
			self.state.planType = ($(this).val() || 'individual').toString();
			self.toggleFamilySizeField();
			self.updateSidebar();
		});

		this.$root.on('change input', 'input[name="family_size"]', function () {
			self.state.familySize = parseInt($(this).val(), 10) || 1;
			self.updateSidebar();
		});

		this.$root.on('change', 'select[name="meals_per_week"]', function () {
			self.state.mealsPerWeek = parseInt($(this).val(), 10) || 0;
			self.updateSidebar();
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

		this.$next.on('click', function (e) {
			e.preventDefault();
			if (!self.validateStep(self.currentStep)) {
				return;
			}

			self.currentStep = Math.min(5, self.currentStep + 1);
			self.syncUI();
			self.scrollToTop();
		});

		this.$prev.on('click', function (e) {
			e.preventDefault();
			self.currentStep = Math.max(1, self.currentStep - 1);
			self.syncUI();
			self.scrollToTop();
		});

		this.$submit.on('click', function (e) {
			e.preventDefault();
			self.submit();
		});
	};

	MealPlansWizard.prototype.toggleFamilySizeField = function () {
		if (this.state.planType === 'family') {
			this.$familySizeField.show();
			this.state.familySize = parseInt(this.$root.find('input[name="family_size"]').val(), 10) || 2;
		} else {
			this.$familySizeField.hide();
			this.state.familySize = 1;
		}
	};

	MealPlansWizard.prototype.scrollToTop = function () {
		var offset = this.$root.offset().top - 100;
		$('html, body').animate({
			scrollTop: Math.max(0, offset)
		}, 300);
	};

	MealPlansWizard.prototype.validateStep = function (step) {
		this.$root.find('.restaurant-field-error').remove();
		this.$root.find('.has-error').removeClass('has-error');
		this.$root.find('.restaurant-wizard-field-card').removeClass('has-error');

		if (step === 1) {
			if (!this.state.planType) {
				var $planContainer = this.$root.find('input[name="plan_type"]').last().closest('.restaurant-wizard-field-card');
				setInlineError($planContainer.length ? $planContainer : this.$root.find('input[name="plan_type"]').last(), 'Please choose a plan type.');
				notify('Please choose a plan type.', 'error');
				return false;
			}
			if (this.state.planType === 'family' && (this.state.familySize < 2 || Number.isNaN(this.state.familySize))) {
				setInlineError(this.$root.find('input[name="family_size"]'), 'Family size must be at least 2.');
				notify('Family size must be at least 2.', 'error');
				return false;
			}
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
			setInlineError(this.$root.find('.restaurant-meal-plan-meals-list').first(), 'Please enter quantity for at least one meal.');
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
			html += '<li><a type="button" class="restaurant-location-suggestion" data-address="' + address + '" data-lat="' + lat + '" data-lng="' + lng + '">' + address + '</a></li>';
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

		var isStepOne = this.currentStep === 1;
		var isLastStep = this.currentStep === 5;
		var isOpen = this.$root.data('subscriptions-open') == '1';

		this.$prev.prop('disabled', isStepOne);
		this.$next.toggle(!isLastStep).prop('disabled', !isOpen);
		this.$submit.toggle(isLastStep).prop('disabled', !isOpen);
		this.$root.toggleClass('is-summary-step', isLastStep);

		if (isLastStep) {
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

		var planTypeLabel = this.state.planType === 'family' ? 'Family (' + this.state.familySize + ' persons)' : 'Individual';

		var html = '';
		html += '<section class="restaurant-summary-panel">';
		html += '<h4>Meal Plan Summary</h4>';
		html += '<div class="restaurant-summary-grid">';
		html += '<article class="restaurant-summary-item"><strong>Plan Type</strong><span>' + escapeHtml(planTypeLabel) + '</span></article>';
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
			self.syncUI();
			return;
		}

		if (!this.validateStep(2)) {
			this.currentStep = 2;
			self.syncUI();
			return;
		}

		if (!this.validateStep(3)) {
			this.currentStep = 3;
			self.syncUI();
			return;
		}

		if (!this.validateStep(4)) {
			this.currentStep = 4;
			self.syncUI();
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
				family_size: this.state.familySize,
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
					
					var checkoutUrl = response.data && response.data.checkout_url;
					
					self.reset();
					self.syncUI();

					if (checkoutUrl) {
						window.location.href = checkoutUrl;
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

