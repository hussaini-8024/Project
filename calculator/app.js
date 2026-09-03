(function () {
  "use strict";

  var engine = window.CalculatorEngine;
  var state = engine.create();
  var displayEl = document.getElementById("display");
  var expressionEl = document.getElementById("expression");

  function render() {
    displayEl.textContent = state.display;
    expressionEl.textContent = state.expression || "";
  }

  function press(key) {
    state = engine.input(state, key);
    render();
  }

  document.querySelector(".calc__keys").addEventListener("click", function (event) {
    var button = event.target.closest("button[data-key]");
    if (!button) return;
    press(button.getAttribute("data-key"));
  });

  var keyMap = {
    Enter: "=",
    Escape: "C",
    Backspace: "Backspace",
    "*": "×",
    "/": "÷",
    "-": "−",
    "+": "+",
    "%": "%",
    ".": ".",
    "=": "=",
  };

  document.addEventListener("keydown", function (event) {
    var key = event.key;
    var mapped = keyMap[key];
    if (!mapped && key >= "0" && key <= "9") mapped = key;
    if (!mapped) return;
    event.preventDefault();
    press(mapped);
    var selector = 'button[data-key="' + mapped + '"]';
    var button = document.querySelector(selector);
    if (button) {
      button.classList.add("is-pressed");
      setTimeout(function () {
        button.classList.remove("is-pressed");
      }, 90);
    }
  });

  render();
})();
