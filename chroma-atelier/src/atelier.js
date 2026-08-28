import * as THREE from "three";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";
import { EffectComposer } from "three/addons/postprocessing/EffectComposer.js";
import { RenderPass } from "three/addons/postprocessing/RenderPass.js";
import { UnrealBloomPass } from "three/addons/postprocessing/UnrealBloomPass.js";
import { OutputPass } from "three/addons/postprocessing/OutputPass.js";
import { palettes } from "./palettes.js";
import {
  particleFragment,
  particleVertex,
  ribbonFragment,
  ribbonVertex,
} from "./shaders.js";

const TAU = Math.PI * 2;

function hexColor(hex) {
  return new THREE.Color(hex);
}

function fibonacciSphere(count, radius) {
  const points = [];
  const golden = Math.PI * (3 - Math.sqrt(5));
  for (let i = 0; i < count; i += 1) {
    const y = 1 - (i / Math.max(1, count - 1)) * 2;
    const r = Math.sqrt(Math.max(0, 1 - y * y));
    const theta = golden * i;
    points.push(
      new THREE.Vector3(
        Math.cos(theta) * r * radius,
        y * radius * 0.82,
        Math.sin(theta) * r * radius,
      ),
    );
  }
  return points;
}

function detectQuality(renderer) {
  const gl = renderer.getContext();
  const debugInfo = gl.getExtension("WEBGL_debug_renderer_info");
  const gpu = debugInfo
    ? gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL)
    : gl.getParameter(gl.RENDERER) || "";
  const software = /swiftshader|llvmpipe|software|mesa offscreen|microsoft basic/i.test(
    String(gpu),
  );
  return {
    software,
    gpu: String(gpu),
    dpr: software ? 1 : Math.min(window.devicePixelRatio || 1, 1.75),
    particles: software ? 900 : 2600,
    knot: software ? [110, 18] : [200, 32],
    ribbons: software ? 3 : 5,
    bloom: software ? [0.55, 0.28, 0.0] : [0.82, 0.36, 0.15],
    transmission: !software,
  };
}

function makeEnvMap(renderer) {
  const pmrem = new THREE.PMREMGenerator(renderer);
  const envScene = new THREE.Scene();
  const hues = [0xff3355, 0xffaa22, 0x33ffcc, 0x3399ff, 0xaa44ff, 0xffffff];
  hues.forEach((color, i) => {
    const mesh = new THREE.Mesh(
      new THREE.SphereGeometry(1.8, 16, 16),
      new THREE.MeshBasicMaterial({ color }),
    );
    const a = (i / hues.length) * TAU;
    mesh.position.set(Math.cos(a) * 8, Math.sin(i * 1.3) * 3.2, Math.sin(a) * 8);
    envScene.add(mesh);
  });
  envScene.add(new THREE.AmbientLight(0xffffff, 1));
  const texture = pmrem.fromScene(envScene, 0.06).texture;
  pmrem.dispose();
  return texture;
}

function makeRibbonCurve(radius, height, turns, phase) {
  const points = [];
  for (let i = 0; i <= 90; i += 1) {
    const t = i / 90;
    const a = t * TAU * turns + phase;
    const swell = 0.72 + 0.28 * Math.sin(t * Math.PI * 3 + phase);
    points.push(
      new THREE.Vector3(
        Math.cos(a) * radius * swell,
        (t - 0.5) * height + Math.sin(a * 2) * 0.18,
        Math.sin(a) * radius * swell,
      ),
    );
  }
  return new THREE.CatmullRomCurve3(points, false, "catmullrom", 0.35);
}

export function createAtelier(canvas) {
  const renderer = new THREE.WebGLRenderer({
    canvas,
    antialias: true,
    alpha: false,
    powerPreference: "high-performance",
  });
  renderer.setClearColor(0x070309, 1);
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.05;
  renderer.shadowMap.enabled = false;

  const quality = detectQuality(renderer);
  renderer.setPixelRatio(quality.dpr);
  renderer.setSize(window.innerWidth, window.innerHeight);

  const scene = new THREE.Scene();
  scene.background = hexColor(palettes[0].bg);
  scene.fog = new THREE.FogExp2(palettes[0].fog, 0.038);
  scene.environment = makeEnvMap(renderer);

  const camera = new THREE.PerspectiveCamera(
    42,
    window.innerWidth / window.innerHeight,
    0.1,
    80,
  );
  camera.position.set(0, 3.6, 12.5);

  const controls = new OrbitControls(camera, canvas);
  controls.enableDamping = true;
  controls.dampingFactor = 0.06;
  controls.autoRotate = true;
  controls.autoRotateSpeed = 0.55;
  controls.minDistance = 4.2;
  controls.maxDistance = 16;
  controls.maxPolarAngle = Math.PI * 0.86;
  controls.target.set(0, 0.15, 0);

  const hemi = new THREE.HemisphereLight(0xffe6c8, 0x0a0610, 0.55);
  scene.add(hemi);

  const keyLight = new THREE.PointLight(0xff7a18, 18, 22, 1.6);
  keyLight.position.set(3.2, 2.8, 4.1);
  scene.add(keyLight);

  const fillLight = new THREE.PointLight(0x4cc9f0, 10, 20, 1.7);
  fillLight.position.set(-4.2, -0.6, -2.4);
  scene.add(fillLight);

  const rimLight = new THREE.PointLight(0xff2e63, 12, 18, 1.8);
  rimLight.position.set(0.2, 3.4, -4.6);
  scene.add(rimLight);

  const coreLight = new THREE.PointLight(0xffe9a0, 28, 10, 2);
  scene.add(coreLight);

  const floor = new THREE.Mesh(
    new THREE.CircleGeometry(11, 64),
    new THREE.MeshPhysicalMaterial({
      color: palettes[0].floor,
      metalness: 0.88,
      roughness: 0.22,
      envMapIntensity: 0.9,
    }),
  );
  floor.rotation.x = -Math.PI / 2;
  floor.position.y = -2.35;
  scene.add(floor);

  const ring = new THREE.Mesh(
    new THREE.TorusGeometry(4.35, 0.012, 12, 180),
    new THREE.MeshBasicMaterial({ color: palettes[0].accents[2] }),
  );
  ring.rotation.x = Math.PI / 2;
  ring.position.y = -2.32;
  scene.add(ring);

  const coreGroup = new THREE.Group();
  scene.add(coreGroup);

  const knotGeo = new THREE.TorusKnotGeometry(1.18, 0.36, quality.knot[0], quality.knot[1]);
  const knotMat = new THREE.MeshPhysicalMaterial({
    color: 0xffffff,
    metalness: quality.transmission ? 0.08 : 0.78,
    roughness: 0.08,
    transmission: quality.transmission ? 1 : 0,
    thickness: 1.6,
    ior: 1.48,
    iridescence: 1,
    iridescenceIOR: 1.22,
    iridescenceThicknessRange: [120, 420],
    clearcoat: 1,
    clearcoatRoughness: 0.08,
    envMapIntensity: 1.4,
    attenuationColor: hexColor(palettes[0].core),
    attenuationDistance: 2.4,
    transparent: quality.transmission,
  });
  const knot = new THREE.Mesh(knotGeo, knotMat);
  coreGroup.add(knot);

  const star = new THREE.Mesh(
    new THREE.IcosahedronGeometry(0.28, 1),
    new THREE.MeshBasicMaterial({ color: palettes[0].core }),
  );
  coreGroup.add(star);

  const gemGeos = [
    new THREE.IcosahedronGeometry(0.28, 0),
    new THREE.OctahedronGeometry(0.3, 0),
    new THREE.DodecahedronGeometry(0.26, 0),
    new THREE.TetrahedronGeometry(0.32, 0),
    new THREE.SphereGeometry(0.24, 24, 16),
    new THREE.TorusGeometry(0.2, 0.07, 12, 28),
  ];
  const gemRoots = fibonacciSphere(9, 3.15);
  const gems = gemRoots.map((origin, i) => {
    const color = hexColor(palettes[0].accents[i % 5]);
    const material = new THREE.MeshPhysicalMaterial({
      color,
      emissive: color.clone(),
      emissiveIntensity: 0.7,
      metalness: 0.35,
      roughness: 0.18,
      iridescence: 1,
      iridescenceIOR: 1.3,
      clearcoat: 0.8,
      sheen: 0.6,
      sheenColor: color.clone(),
    });
    const mesh = new THREE.Mesh(gemGeos[i % gemGeos.length], material);
    mesh.position.copy(origin);
    mesh.userData = {
      origin: origin.clone(),
      speed: 0.12 + (i % 4) * 0.04,
      bob: 0.7 + i * 0.13,
      phase: i * 0.7,
      paletteIndex: i % 5,
      pulse: 0,
    };
    scene.add(mesh);
    return mesh;
  });

  const ribbonMats = [];
  const ribbons = [];
  for (let i = 0; i < quality.ribbons; i += 1) {
    const curve = makeRibbonCurve(2.05 + i * 0.22, 3.4, 2 + (i % 2), i * 0.9);
    const geo = new THREE.TubeGeometry(curve, 180, 0.028, 6, false);
    const mat = new THREE.ShaderMaterial({
      uniforms: {
        uTime: { value: 0 },
        uC1: { value: hexColor(palettes[0].accents[0]) },
        uC2: { value: hexColor(palettes[0].accents[1]) },
        uC3: { value: hexColor(palettes[0].accents[2]) },
        uC4: { value: hexColor(palettes[0].accents[3]) },
      },
      vertexShader: ribbonVertex,
      fragmentShader: ribbonFragment,
      transparent: true,
      blending: THREE.AdditiveBlending,
      depthWrite: false,
      toneMapped: false,
    });
    const mesh = new THREE.Mesh(geo, mat);
    mesh.rotation.y = i * 0.5;
    scene.add(mesh);
    ribbons.push(mesh);
    ribbonMats.push(mat);
  }

  const particleGeo = new THREE.BufferGeometry();
  const count = quality.particles;
  const positions = new Float32Array(count * 3);
  const seeds = new Float32Array(count);
  const sizes = new Float32Array(count);
  for (let i = 0; i < count; i += 1) {
    const r = 2.3 + Math.random() * 6.4;
    const theta = Math.random() * TAU;
    const phi = Math.acos(2 * Math.random() - 1);
    positions[i * 3] = r * Math.sin(phi) * Math.cos(theta);
    positions[i * 3 + 1] = r * Math.cos(phi) * 0.72;
    positions[i * 3 + 2] = r * Math.sin(phi) * Math.sin(theta);
    seeds[i] = Math.random();
    sizes[i] = 1.2 + Math.random() * 4.2;
  }
  particleGeo.setAttribute("position", new THREE.BufferAttribute(positions, 3));
  particleGeo.setAttribute("aSeed", new THREE.BufferAttribute(seeds, 1));
  particleGeo.setAttribute("aSize", new THREE.BufferAttribute(sizes, 1));
  const particleMat = new THREE.ShaderMaterial({
    uniforms: {
      uTime: { value: 0 },
      uPixelRatio: { value: quality.dpr },
      uC1: { value: hexColor(palettes[0].accents[0]) },
      uC2: { value: hexColor(palettes[0].accents[3]) },
    },
    vertexShader: particleVertex,
    fragmentShader: particleFragment,
    transparent: true,
    blending: THREE.AdditiveBlending,
    depthWrite: false,
    toneMapped: false,
  });
  const particles = new THREE.Points(particleGeo, particleMat);
  scene.add(particles);

  const composer = new EffectComposer(renderer);
  composer.addPass(new RenderPass(scene, camera));
  const bloom = new UnrealBloomPass(
    new THREE.Vector2(window.innerWidth, window.innerHeight),
    quality.bloom[0],
    quality.bloom[1],
    quality.bloom[2],
  );
  composer.addPass(bloom);
  composer.addPass(new OutputPass());

  const current = {
    bg: hexColor(palettes[0].bg),
    fog: hexColor(palettes[0].fog),
    floor: hexColor(palettes[0].floor),
    core: hexColor(palettes[0].core),
    accents: palettes[0].accents.map(hexColor),
  };
  const target = {
    bg: current.bg.clone(),
    fog: current.fog.clone(),
    floor: current.floor.clone(),
    core: current.core.clone(),
    accents: current.accents.map((c) => c.clone()),
  };

  let paletteIndex = 0;
  let absorbed = null;
  const listeners = new Set();
  const clock = new THREE.Clock();
  const raycaster = new THREE.Raycaster();
  const pointer = new THREE.Vector2();
  const introFrom = camera.position.clone();
  const introTo = new THREE.Vector3(0, 1.25, 7.15);
  const white = new THREE.Color("#ffffff");
  const pointerDown = { x: 0, y: 0, moved: false, active: false };
  let intro = 0;
  let bloomPulse = 0;
  let running = true;
  let hoverGem = null;

  function emit(event, payload) {
    listeners.forEach((fn) => fn(event, payload));
  }

  function setPalette(idOrIndex, { absorb = null } = {}) {
    const next =
      typeof idOrIndex === "number"
        ? palettes[idOrIndex]
        : palettes.find((p) => p.id === idOrIndex);
    if (!next) return;
    paletteIndex = palettes.indexOf(next);
    target.bg.set(next.bg);
    target.fog.set(next.fog);
    target.floor.set(next.floor);
    target.core.set(next.core);
    next.accents.forEach((hex, i) => target.accents[i].set(hex));
    absorbed = absorb;
    bloomPulse = 1;
    const root = document.documentElement.style;
    root.setProperty("--bg", next.bg);
    root.setProperty("--accent", next.accents[1]);
    root.setProperty("--c1", next.accents[0]);
    root.setProperty("--c2", next.accents[1]);
    root.setProperty("--c3", next.accents[2]);
    root.setProperty("--c4", next.accents[3]);
    root.setProperty("--c5", next.accents[4]);
    emit("palette", { palette: next, absorbed });
  }

  function applyColors() {
    scene.background.copy(current.bg);
    scene.fog.color.copy(current.fog);
    renderer.setClearColor(current.bg, 1);
    floor.material.color.copy(current.floor);
    ring.material.color.copy(current.accents[2]);
    star.material.color.copy(current.core);
    knotMat.attenuationColor.copy(current.core);
    knotMat.color.copy(current.core).lerp(white, 0.55);
    coreLight.color.copy(current.core);
    keyLight.color.copy(current.accents[1]);
    fillLight.color.copy(current.accents[2]);
    rimLight.color.copy(current.accents[4]);
    hemi.color.copy(current.accents[3]);
    particleMat.uniforms.uC1.value.copy(current.accents[0]);
    particleMat.uniforms.uC2.value.copy(current.accents[3]);
    ribbonMats.forEach((mat) => {
      mat.uniforms.uC1.value.copy(current.accents[0]);
      mat.uniforms.uC2.value.copy(current.accents[1]);
      mat.uniforms.uC3.value.copy(current.accents[2]);
      mat.uniforms.uC4.value.copy(current.accents[3]);
    });
    gems.forEach((gem) => {
      const accent = current.accents[gem.userData.paletteIndex];
      gem.material.color.copy(accent);
      gem.material.emissive.copy(accent);
      gem.material.sheenColor.copy(accent);
    });
  }

  function pickGem(clientX, clientY) {
    const rect = canvas.getBoundingClientRect();
    pointer.x = ((clientX - rect.left) / rect.width) * 2 - 1;
    pointer.y = -((clientY - rect.top) / rect.height) * 2 + 1;
    raycaster.setFromCamera(pointer, camera);
    const hits = raycaster.intersectObjects(gems, false);
    return hits[0]?.object ?? null;
  }

  function absorbGem(gem) {
    if (!gem) return;
    gem.userData.pulse = 1;
    const palette = palettes[paletteIndex];
    const name = palette.names[gem.userData.paletteIndex];
    const hex = palette.accents[gem.userData.paletteIndex];
    const toward = hexColor(hex);
    target.core.copy(toward);
    target.accents.forEach((c, i) => {
      c.lerp(toward, i === gem.userData.paletteIndex ? 0 : 0.35);
    });
    bloomPulse = 1.2;
    absorbed = { name, hex };
    emit("palette", { palette, absorbed });
  }

  function onPointerMove(event) {
    if (pointerDown.active) {
      const dist = Math.hypot(event.clientX - pointerDown.x, event.clientY - pointerDown.y);
      if (dist > 8) pointerDown.moved = true;
    }
    const gem = pickGem(event.clientX, event.clientY);
    hoverGem = gem;
    canvas.style.cursor = gem ? "pointer" : pointerDown.active ? "grabbing" : "grab";
  }

  function onPointerDown(event) {
    pointerDown.x = event.clientX;
    pointerDown.y = event.clientY;
    pointerDown.moved = false;
    pointerDown.active = true;
    canvas.classList.add("is-dragging");
  }

  function onPointerUp(event) {
    canvas.classList.remove("is-dragging");
    const wasClick = pointerDown.active && !pointerDown.moved;
    pointerDown.active = false;
    canvas.style.cursor = "grab";
    if (!wasClick) return;
    const gem = pickGem(event.clientX, event.clientY);
    if (gem) absorbGem(gem);
  }

  function onResize() {
    const w = window.innerWidth;
    const h = window.innerHeight;
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    renderer.setSize(w, h);
    composer.setSize(w, h);
    bloom.setSize(w, h);
  }

  canvas.addEventListener("pointermove", onPointerMove);
  canvas.addEventListener("pointerdown", onPointerDown);
  canvas.addEventListener("pointerup", onPointerUp);
  window.addEventListener("resize", onResize);

  const spinAxis = new THREE.Vector3(0, 1, 0);

  function tick() {
    if (!running) return;
    const dt = Math.min(clock.getDelta(), 0.05);
    const t = clock.elapsedTime;

    const k = 1 - Math.exp(-dt * 2.4);
    current.bg.lerp(target.bg, k);
    current.fog.lerp(target.fog, k);
    current.floor.lerp(target.floor, k);
    current.core.lerp(target.core, k);
    current.accents.forEach((c, i) => c.lerp(target.accents[i], k));
    applyColors();

    if (intro < 1) {
      intro = Math.min(1, intro + dt * 0.38);
      const e = 1 - Math.pow(1 - intro, 3);
      camera.position.lerpVectors(introFrom, introTo, e);
    }

    coreGroup.rotation.y += dt * 0.18;
    coreGroup.rotation.x = Math.sin(t * 0.35) * 0.12;
    star.rotation.y -= dt * 0.8;
    star.scale.setScalar(0.92 + Math.sin(t * 2.2) * 0.1);
    coreLight.intensity = 22 + Math.sin(t * 2.2) * 8;
    particles.rotation.y = t * 0.03;

    gems.forEach((gem) => {
      gem.userData.origin.applyAxisAngle(spinAxis, dt * gem.userData.speed * 0.25);
      gem.position.copy(gem.userData.origin);
      gem.position.y += Math.sin(t * gem.userData.bob + gem.userData.phase) * 0.16;
      gem.rotation.y += dt * 0.55;
      gem.rotation.x += dt * 0.2;
      if (gem.userData.pulse > 0) gem.userData.pulse = Math.max(0, gem.userData.pulse - dt * 1.6);
      const s = 1 + gem.userData.pulse * 0.55 + (hoverGem === gem ? 0.12 : 0);
      gem.scale.setScalar(s);
      gem.material.emissiveIntensity = 0.55 + gem.userData.pulse * 1.4 + Math.sin(t * 3 + gem.userData.phase) * 0.12;
    });

    ribbonMats.forEach((mat, i) => {
      mat.uniforms.uTime.value = t + i * 0.4;
      ribbons[i].rotation.y += dt * (0.08 + i * 0.02);
    });
    particleMat.uniforms.uTime.value = t;

    bloomPulse = Math.max(0, bloomPulse - dt * 1.3);
    bloom.strength = quality.bloom[0] + bloomPulse * 0.45;

    controls.update();
    composer.render();
    requestAnimationFrame(tick);
  }

  requestAnimationFrame(tick);

  return {
    quality,
    palettes,
    getPalette() {
      return palettes[paletteIndex];
    },
    setPalette,
    nextPalette() {
      setPalette((paletteIndex + 1) % palettes.length);
    },
    resetView() {
      introFrom.copy(camera.position);
      introTo.set(0, 1.25, 7.15);
      intro = 0;
      controls.target.set(0, 0.15, 0);
    },
    setAutoRotate(on) {
      controls.autoRotate = on;
    },
    on(fn) {
      listeners.add(fn);
      return () => listeners.delete(fn);
    },
    dispose() {
      running = false;
      canvas.removeEventListener("pointermove", onPointerMove);
      canvas.removeEventListener("pointerdown", onPointerDown);
      canvas.removeEventListener("pointerup", onPointerUp);
      window.removeEventListener("resize", onResize);
      controls.dispose();
      composer.dispose();
      renderer.dispose();
    },
  };
}
