import { describe, expect, it } from "vitest";
import { doesRowSatisfyHint, parseHint } from "./hint";
import { Row } from "./models";

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

  it("should return true if the row satisfies the hint", () => {
    const hint = [1, 3, 1];
    const row = new Row(["Y", "n", "Y", "Y", "Y", "n", "Y"]);
    expect(doesRowSatisfyHint(row, hint)).toBe(true);
  });
});
