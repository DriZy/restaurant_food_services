(function ($) {
	'use strict';

	$(function () {
		var $container = $('#restaurant-catering-requests-container');

		if (!$container.length) {
			return;
		}

		$container.on('click', '.restaurant-pagination a', function (e) {
			e.preventDefault();

			var $link = $(this);
			var href = $link.attr('href');
			var page = 1;

			// Extract page number from href (format #%#% or ?paged=%#%)
			var matches = href.match(/paged=(\d+)/);
			if (matches && matches[1]) {
				page = parseInt(matches[1], 10);
			} else {
				// Try hash format
				matches = href.match(/#(\d+)/);
				if (matches && matches[1]) {
					page = parseInt(matches[1], 10);
				}
			}

			loadRequests(page);
		});

		function loadRequests(page) {
			var cfg = window.RestaurantFoodServicesPublic || {};
			var nonce = $container.data('nonce') || cfg.cateringRequestsNonce;

			if (!cfg.ajaxUrl || !nonce) {
				return;
			}

			$container.addClass('is-loading').css('opacity', 0.5);

			$.ajax({
				url: cfg.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'restaurant_get_catering_requests',
					nonce: nonce,
					paged: page
				}
			})
				.done(function (response) {
					if (response && response.success && response.data && response.data.html) {
						$container.html(response.data.html);
						
						// Scroll to top of container
						$('html, body').animate({
							scrollTop: $container.offset().top - 100
						}, 300);
					}
				})
				.fail(function () {
					console.error('Failed to load catering requests.');
				})
				.always(function () {
					$container.removeClass('is-loading').css('opacity', 1);
				});
		}
	});
})(jQuery);
