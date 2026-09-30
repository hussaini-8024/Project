(function () {
	'use strict';

	function serializeForm(form) {
		return new FormData(form);
	}

	function setMessage(form, message, success) {
		var target = form.querySelector('.gcm-form-message');
		if (!target) {
			return;
		}
		target.textContent = message;
		target.classList.toggle('success', !!success);
		target.classList.toggle('error', !success);
	}

	function ajaxForm(form) {
		var formData = serializeForm(form);
		formData.append('action', form.getAttribute('data-action'));
		formData.append('gcm_ajax', '1');
		if (!formData.get('nonce') && window.gcmPublic) {
			formData.append('nonce', window.gcmPublic.nonce);
		}

		setMessage(form, 'Submitting...', true);
		var url = (window.gcmPublic && gcmPublic.ajaxUrl) || (window.gcmTheme && gcmTheme.formUrl) || (window.gcmPublic && gcmPublic.adminAjax);
		fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		}).then(function (response) {
			return response.json();
		}).then(function (json) {
			var message = json.data && json.data.message ? json.data.message : 'Request complete.';
			setMessage(form, message, json.success);
			if (json.success && form.classList.contains('gcm-contact-form')) {
				form.reset();
			}
			if (json.success && (form.classList.contains('gcm-teacher-form') || form.getAttribute('data-action') === 'gcm_send_course_message')) {
				window.setTimeout(function () {
					window.location.reload();
				}, 600);
			}
			if (json.success && json.data && json.data.start_url && form.getAttribute('data-action') === 'gcm_start_class') {
				window.open(json.data.start_url, '_blank', 'noopener');
			}
		}).catch(function () {
			setMessage(form, 'Request failed. Please try again.', false);
		});
	}

	document.addEventListener('click', function (event) {
		var joinBtn = event.target.closest('.gcm-join-live');
		if (!joinBtn || !window.gcmPublic) {
			return;
		}
		event.preventDefault();
		var data = new URLSearchParams();
		data.append('action', 'gcm_join_live_class');
		data.append('nonce', window.gcmPublic.nonce);
		data.append('class_id', joinBtn.getAttribute('data-class-id'));
		joinBtn.disabled = true;
		fetch(window.gcmPublic.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: data.toString()
		}).then(function (response) {
			return response.json();
		}).then(function (json) {
			if (json.success && json.data && json.data.join_url) {
				// Same-tab navigation avoids popup blockers and broken blank tabs.
				window.location.assign(json.data.join_url);
				return;
			}
			window.alert((json.data && json.data.message) || 'Unable to join class.');
			joinBtn.disabled = false;
		}).catch(function () {
			window.alert('Unable to join class.');
			joinBtn.disabled = false;
		});
	});

	document.addEventListener('click', function (event) {
		var button = event.target.closest('.gcm-teacher-action');
		if (!button || !window.gcmPublic) {
			return;
		}
		event.preventDefault();
		var data = new URLSearchParams();
		data.append('action', button.getAttribute('data-action'));
		data.append('nonce', window.gcmPublic.nonce);
		if (button.getAttribute('data-class-id')) {
			data.append('class_id', button.getAttribute('data-class-id'));
			var row = button.closest('li, tr, .gcm-teacher-class');
			var passInput = row ? row.querySelector('.gcm-host-passcode') : null;
			if (passInput && passInput.value) {
				data.append('passcode', passInput.value);
			}
		}
		if (button.getAttribute('data-note-id')) {
			data.append('note_id', button.getAttribute('data-note-id'));
		}
		if (button.getAttribute('data-recording-id')) {
			data.append('recording_id', button.getAttribute('data-recording-id'));
		}
		if (button.getAttribute('data-announcement-id')) {
			data.append('announcement_id', button.getAttribute('data-announcement-id'));
		}
		button.disabled = true;
		fetch(window.gcmPublic.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: data.toString()
		}).then(function (response) {
			return response.json();
		}).then(function (json) {
			if (!json.success) {
				window.alert((json.data && json.data.message) || 'Request failed.');
				button.disabled = false;
				return;
			}
			var meetingUrl = (json.data && (json.data.start_url || json.data.join_url)) || '';
			if (meetingUrl && button.getAttribute('data-action') === 'gcm_start_class') {
				window.open(meetingUrl, '_blank', 'noopener');
				window.setTimeout(function () {
					window.location.reload();
				}, 500);
				return;
			}
			window.setTimeout(function () {
				window.location.reload();
			}, 400);
		}).catch(function () {
			window.alert('Request failed.');
			button.disabled = false;
		});
	});

	document.addEventListener('submit', function (event) {
		var form = event.target.closest('.gcm-ajax-form');
		if (!form) {
			return;
		}
		event.preventDefault();
		ajaxForm(form);
	});

	document.addEventListener('submit', function (event) {
		var form = event.target.closest('.gcm-course-search');
		if (!form) {
			return;
		}
		event.preventDefault();
		var wrapper = form.closest('.gcm-courses');
		var grid = wrapper.querySelector('.gcm-course-grid');
		var data = new URLSearchParams(new FormData(form));
		data.append('action', 'gcm_course_search');
		data.append('nonce', wrapper.getAttribute('data-nonce') || window.gcmPublic.nonce);

		grid.innerHTML = '<p>Searching...</p>';
		fetch(window.gcmPublic.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: data.toString()
		}).then(function (response) {
			return response.json();
		}).then(function (json) {
			if (!json.success || !json.data.courses.length) {
				grid.innerHTML = '<p>No courses found.</p>';
				return;
			}
			grid.innerHTML = json.data.courses.map(function (course) {
				var regular = Number(course.price) || 0;
				var sale = Number(course.discount_price) || 0;
				var onSale = sale > 0 && sale < regular;
				var badge = course.sale_label ? escapeHtml(course.sale_label) : 'Sale';
				var img = course.thumbnail
					? '<div class="gcm-course-card__media"><img src="' + course.thumbnail + '" alt="' + escapeHtml(course.title) + '">' + (onSale ? '<span class="gcm-sale-badge">' + badge + '</span>' : '') + '</div>'
					: (onSale ? '<div class="gcm-course-card__media"><span class="gcm-sale-badge">' + badge + '</span></div>' : '');
				var priceHtml = onSale
					? '<p class="gcm-price gcm-price-block"><span class="gcm-price-block__sale">' + sale.toFixed(2) + '</span><s class="gcm-price-block__original">' + regular.toFixed(2) + '</s></p>'
					: '<p class="gcm-price">' + regular.toFixed(2) + '</p>';
				return '<article class="gcm-course-card' + (onSale ? ' gcm-course-card--sale' : '') + '">' + img + '<h3><a href="' + course.permalink + '">' + escapeHtml(course.title) + '</a></h3><p>' + escapeHtml(course.excerpt || '') + '</p>' + priceHtml + '<a class="gcm-button" href="' + window.gcmPublic.paymentUrl + '?course_id=' + course.id + '">Enroll now</a></article>';
			}).join('');
		}).catch(function () {
			grid.innerHTML = '<p>Search failed.</p>';
		});
	});

	document.addEventListener('click', function (event) {
		var button = event.target.closest('.gcm-complete-lesson');
		if (!button) {
			return;
		}
		event.preventDefault();
		var data = new URLSearchParams();
		data.append('action', 'gcm_mark_lesson_complete');
		data.append('nonce', window.gcmPublic.nonce);
		data.append('lesson_id', button.getAttribute('data-lesson-id'));
		data.append('completed', '1');

		button.disabled = true;
		fetch(window.gcmPublic.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: data.toString()
		}).then(function (response) {
			return response.json();
		}).then(function (json) {
			button.textContent = json.success ? 'Completed' : 'Try again';
			button.disabled = !!json.success;
		}).catch(function () {
			button.textContent = 'Try again';
			button.disabled = false;
		});
	});

	function copyText(value, button) {
		if (!value) {
			return;
		}
		var original = button ? button.textContent : '';
		var done = function () {
			if (!button) {
				return;
			}
			button.textContent = 'Copied';
			window.setTimeout(function () {
				button.textContent = original;
			}, 1600);
		};
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(value).then(done).catch(function () {
				window.prompt('Copy this:', value);
			});
			return;
		}
		window.prompt('Copy this:', value);
	}

	document.addEventListener('click', function (event) {
		var copyBtn = event.target.closest('.gcm-copy-value');
		if (copyBtn) {
			event.preventDefault();
			copyText(copyBtn.getAttribute('data-copy') || '', copyBtn);
			return;
		}

		var copyInvite = event.target.closest('.gcm-copy-invite');
		if (copyInvite) {
			event.preventDefault();
			var panel = copyInvite.closest('.gcm-meeting-invite');
			var source = panel ? panel.querySelector('.gcm-invite-source') : null;
			copyText(source ? source.value : '', copyInvite);
			return;
		}

		var shareBtn = event.target.closest('.gcm-share-invite');
		if (!shareBtn) {
			return;
		}
		event.preventDefault();
		var sharePanel = shareBtn.closest('.gcm-meeting-invite');
		var shareSource = sharePanel ? sharePanel.querySelector('.gcm-invite-source') : null;
		var shareData = {
			title: shareBtn.getAttribute('data-title') || 'Live class invitation',
			text: shareSource ? shareSource.value : (shareBtn.getAttribute('data-text') || ''),
			url: shareBtn.getAttribute('data-url') || ''
		};
		if (navigator.share) {
			navigator.share(shareData).catch(function () {});
			return;
		}
		copyText([shareData.text, shareData.url].filter(Boolean).join('\n\n'), shareBtn);
	});

	function escapeHtml(value) {
		var div = document.createElement('div');
		div.textContent = value;
		return div.innerHTML;
	}
})();
