const assert = require("assert");
const engine = require("./engine");

function run(keys) {
  let state = engine.create();
  for (const key of keys) {
    state = engine.input(state, key);
  }
  return state;
}

function seq(keys, expectedDisplay) {
  const state = run(keys);
  assert.strictEqual(state.display, expectedDisplay, keys.join(" ") + " => " + state.display);
}

seq(["1", "2", "3"], "123");
seq(["1", "+", "2", "="], "3");
seq(["9", "−", "4", "="], "5");
seq(["6", "×", "7", "="], "42");
seq(["8", "÷", "2", "="], "4");
seq(["1", "0", "÷", "4", "="], "2.5");
seq(["2", "+", "3", "×", "4", "="], "20");
seq(["1", "0", "0", "+", "2", "5", "%", "="], "125");
seq(["5", "0", "%"], "0.5");
seq(["8", "÷", "0", "="], "Error");
seq(["1", ".", "5", "+", "2", "."], "2.");
seq(["5", "±"], "-5");
seq(["1", "2", "Backspace"], "1");
seq(["9", "+", "1", "=", "C"], "0");
seq([".", "5", "+", "1", "="], "1.5");

console.log("All calculator engine tests passed.");
