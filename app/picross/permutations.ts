import { Row, Piece } from "./models";
import { Permutation } from "permutation-sjt";
import { doesRowSatisfyHint, getPiecesForHint } from "./hint";
import type { Hint } from "./hint";

/**
 * This is a very naive implementation. We produce all possible permutations of the pieces, ignoring the hint that generated this configuration of pieces.
 * We produce many duplicates and many rows that do not satisfy the hint that we must filter out.
 * Per permutation-sjt documentation library, this will take minutes if we have 13 or more pieces, but should be performant below that.
 */
export const findAllPermutations = (hint: Hint, rowSize: number): Row[] => {
  if (hint.length < 1) {
    return [];
  }
  const pieces = getPiecesForHint(hint, rowSize);
  if (pieces.length < 1) {
    return [];
  }

  const permutations: Row[] = [];
  const permutator = new Permutation(pieces.length);

  while (permutator.hasNext()) {
    const nextPermutation = permutator.next();
    const row = new Row(rowSize);
    nextPermutation.forEach((value) => {
      row.addPiece(pieces[value]);
    });

    if (doesRowSatisfyHint(row, hint) && isUnique(permutations, row)) {
      permutations.push(row);
    }
  }
  return permutations;
};

/**
 * ### Notes
 * If Rows were Records or Tuples, I think I could use a Set for my permutations, and not have to handle equality myself.
 * See https://stackoverflow.com/questions/29759480/how-to-customize-object-equality-for-javascript-set for more on this.
 */
const isUnique = (permutations: Row[], row: Row): boolean => {
  for (let i = 0; i < permutations.length; i++) {
    if (row.isEqual(permutations[i])) {
      return false;
    }
  }
  return true;
};

export const findOverlap = (rows: Row[]): Set<number> => {
  if (rows.length < 1) {
    return new Set<number>();
  }
  const rowSize = rows[0].getSize();

  const finalSet = new Set<number>();

  const sets = rows.map((row) => row.filledCells());
  for (let i = 0; i < rowSize; i++) {
    const satisfiesAll = sets
      .map((set) => set.has(i))
      .reduce((a, b) => a && b, true);
    if (satisfiesAll) {
      finalSet.add(i);
    }
  }

  return finalSet;
};
