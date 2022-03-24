import { describe, expect, it } from "vitest";
import { parseHint } from "./hint";

describe("parseHint", () => {
  const valid: [string, number[]][] = [
    ["1 3 1", [1, 3, 1]],
    [" 2 2 1 2 ", [2, 2, 1, 2]],
    ["6  1   2    1", [6, 1, 2, 1]],
  ];

  it.each(valid)('should parse "%s" as a valid hint', (input, expected) => {
    const result = parseHint(input);
    if (!result.success) {
      throw new Error(`Expected hint to parse. ${result.error.join(". ")}`);
    }
    expect(result.payload).toEqual(expected);
  });
});
