(function ($) {
	'use strict';

	function showNotice(message, success) {
		var klass = success ? 'notice-success' : 'notice-error';
		$('.gcm-admin-notice').remove();
		$('.gcm-admin-wrap').first().prepend('<div class="notice ' + klass + ' gcm-admin-notice is-dismissible"><p>' + $('<div>').text(message).html() + '</p></div>');
	}

	$(document).on('click', '.gcm-tabs a', function (event) {
		event.preventDefault();
		var target = $(this).attr('href');
		$('.gcm-tabs a, .gcm-tab').removeClass('active');
		$(this).addClass('active');
		$(target).addClass('active');
	});

	$(document).on('click', '.gcm-ajax-button', function (event) {
		event.preventDefault();
		var $button = $(this);
		var action = $button.data('action');
		var payload = {
			action: action,
			nonce: gcmAdmin.nonce,
			payment_id: $button.data('payment-id') || 0,
			user_id: $button.data('user-id') || 0,
			course_id: $button.data('course-id') || 0
		};

		if ($button.hasClass('gcm-reject-payment')) {
			var reason = window.prompt('Enter rejection reason:');
			if (reason === null) {
				return;
			}
			payload.reason = reason;
		}

		$button.prop('disabled', true);
		$.post(gcmAdmin.ajaxUrl, payload)
			.done(function (response) {
				showNotice(response.data && response.data.message ? response.data.message : 'Done.', response.success);
				if (response.success) {
					window.setTimeout(function () {
						window.location.reload();
					}, 700);
				}
			})
			.fail(function (xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : 'Request failed.';
				showNotice(message, false);
			})
			.always(function () {
				$button.prop('disabled', false);
			});
	});

	$(document).on('change', '.gcm-contact-status', function () {
		var $select = $(this);
		$.post(gcmAdmin.ajaxUrl, {
			action: 'gcm_update_contact_status',
			nonce: gcmAdmin.nonce,
			contact_id: $select.data('contact-id'),
			status: $select.val()
		}).done(function (response) {
			showNotice(response.data && response.data.message ? response.data.message : 'Status updated.', response.success);
		}).fail(function (xhr) {
			var message = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : 'Status update failed.';
			showNotice(message, false);
		});
	});

	$(document).on('submit', '.gcm-settings-form', function (event) {
		event.preventDefault();
		var $form = $(this);
		var $message = $form.find('.gcm-form-message');
		$message.text('Saving...');

		$.post(gcmAdmin.ajaxUrl, $form.serialize())
			.done(function (response) {
				$message.text(response.data && response.data.message ? response.data.message : 'Settings saved.');
			})
			.fail(function (xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : 'Settings could not be saved.';
				$message.text(message);
			});
	});
})(jQuery);
