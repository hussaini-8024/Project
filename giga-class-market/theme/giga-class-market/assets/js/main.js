(function () {
	'use strict';

	function setCookie(name, value) {
		var maxAge = 60 * 60 * 24 * 365;
		document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
	}

	function initThemeToggle() {
		var toggle = document.querySelector('[data-gcm-theme-toggle]');
		var html = document.documentElement;
		var saved = window.localStorage.getItem('gcmTheme');

		if (saved === 'dark' || saved === 'light') {
			html.setAttribute('data-theme', saved);
			setCookie('gcm_theme', saved);
		}

		if (!toggle) {
			return;
		}

		function updatePressed() {
			toggle.setAttribute('aria-pressed', html.getAttribute('data-theme') === 'dark' ? 'true' : 'false');
		}

		toggle.addEventListener('click', function () {
			var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
			html.setAttribute('data-theme', next);
			window.localStorage.setItem('gcmTheme', next);
			setCookie('gcm_theme', next);
			updatePressed();
		});

		updatePressed();
	}

	function initMobileNav() {
		var toggle = document.querySelector('.gcm-nav-toggle');
		var nav = document.querySelector('#gcm-primary-nav');

		if (!toggle || !nav) {
			return;
		}

		toggle.addEventListener('click', function () {
			var open = toggle.getAttribute('aria-expanded') === 'true';
			toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
			document.body.classList.toggle('gcm-nav-open', !open);
		});

		nav.addEventListener('click', function (event) {
			if (event.target.tagName === 'A') {
				toggle.setAttribute('aria-expanded', 'false');
				document.body.classList.remove('gcm-nav-open');
			}
		});
	}

	function initScrollAnimations() {
		var items = document.querySelectorAll('.gcm-animate');

		if (!('IntersectionObserver' in window)) {
			items.forEach(function (item) {
				item.classList.add('is-visible');
			});
			return;
		}

		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.classList.add('is-visible');
					observer.unobserve(entry.target);
				}
			});
		}, { threshold: 0.12 });

		items.forEach(function (item) {
			observer.observe(item);
		});
	}

	function initAjaxForms() {
		document.querySelectorAll('[data-gcm-ajax-form], [data-gcm-progress-form]').forEach(function (form) {
			form.addEventListener('submit', function (event) {
				var status = form.querySelector('.gcm-form-status');
				var button = form.querySelector('button[type="submit"]');
				var data = new FormData(form);

				event.preventDefault();
				if (status) {
					status.textContent = window.gcmTheme && gcmTheme.i18n ? gcmTheme.i18n.sending : 'Sending...';
				}
				if (button) {
					button.disabled = true;
				}

				window.fetch(form.getAttribute('action'), {
					method: 'POST',
					credentials: 'same-origin',
					body: data
				})
					.then(function (response) {
						return response.json().catch(function () {
							return { success: response.ok };
						});
					})
					.then(function (payload) {
						var message = payload && payload.data && payload.data.message ? payload.data.message : null;
						if (status) {
							status.textContent = message || (payload.success ? gcmTheme.i18n.sent : gcmTheme.i18n.error);
							status.classList.toggle('is-error', !payload.success);
						}
						if (payload.success && form.hasAttribute('data-gcm-ajax-form')) {
							form.reset();
						}
					})
					.catch(function () {
						if (status) {
							status.textContent = window.gcmTheme && gcmTheme.i18n ? gcmTheme.i18n.error : 'Something went wrong.';
							status.classList.add('is-error');
						}
					})
					.finally(function () {
						if (button) {
							button.disabled = false;
						}
					});
			});
		});
	}

	function initPromoPopup() {
		var popup = document.getElementById('gcm-promo-popup');
		if (!popup) {
			return;
		}

		var popupId = popup.getAttribute('data-popup-id') || 'default';
		var storageKey = 'gcmPromoPopupDismissed:' + popupId;

		try {
			if (window.localStorage.getItem(storageKey) === '1') {
				return;
			}
		} catch (err) {
			/* ignore storage errors */
		}

		function closePopup() {
			popup.hidden = true;
			popup.classList.remove('is-open');
			document.body.classList.remove('gcm-promo-open');
			try {
				window.localStorage.setItem(storageKey, '1');
			} catch (err) {
				/* ignore storage errors */
			}
		}

		popup.hidden = false;
		window.requestAnimationFrame(function () {
			popup.classList.add('is-open');
			document.body.classList.add('gcm-promo-open');
		});

		popup.querySelectorAll('[data-gcm-popup-close]').forEach(function (el) {
			el.addEventListener('click', closePopup);
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && popup.classList.contains('is-open')) {
				closePopup();
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		initThemeToggle();
		initMobileNav();
		initScrollAnimations();
		initAjaxForms();
		initPromoPopup();
	});
})();
