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
	}

	SummarySidebar.prototype.update = function (payload) {
		var items = (payload && payload.items) ? payload.items : [];
		var totalHtml = (payload && payload.totalHtml) ? payload.totalHtml : '$0.00';
		var detailsHtml = (payload && payload.detailsHtml) ? payload.detailsHtml : '';

		this.$items.empty();

		if (detailsHtml) {
			this.$items.append($('<li/>', {
				class: 'restaurant-summary-sidebar__details',
				html: detailsHtml
			}));
		}

		if (!items.length) {
			if (!detailsHtml) {
				this.$items.append($('<li/>', { text: 'No selections yet.' }));
			}
		} else {
			items.forEach(function (item) {
				var label = (item.name || 'Item') + ' x ' + (item.quantity || 1);
				this.$items.append($('<li/>', { text: label }));
			}, this);
		}

		this.$total.html(totalHtml);
	};

	function applyFilters($scope) {
		var searchTerm = (($scope.find('[data-filter-search]').val() || '').toString()).toLowerCase();
		var spiceValue = (($scope.find('[data-filter-spice]').val() || '').toString()).toLowerCase();
		var $grid = $scope.nextAll('.restaurant-catering-menu-list, .restaurant-meals-grid').first();

		if (!$grid.length) {
			$grid = $scope.closest('.restaurant-wizard-panel, .restaurant-order-meals, .restaurant-meal-plans-wizard, .restaurant-catering-wizard').find('.restaurant-catering-menu-list, .restaurant-meals-grid').first();
		}

		if (!$grid.length) {
			return;
		}

		// First, hide all cards and mark those that match the filter
		$grid.find('.restaurant-meal-card').each(function () {
			var $card = $(this);
			var name = ($card.data('product-name') || '').toString();
			var spice = ($card.data('spice-level') || '').toString();
			var matchSearch = !searchTerm || name.indexOf(searchTerm) !== -1;
			var matchSpice = !spiceValue || spice === spiceValue;
			$card.data('matches-filter', matchSearch && matchSpice);
			$card.hide();
		});

		// Determine which lists to process (course groups or the whole grid)
		var $lists = $grid.find('.restaurant-meal-course-items');
		if (!$lists.length) {
			$lists = $grid;
		}

		var isWizard = $scope.closest('.restaurant-meal-plans-wizard, .restaurant-catering-wizard').length > 0;

		$lists.each(function () {
			var $list = $(this);
			var $matchingItems = $list.find('.restaurant-meal-card').filter(function () {
				return $(this).data('matches-filter');
			});

			if (isWizard) {
				var currentlyShown = $list.data('currently-shown') || 5;
				$matchingItems.slice(0, currentlyShown).show();

				var $loadMoreContainer = $list.next('.restaurant-load-more-container');
				if ($loadMoreContainer.length) {
					if ($matchingItems.length > currentlyShown) {
						$loadMoreContainer.show();
					} else {
						$loadMoreContainer.hide();
					}
				}
			} else {
				$matchingItems.show();
			}
		});

		// Hide empty groups
		$grid.find('.restaurant-meal-course-group').each(function () {
			var $group = $(this);
			var hasMatchingItems = $group.find('.restaurant-meal-card').filter(function () {
				return $(this).data('matches-filter');
			}).length > 0;
			$group.toggle(hasMatchingItems);
		});
	}

	function initPagination() {
		$(document).on('click', '.restaurant-load-more-button', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $container = $btn.closest('.restaurant-load-more-container');
			var $list = $container.prev();

			var currentlyShown = $list.data('currently-shown') || 5;
			var nextShown = currentlyShown + 5;
			$list.data('currently-shown', nextShown);

			// Re-apply filters with updated "currently-shown"
			var $scope = $list.closest('.restaurant-wizard-panel, .restaurant-order-meals, .restaurant-meal-plans-wizard, .restaurant-catering-wizard').find('.restaurant-filters');
			if ($scope.length) {
				applyFilters($scope.first());
			}
		});

		// Initialize on load
		$('.restaurant-filters').each(function () {
			applyFilters($(this));
		});

		// Re-initialize when wizard steps change
		$(document).on('click', '.restaurant-wizard-next, .restaurant-wizard-prev', function () {
			setTimeout(function() {
				$('.restaurant-filters').each(function () {
					applyFilters($(this));
				});
			}, 10);
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

	function initAuthTabSwitchers() {
		function setActiveTab($switcher, tabName) {
			var targetTab = (tabName || '').toString();
			var $tabs = $switcher.find('[data-auth-target]');
			var $panels = $switcher.find('.restaurant-account-auth__panel');

			if (!targetTab) {
				targetTab = $tabs.filter('.is-active').data('auth-target') || $tabs.first().data('auth-target') || 'signup';
			}

			$tabs.each(function () {
				var $tab = $(this);
				var isActive = ($tab.data('auth-target') || '').toString() === targetTab;
				$tab.toggleClass('is-active', isActive);
				$tab.attr('aria-selected', isActive ? 'true' : 'false');
			});

			$panels.each(function () {
				var $panel = $(this);
				var isActivePanel = ($panel.data('auth-panel') || '').toString() === targetTab;
				if (isActivePanel) {
					$panel.removeAttr('hidden');
				} else {
					$panel.attr('hidden', 'hidden');
				}
			});
		}

		$(document).on('click', '[data-auth-switcher] [data-auth-target]', function () {
			var $tab = $(this);
			var $switcher = $tab.closest('[data-auth-switcher]');

			if (!$switcher.length) {
				return;
			}

			setActiveTab($switcher, $tab.data('auth-target'));
		});

		$('[data-auth-switcher]').each(function () {
			var $switcher = $(this);
			setActiveTab($switcher, $switcher.data('auth-default-tab'));
		});
	}

	window.RestaurantFoodServicesUI = window.RestaurantFoodServicesUI || {};
	window.RestaurantFoodServicesUI.showToast = showToast;
	window.RestaurantFoodServicesUI.updateCartCount = updateCartCount;
	window.RestaurantFoodServicesUI.SummarySidebar = SummarySidebar;

	initCollapsibleFilters();
	initMobileStickyCta();
	initAuthTabSwitchers();
	initPagination();

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

