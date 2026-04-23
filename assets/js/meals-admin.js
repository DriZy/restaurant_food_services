(function ($) {
	'use strict';

	function isMealPlanEnabled() {
		var $field = $('#is_meal_plan');

		if ($field.length) {
			return $field.is(':checked');
		}

		return false;
	}

	function toggleMealPlanFields() {
		var shouldShow = isMealPlanEnabled();
		$('.show_if_meal_plan').toggle(shouldShow);
	}

	$(function () {
		toggleMealPlanFields();
		$(document).on('change', '#is_meal_plan', toggleMealPlanFields);

		// Keep compatibility with dynamic admin UIs that mount fields after load.
		if (window.MutationObserver) {
			var observer = new MutationObserver(function () {
				toggleMealPlanFields();
			});

			observer.observe(document.body, {
				childList: true,
				subtree: true
			});
		}
	});
})(jQuery);

