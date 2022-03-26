import { Result } from "~/fn/result";
import { z } from "zod";
import { Row } from "./models";

const HintSchema = z.array(z.number());

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
    error: "Only integers and spaces are permitted in the input.",
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
  const filledPieces = row.toUniqueString().split("n");
  const pieceSizes = filledPieces.map((cellStates) => cellStates.length);
  const matches = pieceSizes.map(
    (pieceSize, index) => pieceSize === hint[index]
  );
  const doAllMatch = matches.reduce((a, b) => a && b);
  return doAllMatch;
};
