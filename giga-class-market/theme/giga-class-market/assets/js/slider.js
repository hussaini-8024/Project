(function () {
	'use strict';

	function createSlider(root) {
		var slides = Array.prototype.slice.call(root.querySelectorAll('[data-gcm-slide]'));
		var dots = Array.prototype.slice.call(root.querySelectorAll('[data-gcm-slider-dot]'));
		var prev = root.querySelector('[data-gcm-slider-prev]');
		var next = root.querySelector('[data-gcm-slider-next]');
		var current = 0;
		var timer = null;

		if (slides.length < 2) {
			return;
		}

		function show(index) {
			current = (index + slides.length) % slides.length;
			slides.forEach(function (slide, slideIndex) {
				slide.classList.toggle('is-active', slideIndex === current);
			});
			dots.forEach(function (dot, dotIndex) {
				dot.classList.toggle('is-active', dotIndex === current);
			});
		}

		function stop() {
			if (timer) {
				window.clearInterval(timer);
			}
		}

		function start() {
			stop();
			timer = window.setInterval(function () {
				show(current + 1);
			}, 3000);
		}

		if (prev) {
			prev.addEventListener('click', function () {
				show(current - 1);
				start();
			});
		}

		if (next) {
			next.addEventListener('click', function () {
				show(current + 1);
				start();
			});
		}

		dots.forEach(function (dot) {
			dot.addEventListener('click', function () {
				show(parseInt(dot.getAttribute('data-gcm-slider-dot'), 10));
				start();
			});
		});

		root.addEventListener('mouseenter', stop);
		root.addEventListener('mouseleave', start);
		root.addEventListener('focusin', stop);
		root.addEventListener('focusout', start);

		start();
	}

	window.gcmCreateSlider = createSlider;

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-gcm-slider]').forEach(createSlider);
	});
})();
