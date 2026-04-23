(function ($) {
	'use strict';

	if (typeof RestaurantFoodServicesCateringSettings === 'undefined') {
		return;
	}

	var $modal;

	function ensureModal() {
		$modal = $('.restaurant-catering-settings-modal');

		if ($modal.length) {
			return $modal;
		}

		return $modal;
	}

	function renderNotice(message, type) {
		var $container = $('.restaurant-catering-settings-notices');
		var safeMessage = $('<div />').text(message || '').html();
		var noticeClass = 'notice-error';

		if ('success' === type) {
			noticeClass = 'notice-success';
		}

		$container.html('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + safeMessage + '</p></div>');
	}

	function openRenameModal($button) {
		var $form = $button.closest('form');
		var $modalForm;

		ensureModal();
		$modalForm = $modal.find('.restaurant-catering-settings-modal__form');

		$modalForm.find('input[name="option_name"]').val($button.data('option-name'));
		$modalForm.find('input[name="option_key"]').val($button.data('option-key'));
		$modalForm.find('input[name="rename_label"]').val($button.data('option-label'));
		$modalForm.find('input[name="offering_page"]').val($button.data('offering-page') || $form.find('input[name="offering_page"]').val() || '1');
		$modalForm.find('input[name="service_page"]').val($button.data('service-page') || $form.find('input[name="service_page"]').val() || '1');
		$modalForm.find('input[name="course_page"]').val($button.data('course-page') || $form.find('input[name="course_page"]').val() || '1');

		$modal.prop('hidden', false).addClass('is-open');
		$modal.find('#restaurant-catering-settings-modal-input').trigger('focus').trigger('select');
	}

	function closeRenameModal() {
		if (!$modal || !$modal.length) {
			return;
		}

		$modal.prop('hidden', true).removeClass('is-open');
	}

	function sendAjaxForm($form) {
		var $section = $form.closest('.restaurant-catering-settings-section');
		var isModal = $form.closest('.restaurant-catering-settings-modal').length > 0;
		var optionName = $form.find('input[name="option_name"]').val();
		var $button = $form.find('button[type="submit"], button.button-primary').first();
		var data = $form.serializeArray();
		var request;

		if (!$section.length && optionName) {
			$section = $('.restaurant-catering-settings-section[data-option-name="' + optionName + '"]');
		}

		data.push({ name: 'action', value: 'restaurant_catering_settings_option_action' });

		$button.prop('disabled', true);

		request = $.ajax({
			url: RestaurantFoodServicesCateringSettings.ajaxUrl,
			method: 'POST',
			data: $.param(data),
			dataType: 'json'
		})
			.done(function (response) {
				if (!response || !response.data) {
					renderNotice(RestaurantFoodServicesCateringSettings.errorMessage, 'error');
					return;
				}

				if (response.data.sectionHtml) {
					$section.replaceWith(response.data.sectionHtml);
				}

				renderNotice(response.data.message || RestaurantFoodServicesCateringSettings.errorMessage, response.success ? 'success' : 'error');

				if (isModal && response.success) {
					closeRenameModal();
				}
			})
			.fail(function (xhr) {
				var message = RestaurantFoodServicesCateringSettings.errorMessage;

				if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					message = xhr.responseJSON.data.message;
				}

				renderNotice(message, 'error');
			})
			.always(function () {
				$button.prop('disabled', false);
			});

		return request;
	}

	$(document).on('submit', '.restaurant-catering-settings-section form', function (event) {
		event.preventDefault();
		sendAjaxForm($(this));
	});

	$(document).on('submit', '.restaurant-catering-settings-row-actions', function (event) {
		if (
			$(this).find('input[name="operation"]').val() === 'delete' &&
			typeof RestaurantFoodServicesCateringSettings.confirmDelete === 'string' &&
			!window.confirm(RestaurantFoodServicesCateringSettings.confirmDelete)
		) {
			event.preventDefault();
			return;
		}
	});

	$(document).on('click', '.restaurant-catering-settings-rename-button', function (event) {
		event.preventDefault();
		openRenameModal($(this));
	});

	$(document).on('click', '[data-catering-modal-close]', function (event) {
		event.preventDefault();
		closeRenameModal();
	});

	$(document).on('keydown', function (event) {
		if (event.key === 'Escape') {
			closeRenameModal();
		}
	});

	$(document).on('click', '.restaurant-catering-settings-modal', function (event) {
		if ($(event.target).hasClass('restaurant-catering-settings-modal')) {
			closeRenameModal();
		}
	});

	$(document).on('submit', '.restaurant-catering-settings-modal__form', function (event) {
		event.preventDefault();

		sendAjaxForm($(this));
	});
})(jQuery);

