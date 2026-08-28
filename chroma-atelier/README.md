# Chroma Atelier

An interactive 3D color studio: a glass torus knot holds a trapped star, gems orbit on a Fibonacci sphere, and pigment ribbons drift through fog. Six palettes were designed as complete lighting worlds — not random hex lists.

Open `chroma-atelier/` and run:

```bash
cd chroma-atelier
npm install
npm run dev
```

Then visit `http://localhost:5173`.

## How to play with it

- **Drag** to orbit, **scroll** to zoom
- **Click a gem** to absorb its pigment — the core, lights, and ribbons regrade toward that hue
- **1–6** jump palettes, **space** cycles, **R** resets the camera
- **Autoplay** walks the six worlds every 14 seconds

## Palettes

| World | Harmony | Feeling |
| --- | --- | --- |
| Solar Flare | Analogous heat | Coral, furnace gold, hot magenta |
| Biolume | Split-complement ocean | Teal, sonar cyan, lure pink |
| Kyoto Dusk | Traditional Japanese | Vermillion, gold leaf, pine |
| Polar Bloom | Triadic aurora | Violet, glacier cyan, spring green |
| Ember Noir | Chiaroscuro | Amber, hearth red, bone |
| Lotus Neon | Clash harmony | Hot pink, ultraviolet, laser cyan |

## Other 3D color ideas (not in this build)

1. **Glass Prism Conservatory** — walk a greenhouse of giant prisms; sunlight splits into spectral rooms you can step through.
2. **Synesthetic Music Nebula** — each note is a color and a shape; a track grows a 3D score you can fly inside.
3. **Iridescent Koi Pond** — physically based water, morpho-blue scales, and a moon that shifts the whole palette.
4. **Albers Rooms** — Josef Albers *Homage to the Square* extruded into nested chambers; color relativity becomes architecture.
5. **Liquid Metal Weather** — a planet of mercury continents with iridescent storms you steer with the cursor.
6. **Origami Crane Flock** — thousands of folding papers, each a swatch, murmuring between complementary skies.
7. **Neon Night Market** — wet pavement, signage in clash palettes, steam that picks up nearby neon.
8. **Botanical Iridescence** — a morpho-butterfly garden where wing interference shaders are the whole show.

## Stack

Vite, Three.js, custom GLSL particles/ribbons, ACES filmic tone mapping, Unreal bloom. Software GPUs fall back from glass transmission to iridescent metal so the scene still reads.
