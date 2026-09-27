import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  attendancePercent,
  classifyAttendance,
  sessionExpectedSeconds,
  sumSegmentSeconds,
} from "./attendanceService.js";

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

describe("sumSegmentSeconds", () => {
  it("counts 10:00-10:20 plus 10:40-11:00 as 40 minutes, not 60", () => {
    const seconds = sumSegmentSeconds([
      { joined_at: "2026-09-20T10:00:00.000Z", left_at: "2026-09-20T10:20:00.000Z" },
      { joined_at: "2026-09-20T10:40:00.000Z", left_at: "2026-09-20T11:00:00.000Z" },
    ]);
    assert.equal(seconds, 40 * 60);
    assert.notEqual(seconds, 60 * 60);
  });

  it("sums multiple rejoin events", () => {
    const seconds = sumSegmentSeconds([
      { joined_at: "2026-09-20T10:00:00.000Z", left_at: "2026-09-20T10:20:00.000Z" },
      { joined_at: "2026-09-20T10:40:00.000Z", left_at: "2026-09-20T11:00:00.000Z" },
      { joined_at: "2026-09-20T11:10:00.000Z", left_at: "2026-09-20T11:30:00.000Z" },
    ]);
    assert.equal(seconds, 60 * 60);
  });
});

describe("attendancePercent", () => {
  it("uses actual / expected duration", () => {
    assert.equal(attendancePercent(80 * 60, 100 * 60), 80);
  });
});

describe("sessionExpectedSeconds", () => {
  it("uses actual start and ended_at when present", () => {
    assert.equal(
      sessionExpectedSeconds({
        start_time: "2026-09-20T09:00:00.000Z",
        end_time: "2026-09-20T12:00:00.000Z",
        actual_start: "2026-09-20T10:00:00.000Z",
        ended_at: "2026-09-20T11:40:00.000Z",
        status: "ended",
      }),
      100 * 60,
    );
  });
});
