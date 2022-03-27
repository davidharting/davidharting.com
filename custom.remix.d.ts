/**
 * A function that loads data for a route.
 */
export interface LoaderFunction<T> {
  (args: DataFunctionArgs): Promise<Response> | Response | Promise<T> | T;
}

export interface ActionFunction<T> {
  (args: DataFunctionArgs): Promise<Response> | Response | Promise<T> | T;
}

interface DataFunctionArgs {
  request: Request;
  context: { SECRET_MESSAGES: KVNamespace };
  params: Params;
}
