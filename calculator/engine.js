/**
 * Pure calculator engine used by the UI and unit tests.
 * Follows left-to-right binary operations (standard four-function calculator).
 */
(function (root, factory) {
  if (typeof module === "object" && module.exports) {
    module.exports = factory();
  } else {
    root.CalculatorEngine = factory();
  }
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  "use strict";

  var MAX_DIGITS = 12;
  var OPERATORS = { "+": 1, "−": 1, "×": 1, "÷": 1 };

  function create() {
    return {
      display: "0",
      expression: "",
      stored: null,
      pendingOp: null,
      fresh: true,
      error: false,
    };
  }

  function clone(state) {
    return {
      display: state.display,
      expression: state.expression,
      stored: state.stored,
      pendingOp: state.pendingOp,
      fresh: state.fresh,
      error: state.error,
    };
  }

  function formatNumber(n) {
    if (!isFinite(n)) return "Error";
    var abs = Math.abs(n);
    if (abs !== 0 && (abs >= 1e12 || abs < 1e-9)) {
      return n.toExponential(6).replace(/\.?0+e/, "e").replace("e+", "e");
    }
    var s = String(n);
    if (s.indexOf("e") !== -1) return s;
    if (s.indexOf(".") !== -1) {
      s = s.replace(/\.?0+$/, "");
    }
    if (s.replace("-", "").replace(".", "").length > MAX_DIGITS) {
      return Number(n.toPrecision(MAX_DIGITS)).toString();
    }
    return s === "-0" ? "0" : s;
  }

  function applyOp(a, op, b) {
    switch (op) {
      case "+":
        return a + b;
      case "−":
        return a - b;
      case "×":
        return a * b;
      case "÷":
        if (b === 0) return NaN;
        return a / b;
      default:
        return b;
    }
  }

  function currentValue(state) {
    return parseFloat(state.display);
  }

  function digit(state, d) {
    state = clone(state);
    if (state.error) state = create();
    if (state.fresh) {
      state.display = d;
      state.fresh = false;
    } else {
      if (state.display.replace("-", "").replace(".", "").length >= MAX_DIGITS) {
        return state;
      }
      if (state.display === "0") {
        state.display = d;
      } else if (state.display === "-0") {
        state.display = "-" + d;
      } else {
        state.display += d;
      }
    }
    return state;
  }

  function decimal(state) {
    state = clone(state);
    if (state.error) state = create();
    if (state.fresh) {
      state.display = "0.";
      state.fresh = false;
      return state;
    }
    if (state.display.indexOf(".") === -1) {
      state.display += ".";
    }
    return state;
  }

  function operator(state, op) {
    state = clone(state);
    if (state.error) return state;
    var value = currentValue(state);
    if (state.pendingOp && !state.fresh) {
      var result = applyOp(state.stored, state.pendingOp, value);
      if (!isFinite(result)) {
        state.display = "Error";
        state.expression = "";
        state.stored = null;
        state.pendingOp = null;
        state.fresh = true;
        state.error = true;
        return state;
      }
      state.stored = result;
      state.display = formatNumber(result);
    } else if (state.stored === null) {
      state.stored = value;
    }
    state.pendingOp = op;
    state.expression = formatNumber(state.stored) + " " + op;
    state.fresh = true;
    return state;
  }

  function equals(state) {
    state = clone(state);
    if (state.error) return state;
    if (!state.pendingOp || state.stored === null) {
      state.expression = "";
      return state;
    }
    var value = currentValue(state);
    var result = applyOp(state.stored, state.pendingOp, value);
    if (!isFinite(result)) {
      state.display = "Error";
      state.expression = "";
      state.stored = null;
      state.pendingOp = null;
      state.fresh = true;
      state.error = true;
      return state;
    }
    state.expression =
      formatNumber(state.stored) + " " + state.pendingOp + " " + formatNumber(value) + " =";
    state.display = formatNumber(result);
    state.stored = result;
    state.pendingOp = null;
    state.fresh = true;
    return state;
  }

  function clearAll() {
    return create();
  }

  function backspace(state) {
    state = clone(state);
    if (state.error) return create();
    if (state.fresh) return state;
    var next = state.display.slice(0, -1);
    if (next === "" || next === "-") {
      state.display = "0";
      state.fresh = true;
    } else {
      state.display = next;
    }
    return state;
  }

  function toggleSign(state) {
    state = clone(state);
    if (state.error) return state;
    if (state.display === "0" || state.display === "0.") {
      return state;
    }
    if (state.display.charAt(0) === "-") {
      state.display = state.display.slice(1);
    } else {
      state.display = "-" + state.display;
    }
    return state;
  }

  function percent(state) {
    state = clone(state);
    if (state.error) return state;
    var value = currentValue(state);
    var result;
    if (state.pendingOp && (state.pendingOp === "+" || state.pendingOp === "−") && state.stored !== null) {
      result = state.stored * (value / 100);
    } else {
      result = value / 100;
    }
    state.display = formatNumber(result);
    state.fresh = true;
    return state;
  }

  function input(state, key) {
    if (key >= "0" && key <= "9") return digit(state, key);
    if (key === ".") return decimal(state);
    if (OPERATORS[key]) return operator(state, key);
    if (key === "=" || key === "Enter") return equals(state);
    if (key === "C" || key === "Escape") return clearAll();
    if (key === "Backspace") return backspace(state);
    if (key === "±") return toggleSign(state);
    if (key === "%") return percent(state);
    return state;
  }

  return {
    create: create,
    input: input,
    digit: digit,
    decimal: decimal,
    operator: operator,
    equals: equals,
    clearAll: clearAll,
    backspace: backspace,
    toggleSign: toggleSign,
    percent: percent,
    formatNumber: formatNumber,
  };
});
