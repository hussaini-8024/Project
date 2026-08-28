import { palettes } from "./palettes.js";

export function bindHud(atelier) {
  const row = document.getElementById("palette-row");
  const nameEl = document.getElementById("palette-name");
  const theoryEl = document.getElementById("palette-theory");
  const absorbedEl = document.getElementById("absorbed");
  const autoBtn = document.getElementById("btn-auto");
  const resetBtn = document.getElementById("btn-reset");
  const loader = document.getElementById("loader");

  let autoplay = true;
  let timer = 0;

  palettes.forEach((palette, index) => {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = `swatch${index === 0 ? " is-active" : ""}`;
    btn.dataset.id = palette.id;
    btn.setAttribute("aria-pressed", index === 0 ? "true" : "false");
    btn.innerHTML = `
      <span class="swatch-bar" aria-hidden="true">
        ${palette.accents.map((hex) => `<span style="background:${hex}"></span>`).join("")}
      </span>
      <span class="swatch-name">${palette.name}</span>
    `;
    btn.addEventListener("click", () => {
      atelier.setPalette(palette.id);
      restartAuto();
    });
    row.appendChild(btn);
  });

  function paintHud(palette, absorbed) {
    nameEl.textContent = palette.name;
    theoryEl.textContent = palette.theory;
    document.querySelectorAll(".swatch").forEach((btn) => {
      const on = btn.dataset.id === palette.id;
      btn.classList.toggle("is-active", on);
      btn.setAttribute("aria-pressed", on ? "true" : "false");
    });
    if (absorbed) {
      absorbedEl.classList.remove("is-idle");
      absorbedEl.textContent = `absorbed · ${absorbed.name}`;
    } else {
      absorbedEl.classList.add("is-idle");
      absorbedEl.textContent = "click a gem to absorb color";
    }
  }

  function restartAuto() {
    window.clearInterval(timer);
    if (!autoplay) return;
    timer = window.setInterval(() => atelier.nextPalette(), 14000);
  }

  autoBtn.addEventListener("click", () => {
    autoplay = !autoplay;
    autoBtn.classList.toggle("is-on", autoplay);
    autoBtn.setAttribute("aria-pressed", autoplay ? "true" : "false");
    atelier.setAutoRotate(autoplay);
    restartAuto();
  });

  resetBtn.addEventListener("click", () => atelier.resetView());

  window.addEventListener("keydown", (event) => {
    if (event.metaKey || event.ctrlKey || event.altKey) return;
    const num = Number(event.key);
    if (num >= 1 && num <= palettes.length) {
      atelier.setPalette(palettes[num - 1].id);
      restartAuto();
    }
    if (event.key === " ") {
      event.preventDefault();
      atelier.nextPalette();
      restartAuto();
    }
    if (event.key.toLowerCase() === "r") atelier.resetView();
  });

  atelier.on((_event, payload) => paintHud(payload.palette, payload.absorbed));
  paintHud(atelier.getPalette(), null);
  restartAuto();

  requestAnimationFrame(() => {
    loader.classList.add("is-gone");
  });

  return {
    stopAutoplay() {
      autoplay = false;
      autoBtn.classList.remove("is-on");
      window.clearInterval(timer);
      atelier.setAutoRotate(true);
    },
  };
}
