(function() {
	'use strict';

	// Ensure globals exist
	if (typeof RestaurantFoodServicesCateringChat === 'undefined') {
		console.log('RestaurantFoodServicesCateringChat globals not found');
		return;
	}

	const ajaxUrl = RestaurantFoodServicesCateringChat.ajaxUrl;
	const nonce = RestaurantFoodServicesCateringChat.nonce;

	/**
	 * Initialize AJAX chat handlers when DOM is ready
	 */
	function initCateringChatAjax() {
		// Find all catering chat forms
		const chatForms = document.querySelectorAll('.restaurant-catering-chat-form');

		if (chatForms.length === 0) {
			console.log('No catering chat forms found');
			return;
		}

		chatForms.forEach(function(form) {
			setUpFormHandler(form);
		});
	}

	/**
	 * Set up AJAX handler for a single form
	 */
	function setUpFormHandler(form) {
		form.addEventListener('submit', handleFormSubmit);
	}

	/**
	 * Handle form submission via AJAX
	 */
	function handleFormSubmit(e) {
		e.preventDefault();

		const form = e.target;
		const messageinput = form.querySelector('textarea[name="comment"]');
		const messageText = messageinput ? messageinput.value.trim() : '';

		// Validate message
		if (!messageText) {
			showErrorMessage(form, 'Message cannot be empty');
			return;
		}

		// Find the post ID from the form
		const postIdInput = form.querySelector('input[name="comment_post_ID"]');
		const postId = postIdInput ? parseInt(postIdInput.value, 10) : 0;

		if (!postId || postId <= 0) {
			showErrorMessage(form, 'Invalid catering request');
			return;
		}

		// Show loading state
		const submitButton = form.querySelector('button[type="submit"]');
		const originalButtonText = submitButton ? submitButton.textContent : 'Send Message';
		if (submitButton) {
			submitButton.disabled = true;
			submitButton.textContent = 'Sending...';
		}

		// Send AJAX request
		sendMessageViaAjax(postId, messageText, form, function(success, response) {
			// Restore button state
			if (submitButton) {
				submitButton.disabled = false;
				submitButton.textContent = originalButtonText;
			}

			if (success) {
				// Clear the textarea
				if (messageinput) {
					messageinput.value = '';
				}

				// Add the new message to the chat
				addMessageToChat(form, response.data.commentHtml);

				// Show success message
				showSuccessMessage(form, 'Message sent successfully');
			} else {
				// Show error message
				const errorMessage = response.data && response.data.message
					? response.data.message
					: 'Failed to send message. Please try again.';
				showErrorMessage(form, errorMessage);
			}
		});
	}

	/**
	 * Send message via AJAX
	 */
	function sendMessageViaAjax(postId, message, form, callback) {
		const formData = new FormData();
		formData.append('action', 'restaurant_catering_send_message');
		formData.append('nonce', nonce);
		formData.append('post_id', postId);
		formData.append('message', message);

		fetch(ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				callback(true, data);
			} else {
				callback(false, data);
			}
		})
		.catch(error => {
			console.error('AJAX Error:', error);
			callback(false, {
				data: {
					message: 'An error occurred. Please try again.'
				}
			});
		});
	}

	/**
	 * Add new message to the chat display
	 */
	function addMessageToChat(form, commentHtml) {
		// Find the messages container
		const chatPanel = form.closest('.restaurant-catering-chat');
		if (!chatPanel) {
			console.error('Could not find chat panel');
			return;
		}

		const messagesContainer = chatPanel.querySelector('.restaurant-catering-chat-messages');
		if (!messagesContainer) {
			console.error('Could not find messages container');
			return;
		}

		// Remove empty message if it exists
		const emptyMessage = messagesContainer.querySelector('.restaurant-catering-chat-empty');
		if (emptyMessage) {
			emptyMessage.remove();
		}

		// Insert the new message HTML
		messagesContainer.insertAdjacentHTML('beforeend', commentHtml);

		// Scroll to bottom
		messagesContainer.scrollTop = messagesContainer.scrollHeight;
	}

	/**
	 * Show error message to user
	 */
	function showErrorMessage(form, message) {
		// Remove existing notification
		removeNotification(form);

		// Create error notification
		const notification = document.createElement('div');
		notification.className = 'restaurant-catering-chat-notification restaurant-catering-chat-notification--error';
		notification.setAttribute('role', 'alert');
		notification.textContent = message;

		// Insert before form
		form.parentNode.insertBefore(notification, form);

		// Auto remove after 5 seconds
		setTimeout(function() {
			notification.remove();
		}, 5000);
	}

	/**
	 * Show success message to user
	 */
	function showSuccessMessage(form, message) {
		// Remove existing notification
		removeNotification(form);

		// Create success notification
		const notification = document.createElement('div');
		notification.className = 'restaurant-catering-chat-notification restaurant-catering-chat-notification--success';
		notification.setAttribute('role', 'status');
		notification.textContent = message;

		// Insert before form
		form.parentNode.insertBefore(notification, form);

		// Auto remove after 3 seconds
		setTimeout(function() {
			notification.remove();
		}, 3000);
	}

	/**
	 * Remove existing notifications
	 */
	function removeNotification(form) {
		const parent = form.parentNode;
		const existingNotification = parent.querySelector('.restaurant-catering-chat-notification');
		if (existingNotification) {
			existingNotification.remove();
		}
	}

	/**
	 * Add CSS styles for notifications
	 */
	function addNotificationStyles() {
		if (document.getElementById('restaurant-catering-chat-ajax-styles')) {
			return; // Already added
		}

		const style = document.createElement('style');
		style.id = 'restaurant-catering-chat-ajax-styles';
		style.textContent = `
			.restaurant-catering-chat-notification {
				padding: 12px 16px;
				margin-bottom: 12px;
				border-radius: 4px;
				font-size: 14px;
				animation: slideDown 0.3s ease;
			}

			@keyframes slideDown {
				from {
					opacity: 0;
					transform: translateY(-10px);
				}
				to {
					opacity: 1;
					transform: translateY(0);
				}
			}

			.restaurant-catering-chat-notification--success {
				background-color: #d4edda;
				border: 1px solid #c3e6cb;
				color: #155724;
			}

			.restaurant-catering-chat-notification--error {
				background-color: #f8d7da;
				border: 1px solid #f5c6cb;
				color: #721c24;
			}

			.restaurant-catering-chat-form button[type="submit"]:disabled {
				opacity: 0.6;
				cursor: not-allowed;
			}
		`;
		document.head.appendChild(style);
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			addNotificationStyles();
			initCateringChatAjax();
		});
	} else {
		addNotificationStyles();
		initCateringChatAjax();
	}

	// Reinitialize on dynamic content loading (for admin post editor)
	if (typeof window.wp !== 'undefined' && typeof window.wp.hooks !== 'undefined') {
		window.wp.hooks.addAction('metabox.post.catering_chat_thread', 'restaurantFoodServices', function() {
			initCateringChatAjax();
		});
	}

})();

