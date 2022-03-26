import { describe, expect, it } from "vitest";
import { doesRowSatisfyHint, parseHint, getPiecesForHint, Hint } from "./hint";
import { Piece, Row } from "./models";

describe("parseHint", () => {
  const valid: [string, number[]][] = [
    ["1 3 1", [1, 3, 1]],
    [" 2 2 1 2 ", [2, 2, 1, 2]],
    ["6  1   2    1", [6, 1, 2, 1]],
  ];

  it.each(valid)('should parse "%s" as a valid hint', (input, expected) => {
    const result = parseHint(input);
    if (!result.success) {
      throw new Error(`Expected hint to parse. ${result.error}`);
    }
    expect(result.payload).toEqual(expected);
  });
});

describe("doesRowSatisfyHint", () => {
  it("should return false if the row does not statisfy the hint", () => {
    const hint = [1, 2, 3];
    const row = new Row(["Y", "Y", "n", "Y", "Y", "n", "n", "Y", "n", "n"]);
    expect(doesRowSatisfyHint(row, hint)).toBe(false);
  });

  type TestCase = { row: Row; hint: Hint };
  const testCases: Array<TestCase> = [
    { row: new Row(["Y", "n", "Y", "Y", "Y", "n", "Y"]), hint: [1, 3, 1] },
    { row: new Row(["Y", "Y", "n", "Y", "n"]), hint: [2, 1] },
    { row: new Row(["Y", "Y", "n", "n", "Y"]), hint: [2, 1] },
  ];

  it.each(testCases)(
    "should return true if the row satisfies the hint %j",
    (testCase) => {
      expect(doesRowSatisfyHint(testCase.row, testCase.hint)).toBe(true);
    }
  );
});

describe("getPiecesForHint", () => {
  it("should get pieces for the hint", () => {
    const hint = [1, 3, 2];
    const actual = getPiecesForHint(hint, 10);
    const expected: Piece[] = [
      { state: "Y", size: 1 },
      { state: "Y", size: 3 },
      { state: "Y", size: 2 },
      { state: "n", size: 1 },
      { state: "n", size: 1 },
      { state: "n", size: 1 },
      { state: "n", size: 1 },
    ];
    expect(actual).toEqual(expected);
  });
});
