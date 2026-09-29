import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { courseSeatLimit, LECTURE_HALL_THRESHOLD, ROOM_MAX_PARTICIPANTS } from "./capacityService.js";

describe("courseSeatLimit", () => {
  it("caps every class at 2000 seats", () => {
    assert.equal(courseSeatLimit(undefined), 2000);
    assert.equal(courseSeatLimit(null), 2000);
    assert.equal(courseSeatLimit(5000), ROOM_MAX_PARTICIPANTS);
    assert.equal(courseSeatLimit(120), 120);
    assert.equal(courseSeatLimit(0), 2000);
  });

  it("uses lecture-hall join once a class is large", () => {
    assert.equal(LECTURE_HALL_THRESHOLD, 80);
    assert.ok(ROOM_MAX_PARTICIPANTS >= 2000);
  });
});
