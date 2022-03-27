import { ZodError } from "zod";

export const flattenZodError = (error: ZodError): string => {
  return error.issues.map((issue) => issue.message).join(". ");
};
