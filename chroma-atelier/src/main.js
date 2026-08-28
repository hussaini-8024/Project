import "./styles.css";
import { createAtelier } from "./atelier.js";
import { bindHud } from "./ui.js";

const canvas = document.getElementById("stage");
const atelier = createAtelier(canvas);
bindHud(atelier);

if (import.meta.hot) {
  import.meta.hot.dispose(() => atelier.dispose());
}
