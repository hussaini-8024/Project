# Calculator

A four-function web calculator with chained operations, percent, sign toggle, keyboard input, and a small Node test suite for the engine.

## Run

Open `index.html` in a browser, or serve the folder:

```bash
python3 -m http.server 4173 --directory calculator
```

Then visit http://localhost:4173/

## Tests

```bash
node calculator/engine.test.js
```
