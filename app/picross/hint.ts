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

export const doesRowSatisfyHint = (row: Row, hint: Hint): boolean => {
  return false;
};
