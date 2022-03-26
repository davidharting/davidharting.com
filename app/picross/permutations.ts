import { Row, Piece } from "./models";
import { Permutation } from "permutation-sjt";

/**
 * This is a very naive implementation. We produce all possible permutations of the pieces, ignoring the hint that generated this configuration of pieces.
 * We produce many duplicates and many rows that do not satisfy the hint that we must filter out.
 * Per permutation-sjt documentation library, this will take minutes if we have 13 or more pieces, but should be performant below that.
 */
export const findAllPermutations = (pieces: Piece[]): Row[] => {
  if (pieces.length < 1) {
    return [];
  }
  const rowSize = pieces.map((piece) => piece.size).reduce((a, b) => a + b);

  const permutations: Row[] = [];
  const permutator = new Permutation(pieces.length);

  while (permutator.hasNext()) {
    const nextPermutation = permutator.next();
    const row = new Row(rowSize);
    nextPermutation.forEach((value) => {
      row.addPiece(pieces[value]);
    });

    // TODO: Validate that row matches hint
    // TODO: Validate that row is not a duplicate

    permutations.push(row);
  }

  return permutations;
};
