export const palettes = [
  {
    id: "solar",
    name: "Solar Flare",
    theory:
      "Analogous heat — coral through gold. Warm dominance, high chroma against a near-black void.",
    bg: "#070309",
    fog: "#220910",
    floor: "#14080c",
    core: "#ffb56b",
    accents: ["#ff4d1c", "#ff7a18", "#ffc53d", "#ffe9a0", "#ff2e63"],
    names: ["ember coral", "solar orange", "furnace gold", "flare cream", "hot magenta"],
  },
  {
    id: "biolume",
    name: "Biolume",
    theory:
      "Split-complement ocean — teal, cyan, and magenta. Cool field with electric accents, like deep-sea light.",
    bg: "#02080d",
    fog: "#032029",
    floor: "#031018",
    core: "#7ee8fa",
    accents: ["#00f5d4", "#00bbf9", "#7b2cbf", "#f15bb5", "#9bf6ff"],
    names: ["plankton teal", "sonar cyan", "abyss violet", "lure pink", "ice mint"],
  },
  {
    id: "kyoto",
    name: "Kyoto Dusk",
    theory:
      "Traditional Japanese harmony — vermillion, gold leaf, pine, and bone. Earth plus fire, low saturation edges.",
    bg: "#0a0705",
    fog: "#1a120c",
    floor: "#120d09",
    core: "#f4d35e",
    accents: ["#c1121f", "#f4d35e", "#e09f3e", "#335c67", "#f3e9dc"],
    names: ["vermillion", "gold leaf", "persimmon", "pine smoke", "rice paper"],
  },
  {
    id: "polar",
    name: "Polar Bloom",
    theory:
      "Triadic aurora — violet, cyan, spring green. High-key ice light, complementary tension held in fog.",
    bg: "#04060f",
    fog: "#10183a",
    floor: "#070a18",
    core: "#b8c0ff",
    accents: ["#c77dff", "#7b2cbf", "#4cc9f0", "#80ffdb", "#4895ef"],
    names: ["lilac ice", "polar violet", "glacier cyan", "aurora green", "arctic blue"],
  },
  {
    id: "ember",
    name: "Ember Noir",
    theory:
      "Chiaroscuro — amber, blood orange, and bone on charcoal. Low key, cinematic, one bright note.",
    bg: "#080605",
    fog: "#1a0e08",
    floor: "#120c08",
    core: "#ffba08",
    accents: ["#e85d04", "#f48c06", "#faa307", "#dc2f02", "#f8f1e9"],
    names: ["coal orange", "wick amber", "candle gold", "hearth red", "bone"],
  },
  {
    id: "lotus",
    name: "Lotus Neon",
    theory:
      "Clash harmony — hot pink, ultraviolet, and laser cyan. Complementary shock held by a night-violet ground.",
    bg: "#09050f",
    fog: "#1a0b2a",
    floor: "#100818",
    core: "#ff70a6",
    accents: ["#ff006e", "#8338ec", "#3a86ff", "#fb5607", "#ffbe0b"],
    names: ["lotus pink", "ultraviolet", "laser cyan", "saffron flash", "neon gold"],
  },
];

export const paletteById = Object.fromEntries(palettes.map((p) => [p.id, p]));
