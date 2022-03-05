import type { AppData } from "remix";

/**
 * A function that loads data for a route.
 */
export interface LoaderFunction {
  (args: DataFunctionArgs):
    | Promise<Response>
    | Response
    | Promise<AppData>
    | AppData;
}

export interface ActionFunction {
  (args: DataFunctionArgs):
    | Promise<Response>
    | Response
    | Promise<AppData>
    | AppData;
}

interface DataFunctionArgs {
  request: Request;
  context: { SECRET_MESSAGES: KVNamespace };
  params: Params;
}
