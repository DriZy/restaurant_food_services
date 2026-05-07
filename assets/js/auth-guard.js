(function ($) {
	'use strict';

	/**
	 * Authentication guard for wizards
	 * Prevents non-authenticated users from submitting catering or meal plan requests
	 * and redirects them to the signup page with state preservation
	 */
	window.RestaurantAuthGuard = window.RestaurantAuthGuard || {
		/**
		 * Check if user is authenticated
		 */
		isUserAuthenticated: function() {
			var cfg = window.RestaurantFoodServicesPublic || {};
			// Support multiple localized keys used across the codebase
			return cfg.isUserLoggedIn === true || cfg.isUserLoggedIn === '1' || cfg.isLoggedIn === true || cfg.isLoggedIn === '1' || cfg.is_logged_in === true || cfg.is_logged_in === '1';
		},

		/**
		 * Save wizard state to localStorage for restoration after login
		 */
		saveWizardState: function(wizardType, payload) {
			try {
				// Accept either raw state or an object { state, step }
				var store = payload || {};
				localStorage.setItem('restaurant_' + wizardType + '_state', JSON.stringify(store));
				return true;
			} catch (e) {
				console.error('Failed to save wizard state:', e);
				return false;
			}
		},

		/**
		 * Get saved wizard state from localStorage
		 */
		getWizardState: function(wizardType) {
			try {
				var saved = localStorage.getItem('restaurant_' + wizardType + '_state');
				return saved ? JSON.parse(saved) : null;
			} catch (e) {
				console.error('Failed to retrieve wizard state:', e);
				return null;
			}
		},

		/**
		 * Clear saved wizard state
		 */
		clearWizardState: function(wizardType) {
			try {
				localStorage.removeItem('restaurant_' + wizardType + '_state');
				return true;
			} catch (e) {
				return false;
			}
		},

		/**
		 * Redirect to authentication with state preservation
		 */
		redirectToAuth: function(stateData, wizardType) {
			// Always redirect to /sign-up
			var signupPageUrl = '/sign-up';

			// Save state for restoration after login
			if (stateData && wizardType) {
				this.saveWizardState(wizardType, stateData);

				// Add redirect parameters to signup page to restore state
				var separator = signupPageUrl.indexOf('?') > -1 ? '&' : '?';
				signupPageUrl = signupPageUrl + separator + 'restaurant_wizard_type=' + encodeURIComponent(wizardType) + '&restaurant_form_redirect=1';
				// Optionally include step if present
				if (stateData.step) {
					signupPageUrl += '&restaurant_wizard_step=' + encodeURIComponent(stateData.step);
				}
				// Include redirect_to parameter to return to current page after auth
				signupPageUrl += '&redirect_to=' + encodeURIComponent(window.location.href);
			}

			window.location.href = signupPageUrl;
		},

		/**
		 * Check if user is an admin
		 */
		isUserAdmin: function() {
			var cfg = window.RestaurantFoodServicesPublic || {};
			// Support multiple localized keys used across the codebase
			return cfg.isUserAdmin === true || cfg.isUserAdmin === '1' || cfg.is_user_admin === true || cfg.is_user_admin === '1' || cfg.isAdmin === true || cfg.isAdmin === '1';
		},

		/**
		 * Show authentication modal/overlay
		 */
		showAuthModal: function(wizardType, currentStep) {
			var html = '<div class="restaurant-auth-modal-overlay" id="restaurant-auth-modal-overlay">' +
				'<div class="restaurant-auth-modal">' +
					'<button type="button" class="restaurant-auth-modal__close" id="restaurant-auth-modal-close">&times;</button>' +
					'<div class="restaurant-auth-modal__content">' +
						'<h3>Sign in to Continue</h3>' +
						'<p>Please sign in or create an account to complete your ' + (wizardType === 'catering' ? 'catering request' : 'meal plan subscription') + '.</p>' +
						'<p>We\'ll bring you right back to where you left off.</p>' +
						'<a href="#" class="button button-primary restaurant-auth-modal__button" id="restaurant-auth-modal-signin">' +
							'Sign in / Sign up' +
						'</a>' +
					'</div>' +
				'</div>' +
			'</div>';

			var $modal = $(html);
			$('body').append($modal);

			var self = this;
			$modal.on('click', '#restaurant-auth-modal-close, .restaurant-auth-modal-overlay', function(e) {
				if (e.target.id === 'restaurant-auth-modal-close' || e.target.id === 'restaurant-auth-modal-overlay') {
					$modal.fadeOut(300, function() { $(this).remove(); });
				}
			});

			$modal.on('click', '#restaurant-auth-modal-signin', function(e) {
				e.preventDefault();
				var state = {
					state: (window.currentWizardState || null),
					step: currentStep || 1,
					timestamp: Date.now()
				};
				self.redirectToAuth(state, wizardType);
			});

			$modal.fadeIn(300);
		}
	};

	// Check for restoration after login
	$(function() {
		var params = new URLSearchParams(window.location.search);
		var wizardType = params.get('restaurant_wizard_type');
		var shouldRestore = params.get('restaurant_form_redirect') === '1';
		var authGuard = window.RestaurantAuthGuard;

		// Handle /wp-admin redirect for logged-in admins
		var currentPath = window.location.pathname;
		if (currentPath === '/wp-admin' || currentPath === '/wp-admin/') {
			if (authGuard && authGuard.isUserAuthenticated() && authGuard.isUserAdmin()) {
				// Redirect to WordPress admin dashboard
				window.location.href = (window.RestaurantFoodServicesPublic && window.RestaurantFoodServicesPublic.wpAdminUrl) || '/wp-admin/';
				return;
			}
		}

		if (shouldRestore && wizardType) {
			// Remove URL parameters to clean up
			var newUrl = window.location.pathname;
			window.history.replaceState({}, document.title, newUrl);

			// Trigger restoration event for wizards to listen to
			$(document).trigger('restaurant_restore_wizard_state', [wizardType]);
		}

		// Auto-restore saved state if user is authenticated, even if no URL params present
		if (authGuard && authGuard.isUserAuthenticated()) {
			// Check for any saved wizard states and trigger restoration
			var wizardTypes = ['catering', 'meal_plans'];
			wizardTypes.forEach(function(type) {
				var saved = authGuard.getWizardState(type);
				if (saved) {
					// Trigger restoration event for wizards to listen to
					$(document).trigger('restaurant_restore_wizard_state', [type]);
				}
			});
		}
	});

})(jQuery);
