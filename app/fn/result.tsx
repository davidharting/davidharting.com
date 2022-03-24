export type Result<T, E = string[]> =
  | { success: true; payload: T }
  | { success: false; error: E };
