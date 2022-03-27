import { z } from "zod";
import { Result } from "~/fn/result";
import { Row } from "./models";
import type { Piece } from "./models";
import { flattenZodError } from "~/parse/zodUtils";

const HintSchema = z.array(z.number().int().min(1).max(20));

export type Hint = z.infer<typeof HintSchema>;

export const parseHint = (input: string): Result<Hint, string> => {
  const casted = input
    .trim()
    .split(" ")
    .filter((x) => x !== "") // If there were multiple spaces between items, we get an empty string
    .map((x) => parseInt(x));

  const result = HintSchema.safeParse(casted);

  if (result.success) {
    return { success: true, payload: result.data };
  }
  return {
    success: false,
    error: flattenZodError(result.error),
  };
};

/**
 * ### Logic
 * A row satisfies a hint when there are:
 * 1. Contiguous blocks of filled cells the length of each hint.
 * 2. These contiguous blocks of filled cells are separated by at least one crossed-out cell
 *
 * ### Implementation notes
 * We take advantages the string representation of cells to easily check for piece sizes.
 */
export const doesRowSatisfyHint = (row: Row, hint: Hint): boolean => {
  const filledPieces = row
    .toUniqueString()
    .split("n")
    .filter((str) => str !== "");
  const pieceSizes = filledPieces.map((cellStates) => cellStates.length);
  const matches = pieceSizes.map(
    (pieceSize, index) => pieceSize === hint[index]
  );
  const doAllMatch = matches.reduce((a, b) => a && b, true);
  return doAllMatch;
};

export const getPiecesForHint = (hint: Hint, rowSize: number): Piece[] => {
  const filledPieces: Piece[] = hint.map<Piece>((size) => {
    return { state: "Y", size };
  });

  const filledCellCount = hint.reduce((a, b) => a + b, 0);
  const emptyCellCount = rowSize - filledCellCount;

  const emptyPieces: Piece[] = [];
  for (let i = 0; i < emptyCellCount; i++) {
    emptyPieces.push({ state: "n", size: 1 });
  }

  return [...filledPieces, ...emptyPieces];
};
