import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { answerFromCorpus, extractiveSummary, generateLocalQuiz } from "./aiService.js";

const sample = `
A transaction is a logical unit of work. ACID properties are Atomicity, Consistency, Isolation, and Durability.
Normalization reduces redundancy. Third Normal Form removes transitive dependency.
Indexes speed lookups. A B-plus tree index on university_id makes login fast.
The JOIN CLASS authorization check must happen on the UniMeet backend before a LiveKit token is issued.
`;

describe("local AI helpers", () => {
  it("builds an extractive summary", () => {
    const summary = extractiveSummary(sample, 3);
    assert.ok(summary.length > 40);
  });

  it("builds a quiz with choices", () => {
    const quiz = generateLocalQuiz(sample, "Test quiz");
    assert.ok(quiz.questions.length >= 3);
    assert.ok(quiz.questions[0].choices.length >= 3);
  });

  it("answers from the lecture corpus", () => {
    const result = answerFromCorpus("What is a transaction?", sample);
    assert.match(result.answer.toLowerCase(), /transaction/);
  });
});
