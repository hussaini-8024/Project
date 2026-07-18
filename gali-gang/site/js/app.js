(function () {
  const { characters, episodes } = window.GALI;
  const grid = document.getElementById("charGrid");
  const detail = document.getElementById("charDetail");
  const epList = document.getElementById("epList");
  const epSelect = document.getElementById("epSelect");
  const stageImg = document.getElementById("stageImg");
  const stageTitle = document.getElementById("stageTitle");
  const stageLine = document.getElementById("stageLine");
  const scriptHead = document.getElementById("scriptHead");
  const scriptBody = document.getElementById("scriptBody");
  const bar = document.getElementById("bar");
  const playBtn = document.getElementById("playBtn");
  const stopBtn = document.getElementById("stopBtn");
  const promoVideo = document.getElementById("promoVideo");
  const promoSource = document.getElementById("promoSource");

  let timer = null;
  let sceneIndex = 0;
  let currentEp = episodes[0];

  let useLong = true;

  function setPromo(ep) {
    if (!promoVideo || !promoSource) return;
    const src = useLong && ep.longVideo ? ep.longVideo : ep.video;
    if (!src) return;
    promoSource.src = src;
    promoVideo.load();
  }

  const modeBtn = document.getElementById("modeBtn");
  if (modeBtn) {
    modeBtn.addEventListener("click", () => {
      useLong = !useLong;
      modeBtn.textContent = useLong ? "Mode: 20-min Animatic" : "Mode: Short Promo";
      setPromo(currentEp);
    });
  }

  function showCharacter(c) {
    detail.innerHTML = `
      <h3>${c.name}</h3>
      <p>${c.blurb}</p>
      <p class="line">“${c.line}”</p>
    `;
  }

  characters.forEach((c, i) => {
    const btn = document.createElement("button");
    btn.className = "char";
    btn.type = "button";
    btn.innerHTML = `
      <div class="char-visual"><img src="${c.img}" alt="${c.name}" loading="lazy" /></div>
      <div class="char-meta"><strong>${c.name}</strong><span>${c.role}</span></div>
    `;
    btn.addEventListener("click", () => showCharacter(c));
    grid.appendChild(btn);
    if (i === 0) showCharacter(c);
  });

  episodes.forEach((ep) => {
    const row = document.createElement("article");
    row.className = "ep";
    row.innerHTML = `
      <div class="ep-num">${ep.num}</div>
      <div>
        <h3>${ep.title}</h3>
        <p>${ep.summary}</p>
      </div>
      <div class="ep-time">${ep.runtime}</div>
    `;
    row.addEventListener("click", () => {
      epSelect.value = ep.id;
      loadEpisode(ep, false);
      document.getElementById("watch").scrollIntoView({ behavior: "smooth" });
    });
    epList.appendChild(row);

    const opt = document.createElement("option");
    opt.value = ep.id;
    opt.textContent = `Ep ${ep.num} — ${ep.title} (${ep.runtime})`;
    epSelect.appendChild(opt);
  });

  function loadEpisode(ep, autoplay) {
    stopPlayback();
    currentEp = ep;
    sceneIndex = 0;
    setPromo(ep);
    paintScene(ep.scenes[0], true);
    scriptHead.textContent = `Ep ${ep.num}: ${ep.title}`;
    scriptBody.textContent = ep.summary + " Full teleplay target runtime " + ep.runtime + ".";
    if (autoplay) startPlayback();
  }

  function paintScene(scene, instant) {
    if (!scene) return;
    if (!instant) stageImg.classList.remove("is-on");
    const apply = () => {
      stageImg.src = scene.img;
      stageTitle.textContent = scene.title;
      stageLine.textContent = scene.line;
      scriptBody.textContent = scene.line;
      stageImg.classList.add("is-on");
    };
    if (instant) apply();
    else setTimeout(apply, 180);
  }

  function stopPlayback() {
    if (timer) clearInterval(timer);
    timer = null;
    playBtn.textContent = "Play";
  }

  function startPlayback() {
    stopPlayback();
    sceneIndex = 0;
    const scenes = currentEp.scenes;
    const total = scenes.length;
    paintScene(scenes[0], true);
    playBtn.textContent = "Playing…";
    let tick = 0;
    const sceneMs = 3200;
    timer = setInterval(() => {
      tick += 200;
      const idx = Math.min(total - 1, Math.floor(tick / sceneMs));
      if (idx !== sceneIndex) {
        sceneIndex = idx;
        paintScene(scenes[sceneIndex], false);
      }
      bar.style.width = Math.min(100, (tick / (total * sceneMs)) * 100) + "%";
      if (tick >= total * sceneMs) {
        stopPlayback();
        bar.style.width = "100%";
        scriptBody.textContent = "Reel complete! Full 20-minute teleplay scripts are in /gali-gang/episodes/.";
      }
    }, 200);
  }

  epSelect.addEventListener("change", () => {
    const ep = episodes.find((e) => e.id === epSelect.value);
    loadEpisode(ep, false);
  });
  playBtn.addEventListener("click", startPlayback);
  stopBtn.addEventListener("click", () => {
    stopPlayback();
    bar.style.width = "0%";
  });

  loadEpisode(episodes[0], false);
})();
