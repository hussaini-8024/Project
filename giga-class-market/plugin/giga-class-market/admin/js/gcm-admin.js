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
			course_id: $button.data('course-id') || 0,
			class_id: $button.data('class-id') || 0,
			coupon_id: $button.data('coupon-id') || 0,
			review_id: $button.data('review-id') || 0
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
				if (response.success && response.data && response.data.whatsapp_url) {
					window.open(response.data.whatsapp_url, '_blank', 'noopener');
				}
				if (response.success && action !== 'gcm_whatsapp_payment_reminder') {
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

	$(document).on('submit', '.gcm-ajax-form', function (event) {
		var $form = $(this);
		if ($form.hasClass('gcm-settings-form') || $form.hasClass('gcm-create-teacher-form') || $form.hasClass('gcm-set-teacher-password') || $form.hasClass('gcm-assign-teacher-courses') || $form.hasClass('gcm-admin-schedule-class')) {
			return;
		}
		event.preventDefault();
		var $message = $form.find('.gcm-form-message').first();
		var data = $form.serializeArray();
		var action = $form.data('action');
		if (action) {
			data.push({ name: 'action', value: action });
		}
		if (!$form.find('[name="nonce"]').length) {
			data.push({ name: 'nonce', value: gcmAdmin.nonce });
		}
		$message.text('Saving...');
		$.post(gcmAdmin.ajaxUrl, data)
			.done(function (response) {
				$message.text(response.data && response.data.message ? response.data.message : 'Done.');
				if (response.success) {
					window.setTimeout(function () {
						window.location.reload();
					}, 700);
				}
			})
			.fail(function (xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : 'Request failed.';
				$message.text(message);
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
				// Purge StackCDN/page cache so logged-out visitors see the new banner/settings.
				$.get(gcmAdmin.ajaxUrl, { action: 'purge-all' }).always(function () {
					$.get(gcmAdmin.ajaxUrl, { action: 'purge_all' });
				});
			})
			.fail(function (xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : 'Settings could not be saved.';
				$message.text(message);
			});
	});

	$(document).on('submit', '.gcm-create-teacher-form, .gcm-set-teacher-password, .gcm-assign-teacher-courses, .gcm-admin-schedule-class', function (event) {
		event.preventDefault();
		var $form = $(this);
		var $message = $form.find('.gcm-form-message').first();
		$message.text('Saving...');

		$.post(gcmAdmin.ajaxUrl, $form.serialize())
			.done(function (response) {
				$message.text(response.data && response.data.message ? response.data.message : 'Done.');
				if (response.success) {
					window.setTimeout(function () {
						window.location.reload();
					}, 700);
				}
			})
			.fail(function (xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : 'Request failed.';
				$message.text(message);
			});
	});

	var mediaFrame;

	$(document).on('click', '.gcm-media-upload', function (event) {
		event.preventDefault();
		var $button = $(this);
		var target = $button.data('target');
		var preview = $button.data('preview');

		if (!mediaFrame) {
			mediaFrame = wp.media({
				title: 'Select banner image',
				button: { text: 'Use this image' },
				multiple: false
			});
		}

		mediaFrame.off('select');
		mediaFrame.on('select', function () {
			var attachment = mediaFrame.state().get('selection').first().toJSON();
			$(target).val(attachment.id);
			var url = (attachment.sizes && attachment.sizes.large) ? attachment.sizes.large.url : attachment.url;
			$(preview).addClass('has-image').html('<img src="' + url + '" alt="" />');
		});

		mediaFrame.open();
	});

	$(document).on('click', '.gcm-media-clear', function (event) {
		event.preventDefault();
		var $button = $(this);
		$($button.data('target')).val('0');
		$($button.data('preview')).removeClass('has-image').html('<span>No banner selected</span>');
	});
})(jQuery);
