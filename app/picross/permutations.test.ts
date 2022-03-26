import { Piece, Row } from "./models";
import { findAllPermutations } from "./permutations";

describe("findAllPermutations", () => {
  it("should return an empty array if given an empty array", () => {
    const permutations = findAllPermutations([], 5);
    expect(permutations).toEqual([]);
  });

  it("should return all unique permutations for a given set of pieces", () => {
    const permutations = findAllPermutations([2, 1], 5);
    const permutationSet = new Set(
      permutations.map((row) => row.toUniqueString())
    );
    expect(permutationSet).toEqual(new Set(["nYYnY", "YYnYn", "YYnnY"]));
  });
});
