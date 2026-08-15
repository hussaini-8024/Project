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
		if (!formData.get('nonce') && window.gcmPublic) {
			formData.append('nonce', window.gcmPublic.nonce);
		}

		setMessage(form, 'Submitting...', true);
		fetch(window.gcmPublic.ajaxUrl, {
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
				window.location.assign(meetingUrl);
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
				var img = course.thumbnail ? '<img src="' + course.thumbnail + '" alt="' + escapeHtml(course.title) + '">' : '';
				return '<article class="gcm-course-card">' + img + '<h3><a href="' + course.permalink + '">' + escapeHtml(course.title) + '</a></h3><p>' + escapeHtml(course.excerpt || '') + '</p><p class="gcm-price">' + Number(course.price).toFixed(2) + '</p><a class="gcm-button" href="' + window.gcmPublic.paymentUrl + '?course_id=' + course.id + '">Enroll now</a></article>';
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

	function escapeHtml(value) {
		var div = document.createElement('div');
		div.textContent = value;
		return div.innerHTML;
	}
})();
