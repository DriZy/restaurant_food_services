(function ($) {
	'use strict';

	function updateCartCount(count) {
		var selectors = [
			'.cart-contents .count',
			'.site-header-cart .count',
			'.header-cart-count',
			'.restaurant-cart-count'
		];

		selectors.forEach(function (selector) {
			$(selector).text(count);
		});
	}

	function showToast(message, type) {
		var toastClass = 'restaurant-toast ' + (type || 'success');
		var $toast = $('<div/>', {
			class: toastClass,
			text: message
		});

		$('body').append($toast);

		window.requestAnimationFrame(function () {
			$toast.addClass('is-visible');
		});

		setTimeout(function () {
			$toast.removeClass('is-visible');
			setTimeout(function () {
				$toast.remove();
			}, 250);
		}, 2200);
	}

	function SummarySidebar($root) {
		this.$root = $root;
		this.$items = this.$root.find('.restaurant-summary-sidebar__items');
		this.$total = this.$root.find('.restaurant-summary-sidebar__total-value');
		this.$modal = null;
		this.$modalItems = null;
		this.$modalTotal = null;
		this.$trigger = null;

		this.initMobileModal();
	}

	SummarySidebar.prototype.isMobile = function () {
		return window.matchMedia('(max-width: 768px)').matches;
	};

	SummarySidebar.prototype.initMobileModal = function () {
		var self = this;
		this.$modal = $('<div class="restaurant-summary-modal" aria-hidden="true"><div class="restaurant-summary-modal__backdrop"></div><div class="restaurant-summary-modal__dialog"><button type="button" class="restaurant-summary-modal__close" aria-label="Close">&times;</button><h4>Summary</h4><ul class="restaurant-summary-sidebar__items"></ul><p class="restaurant-summary-sidebar__total"><strong>Total Price:</strong> <span class="restaurant-summary-sidebar__total-value">$0.00</span></p></div></div>');
		$('body').append(this.$modal);
		this.$modalItems = this.$modal.find('.restaurant-summary-sidebar__items');
		this.$modalTotal = this.$modal.find('.restaurant-summary-sidebar__total-value');

		this.$trigger = $('<button type="button" class="restaurant-summary-mobile-trigger">View Summary</button>');
		this.$root.before(this.$trigger);

		this.$trigger.on('click', function () {
			if (!self.isMobile()) {
				return;
			}
			self.$modal.addClass('is-open').attr('aria-hidden', 'false');
		});

		this.$modal.on('click', '.restaurant-summary-modal__close, .restaurant-summary-modal__backdrop', function () {
			self.$modal.removeClass('is-open').attr('aria-hidden', 'true');
		});
	};

	SummarySidebar.prototype.update = function (payload) {
		var items = (payload && payload.items) ? payload.items : [];
		var totalHtml = (payload && payload.totalHtml) ? payload.totalHtml : '$0.00';

		this.$items.empty();

		if (!items.length) {
			this.$items.append($('<li/>', { text: 'No selections yet.' }));
		} else {
			items.forEach(function (item) {
				var label = (item.name || 'Item') + ' x ' + (item.quantity || 1);
				this.$items.append($('<li/>', { text: label }));
			}, this);
		}

		this.$total.html(totalHtml);
		if (this.$modalItems && this.$modalTotal) {
			this.$modalItems.html(this.$items.html());
			this.$modalTotal.html(totalHtml);
		}
	};

	function applyFilters($scope) {
		var searchTerm = (($scope.find('[data-filter-search]').val() || '').toString()).toLowerCase();
		var spiceValue = (($scope.find('[data-filter-spice]').val() || '').toString()).toLowerCase();
		var $grid = $scope.nextAll('.restaurant-meals-grid').first();

		if (!$grid.length) {
			$grid = $scope.closest('.restaurant-wizard-panel, .restaurant-order-meals').find('.restaurant-meals-grid').first();
		}

		$grid.find('.restaurant-meal-card').each(function () {
			var $card = $(this);
			var name = ($card.data('product-name') || '').toString();
			var spice = ($card.data('spice-level') || '').toString();
			var matchSearch = !searchTerm || name.indexOf(searchTerm) !== -1;
			var matchSpice = !spiceValue || spice === spiceValue;
			$card.toggle(matchSearch && matchSpice);
		});
	}

	function initCollapsibleFilters() {
		$(document).on('click', '.restaurant-filter-toggle', function () {
			var $btn = $(this);
			var $filters = $btn.closest('.restaurant-filters');
			$filters.toggleClass('is-open');
			$btn.attr('aria-expanded', $filters.hasClass('is-open') ? 'true' : 'false');
		});

		$(document).on('input change', '.restaurant-filters [data-filter-search], .restaurant-filters [data-filter-spice]', function () {
			applyFilters($(this).closest('.restaurant-filters'));
		});
	}

	function initMobileStickyCta() {
		$(document).on('click', '.restaurant-mobile-cta__button', function () {
			var action = (($(this).data('action') || '').toString());
			if ('add-to-cart' === action) {
				var $target = $('.restaurant-order-meals .restaurant-ajax-add-to-cart:visible').first();
				if ($target.length) {
					$target.trigger('click');
				}
				return;
			}

			if ('start-plan' === action) {
				var $wizard = $('.restaurant-meal-plans-wizard').first();
				if ($wizard.length) {
					$('html, body').animate({ scrollTop: $wizard.offset().top - 20 }, 250);
				}
				return;
			}

			if ('request-catering' === action) {
				var $catering = $('.restaurant-catering-wizard').first();
				if ($catering.length) {
					$('html, body').animate({ scrollTop: $catering.offset().top - 20 }, 250);
				}
			}
		});
	}

	window.RestaurantFoodServicesUI = window.RestaurantFoodServicesUI || {};
	window.RestaurantFoodServicesUI.showToast = showToast;
	window.RestaurantFoodServicesUI.updateCartCount = updateCartCount;
	window.RestaurantFoodServicesUI.SummarySidebar = SummarySidebar;

	initCollapsibleFilters();
	initMobileStickyCta();

	$(document).on('click', '.restaurant-ajax-add-to-cart', function (event) {
		event.preventDefault();

		var $button = $(this);
		var productType = ($button.data('product_type') || '').toString();

		if (productType && productType !== 'simple') {
			window.location.href = $button.closest('.restaurant-meal-card').find('.restaurant-meal-card__title a').attr('href') || '#';
			return;
		}

		if (!window.RestaurantFoodServicesPublic || !RestaurantFoodServicesPublic.ajaxUrl) {
			showToast('Unable to add item to cart right now.', 'error');
			return;
		}

		if ($button.hasClass('loading')) {
			return;
		}

		$button.addClass('loading');

		$.ajax({
			url: RestaurantFoodServicesPublic.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'restaurant_add_meal_to_cart',
				nonce: RestaurantFoodServicesPublic.nonce,
				product_id: $button.data('product_id'),
				quantity: $button.data('quantity') || 1
			}
		})
			.done(function (response) {
				if (response && response.success) {
					updateCartCount(response.data.cart_count || 0);
					showToast(response.data.message || 'Added to cart.', 'success');
					$(document.body).trigger('wc_fragment_refresh');
					return;
				}

				showToast((response && response.data && response.data.message) ? response.data.message : 'Unable to add item.', 'error');
			})
			.fail(function () {
				showToast('Unable to add item to cart right now.', 'error');
			})
			.always(function () {
				$button.removeClass('loading');
			});
	});
})(jQuery);

