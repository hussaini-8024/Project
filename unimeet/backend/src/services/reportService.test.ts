import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { attendancePercent } from "./attendanceService.js";
import { gradeFor, toCsv } from "./reportService.js";

const rules = [
  { id: 1, min_percent: 90, max_percent: 100, grade: "A", label: "Excellent" },
  { id: 2, min_percent: 80, max_percent: 89.99, grade: "B", label: "Good" },
  { id: 3, min_percent: 70, max_percent: 79.99, grade: "C", label: "Satisfactory" },
  { id: 4, min_percent: 60, max_percent: 69.99, grade: "D", label: "Low" },
  { id: 5, min_percent: 0, max_percent: 59.99, grade: "F", label: "Fail" },
];

describe("gradeFor", () => {
  it("assigns configured attendance grades", () => {
    assert.equal(gradeFor(92, rules), "A");
    assert.equal(gradeFor(87.5, rules), "B");
    assert.equal(gradeFor(70, rules), "C");
    assert.equal(gradeFor(59, rules), "F");
  });
});

describe("duration-weighted overall attendance", () => {
  it("does not blindly average subject percentages", () => {
    const subjects = [
      { actual: 90 * 60, expected: 100 * 60 },
      { actual: 80 * 60, expected: 100 * 60 },
      { actual: 70 * 60, expected: 100 * 60 },
    ];
    const overall = attendancePercent(
      subjects.reduce((sum, row) => sum + row.actual, 0),
      subjects.reduce((sum, row) => sum + row.expected, 0),
    );
    const naiveAverage = (90 + 80 + 70) / 3;
    assert.equal(overall, 80);
    assert.equal(overall, naiveAverage);

    const uneven = [
      { actual: 90 * 60, expected: 100 * 60 },
      { actual: 80 * 60, expected: 100 * 60 },
      { actual: 70 * 60, expected: 300 * 60 },
    ];
    const weighted = attendancePercent(
      uneven.reduce((sum, row) => sum + row.actual, 0),
      uneven.reduce((sum, row) => sum + row.expected, 0),
    );
    assert.equal(weighted, 48);
    assert.notEqual(weighted, naiveAverage);
    assert.equal(gradeFor(weighted, rules), "F");
    assert.equal(gradeFor(90, rules), "A");
    assert.equal(gradeFor(80, rules), "B");
    assert.equal(gradeFor(70, rules), "C");
  });
});

describe("toCsv", () => {
  it("escapes commas and quotes", () => {
    const csv = toCsv(["Name", "Note"], [["Ali, Khan", 'said "present"']]);
    assert.equal(csv, 'Name,Note\n"Ali, Khan","said ""present"""');
  });
});
