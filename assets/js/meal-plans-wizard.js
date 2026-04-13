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
		this.summarySidebar = null;
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

		this.$root.on('change', '.restaurant-meal-select', function () {
			var selected = [];
			self.$root.find('.restaurant-meal-select:checked').each(function () {
				selected.push(parseInt($(this).val(), 10));
			});
			self.state.selectedMeals = selected.filter(function (id) { return id > 0; });
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
			setInlineError(this.$root.find('.restaurant-meals-grid').first(), 'Please choose at least one meal.');
			notify('Please choose at least one meal.', 'error');
			return false;
		}

		if (step === 4 && this.state.deliveryDays.length === 0) {
			setInlineError(this.$root.find('.restaurant-delivery-days').first(), 'Please select delivery days.');
			notify('Please select delivery days.', 'error');
			return false;
		}

		return true;
	};

	MealPlansWizard.prototype.updateSidebar = function () {
		if (!this.summarySidebar) {
			return;
		}

		var total = 0;
		var items = [];

		this.$root.find('.restaurant-meal-select:checked').each(function () {
			var $input = $(this);
			var name = ($input.data('product-name') || 'Meal').toString();
			var price = parseFloat($input.data('product-price') || 0);
			total += price;
			items.push({ name: name, quantity: 1 });
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

		if (this.currentStep === 5) {
			this.renderSummary();
		}
	};

	MealPlansWizard.prototype.renderSummary = function () {
		var selectedMeals = [];

		this.$root.find('.restaurant-meal-select:checked').each(function () {
			var $input = $(this);
			selectedMeals.push({
				name: ($input.data('product-name') || 'Meal').toString(),
				price: parseFloat($input.data('product-price') || 0)
			});
		});

		var mealCardsHtml = '<p>-</p>';
		if (selectedMeals.length) {
			mealCardsHtml = '<div class="restaurant-selected-meals-cards">' + selectedMeals.map(function (meal) {
				return '<article class="restaurant-selected-meal-card">' +
					'<h4 class="restaurant-selected-meal-card__title">' + escapeHtml(meal.name) + '</h4>' +
					'<p class="restaurant-selected-meal-card__price">' + formatPrice(meal.price) + '</p>' +
				'</article>';
			}).join('') + '</div>';
		}

		var html = '';
		html += '<p><strong>Plan Type:</strong> ' + (this.state.planType || '-') + '</p>';
		html += '<p><strong>Meals Per Week:</strong> ' + (this.state.mealsPerWeek || '-') + '</p>';
		html += '<div class="restaurant-selected-meals-summary"><p><strong>Meals Selected:</strong> ' + selectedMeals.length + '</p>' + mealCardsHtml + '</div>';
		html += '<p><strong>Delivery Days:</strong> ' + (this.state.deliveryDays.join(', ') || '-') + '</p>';
		html += '<p><strong>Delivery Time:</strong> ' + (this.state.deliveryTime || '-') + '</p>';
		this.$summary.html(html);
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

