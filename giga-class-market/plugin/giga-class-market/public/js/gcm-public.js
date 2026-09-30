(function () {
	'use strict';

	function errorMessage() {
		return (window.gcmPublic && gcmPublic.errorMessage) || 'Something went wrong. Please try again.';
	}

	function ajaxUrls() {
		var urls = [];
		if (window.gcmPublic && gcmPublic.ajaxUrl) {
			urls.push(gcmPublic.ajaxUrl);
		}
		if (window.gcmPublic && gcmPublic.restUrl) {
			urls.push(gcmPublic.restUrl);
		}
		if (window.gcmPublic && gcmPublic.adminAjax) {
			urls.push(gcmPublic.adminAjax);
		}
		return urls;
	}

	function parseJsonResponse(response) {
		return response.text().then(function (text) {
			try {
				var json = JSON.parse(text);
				if (json && typeof json.success !== 'undefined') {
					return json;
				}
			} catch (err) {}
			return {
				success: false,
				_retry: true,
				data: { message: errorMessage() }
			};
		});
	}

	function postAjax(body, asFormData) {
		var urls = ajaxUrls();
		function next(index) {
			if (index >= urls.length) {
				return Promise.resolve({
					success: false,
					data: { message: errorMessage() }
				});
			}
			var options = {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			};
			if (!asFormData) {
				options.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
			}
			return fetch(urls[index], options).then(parseJsonResponse).then(function (json) {
				if (json && json._retry) {
					return next(index + 1);
				}
				return json;
			}).catch(function () {
				return next(index + 1);
			});
		}
		return next(0);
	}

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
		postAjax(formData, true).then(function (json) {
			var message = json.data && json.data.message ? json.data.message : 'Request complete.';
			setMessage(form, message, json.success);
			if (json.success && form.classList.contains('gcm-contact-form')) {
				form.reset();
			}
			var action = form.getAttribute('data-action');
			if (json.success && json.data && json.data.id && (action === 'gcm_host_meeting' || action === 'gcm_start_class')) {
				var hosted = new URL(window.location.href);
				hosted.searchParams.set('hosted', String(json.data.id));
				hosted.hash = 'hosted-meeting';
				window.setTimeout(function () {
					window.location.assign(hosted.toString());
				}, 350);
				return;
			}
			if (json.success && (form.classList.contains('gcm-teacher-form') || action === 'gcm_send_course_message')) {
				window.setTimeout(function () {
					window.location.reload();
				}, 600);
			}
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
		postAjax(data.toString(), false).then(function (json) {
			if (json.success && json.data && json.data.join_url) {
				window.location.assign(json.data.join_url);
				return;
			}
			window.alert((json.data && json.data.message) || 'Unable to join class.');
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
		postAjax(data.toString(), false).then(function (json) {
			if (!json.success) {
				window.alert((json.data && json.data.message) || errorMessage());
				button.disabled = false;
				return;
			}
			if (button.getAttribute('data-action') === 'gcm_start_class' && json.data && json.data.id) {
				var hosted = new URL(window.location.href);
				hosted.searchParams.set('hosted', String(json.data.id));
				hosted.hash = 'hosted-meeting';
				window.location.assign(hosted.toString());
				return;
			}
			window.setTimeout(function () {
				window.location.reload();
			}, 400);
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
		postAjax(data.toString(), false).then(function (json) {
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
		postAjax(data.toString(), false).then(function (json) {
			button.textContent = json.success ? 'Completed' : 'Try again';
			button.disabled = !!json.success;
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

	document.addEventListener('focusin', function (event) {
		var field = event.target;
		if (field && field.matches && field.matches('.gcm-meeting-invite input[readonly]')) {
			field.select();
		}
	});

	function escapeHtml(value) {
		var div = document.createElement('div');
		div.textContent = value;
		return div.innerHTML;
	}
})();
