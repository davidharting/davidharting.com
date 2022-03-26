import { Piece, Row } from "./models";
import { findAllPermutations } from "./permutations";

describe("findAllPermutations", () => {
  it("should return an empty array if given an empty array", () => {
    const permutations = findAllPermutations([]);
    expect(permutations).toEqual([]);
  });

  it.todo(
    "should return all unique permutations for a given set of pieces",
    () => {
      const pieces: Piece[] = [
        { state: "Y", size: 2 },
        { state: "Y", size: 1 },
        { state: "n", size: 1 },
        { state: "n", size: 1 },
      ];
      const permutations = findAllPermutations(pieces);
      expect(permutations).toEqual([
        new Row(["Y", "Y", "n", "Y", "n"]),
        new Row(["Y", "Y", "n", "n", "Y"]),
      ]);
    }
  );
});

// Hint 2 1
// Row length = 5
// 00x0x
// 00xx0
