import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { classifyAttendance } from "./attendanceService.js";

describe("classifyAttendance", () => {
  it("marks a 52-minute open lab as present", () => {
    assert.equal(classifyAttendance(52 * 60, 180, true), "present");
  });

  it("marks a 35-minute open lab as partial", () => {
    assert.equal(classifyAttendance(35 * 60, 180, true), "partial");
  });

  it("marks a 5-minute open lab as insufficient", () => {
    assert.equal(classifyAttendance(5 * 60, 180, true), "insufficient");
  });

  it("uses scheduled-class ratios for a 90-minute lecture", () => {
    assert.equal(classifyAttendance(70 * 60, 90, false), "present");
    assert.equal(classifyAttendance(40 * 60, 90, false), "partial");
    assert.equal(classifyAttendance(10 * 60, 90, false), "insufficient");
  });
});
