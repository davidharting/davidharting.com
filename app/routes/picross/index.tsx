import { Form, useActionData, useTransition } from "remix";
import { z } from "zod";
import type { FC } from "react";
import Heading from "~/element/typography/heading";
import { TextInput } from "~/form/TextInput";
import { atom, useAtom } from "jotai";
import { Hint, parseHint } from "~/picross/hint";
import { Row } from "~/picross/models";
import { findAllPermutations, findOverlap } from "~/picross/permutations";
import { Row as RowComponent } from "~/picross/components/row";
import { ActionFunction } from "custom.remix";
import { Result } from "~/fn/result";
import { Submit } from "~/form/Submit";

const hintInputAtom = atom("");

const rowSizeInputAtom = atom<number>(10);

const PicrossPage: FC = () => {
  const [rowSize, setRowSize] = useAtom(rowSizeInputAtom);
  const [hintValue, setHintValue] = useAtom(hintInputAtom);
  const actionData = useActionData<ActionData>();
  const transition = useTransition();

  const formErrors =
    actionData?.success === false ? actionData.error : undefined;

  const permutations = actionData?.success ? actionData.payload : undefined;
  const rows = permutations
    ? permutations.map((permutation) => new Row(permutation.cells))
    : null;

  const overlap: Set<number> = rows ? findOverlap(rows) : new Set<number>();

  return (
    <div className="m-auto max-w-2xl mt-12 font-sans px-2 md:px-0">
      <div className="space-y-4">
        <Heading as="h1" className="text-5xl">
          Picross Permutations
        </Heading>
        <p>
          This is a tool to help with nonogram puzzles. It helps determine all
          the possible permutations for a hint.
        </p>
      </div>
      <div className="mt-8">
        <Form method="post" className="space-y-4">
          <fieldset disabled={transition.state !== "idle"}>
            <div className="flex flex-col space-y-1">
              <label htmlFor="rowSize">Row Size</label>
              <select
                name="rowSize"
                value={rowSize}
                onChange={(e) => setRowSize(Number(e.target.value))}
                className="ring-1 p-2 ring-slate-900/10 shadow-sm rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500  dark:bg-slate-700 dark:focus:ring-teal-800 dark:focus:bg-slate-900"
              >
                <option value={5}>5</option>
                <option value={10}>10</option>
                <option value={15}>15</option>
                <option value={20}>20</option>
              </select>
            </div>
            <TextInput
              error={formErrors?.hint.join(" ")}
              required
              label="Hint"
              name="hint"
              onChange={(newValue) => setHintValue(newValue)}
              placeholder="1 3 1"
              value={hintValue}
            />
          </fieldset>
          <div className="mt-4">
            <Submit
              disabled={transition.state !== "idle"}
              label={transition.state === "idle" ? "Submit" : "Calculating..."}
            />
          </div>
        </Form>
      </div>
      {transition.state !== "idle" ? (
        <div className="mt-8 w-100 flex flex-row justify-center">
          <div className="animate-spin w-4 h-4 bg-teal-700" />
        </div>
      ) : null}
      {rows ? (
        <div className="flex flex-col space-y-2 mt-8">
          {overlap.size === 0 ? (
            <p>No cell is filled in every permutation.</p>
          ) : (
            <p>
              The following cells to overlap across all permutations:&nbsp;
              {Array.from(overlap)
                .map((cellNumber) => cellNumber + 1)
                .join(", ")}
              .
            </p>
          )}
          {rows.map((row) => (
            <RowComponent
              key={row.toUniqueString()}
              cells={row.getCells()}
              highlight={overlap}
            />
          ))}
        </div>
      ) : null}
    </div>
  );
};

export default PicrossPage;

type ActionData = Result<Row[], RequestParamterIssues>;
export const action: ActionFunction<ActionData> = async ({ request }) => {
  const params = await parameters(request);
  if (params.success === false) {
    return { success: false, error: params.error };
  }

  const permutations = findAllPermutations(
    params.payload.hint,
    params.payload.rowSize
  );

  const result: ActionData = { success: true, payload: permutations };

  return result;
};

const HintSchema = z
  .string()
  .regex(/[\d\s]/, "Only digits and spaces are allowed.");
const RowSizeSchema = z.number().min(5).max(20).multipleOf(5);

interface RequestParameterValues {
  rowSize: number;
  hint: Hint;
}
interface RequestParamterIssues {
  rowSize: string[];
  hint: string[];
}

const parameters = async (
  request: Request
): Promise<Result<RequestParameterValues, RequestParamterIssues>> => {
  const formData = await request.formData();
  const rowSizeInputValue = formData.get("rowSize");
  const hintInputValue = formData.get("hint");
  console.log({
    rowSizeInputValue,
    hintInputValue,
  });

  const rowSizeIssues: string[] = [];
  const hintIssues: string[] = [];

  let rowSize: number | null = null;
  const parsedRowSize = await RowSizeSchema.safeParseAsync(
    Number(rowSizeInputValue)
  );
  if (parsedRowSize.success === false) {
    parsedRowSize.error.issues.forEach((issue) => {
      rowSizeIssues.push(issue.message);
    });
  } else {
    rowSize = parsedRowSize.data;
  }

  let hint: Hint | null = null;
  const parsedHintResult = await HintSchema.safeParseAsync(hintInputValue);
  if (parsedHintResult.success === false) {
    parsedHintResult.error.issues.forEach((issue) =>
      hintIssues.push(issue.message)
    );
  } else {
    const picrossHintResult = parseHint(parsedHintResult.data);
    if (picrossHintResult.success === false) {
      hintIssues.push(picrossHintResult.error);
    } else {
      hint = picrossHintResult.payload;
    }
  }
  if (
    rowSizeIssues.length > 0 ||
    hintIssues.length > 0 ||
    hint === null ||
    rowSize === null
  ) {
    return {
      success: false,
      error: {
        rowSize: rowSizeIssues,
        hint: hintIssues,
      },
    };
  } else {
    return { success: true, payload: { hint, rowSize } };
  }
};
