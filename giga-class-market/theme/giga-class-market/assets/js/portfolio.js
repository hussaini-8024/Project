(function () {
	var root = document.querySelector("[data-gcm-folio]");
	if (!root) {
		return;
	}

	var reveals = root.querySelectorAll(".gcm-folio-reveal");
	if ("IntersectionObserver" in window) {
		var io = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						entry.target.classList.add("is-in");
						io.unobserve(entry.target);
					}
				});
			},
			{ threshold: 0.14, rootMargin: "0px 0px -8% 0px" }
		);
		reveals.forEach(function (el, index) {
			if (!el.style.getPropertyValue("--gcm-folio-i")) {
				el.style.setProperty("--gcm-folio-i", String(index % 8));
			}
			io.observe(el);
		});
	} else {
		reveals.forEach(function (el) {
			el.classList.add("is-in");
		});
	}

	// Soft typewriter for the name (keeps final text accessible).
	var typeEl = root.querySelector("[data-gcm-typewriter]");
	if (typeEl) {
		var full = (typeEl.textContent || "").trim();
		if (full.length > 1 && !window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
			typeEl.textContent = "";
			typeEl.classList.add("is-in");
			var i = 0;
			var timer = window.setInterval(function () {
				typeEl.textContent = full.slice(0, i + 1);
				i += 1;
				if (i >= full.length) {
					window.clearInterval(timer);
				}
			}, 42);
		} else {
			typeEl.classList.add("is-in");
		}
	}

	var filters = root.querySelectorAll(".gcm-folio-filters button");
	var cards = root.querySelectorAll(".gcm-folio-card");
	filters.forEach(function (btn) {
		btn.addEventListener("click", function () {
			var filter = btn.getAttribute("data-filter") || "all";
			filters.forEach(function (b) {
				b.classList.toggle("is-active", b === btn);
				b.setAttribute("aria-selected", b === btn ? "true" : "false");
			});
			cards.forEach(function (card) {
				var cat = card.getAttribute("data-category") || "";
				var show = filter === "all" || cat === filter;
				card.classList.toggle("is-hidden", !show);
				if (show) {
					card.classList.add("is-in");
				}
			});
		});
	});
})();
