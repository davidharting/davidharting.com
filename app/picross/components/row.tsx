import type { FC } from "react";
import cn from "classnames";
import type { CellState } from "../models";

interface Props {
  cells: CellState[];
  highlight: Set<number>;
}

export const Row: FC<Props> = ({ cells, highlight }) => {
  const rowSize = cells.length;
  if (rowSize < 1) {
    return null;
  }

  const cellSize = Math.round((1 / rowSize) * 100);
  return (
    <div className="flex flex-row flex-nowrap w-full">
      {cells.map((cell, i) => (
        <Cell
          key={i}
          state={cell}
          size={cellSize}
          highlight={highlight.has(i)}
        />
      ))}
    </div>
  );
};

interface CellProps {
  state: CellState;
  size: number;
  highlight: boolean;
}
const Cell: FC<CellProps> = ({ highlight, size, state }) => {
  return (
    <div
      style={{
        flex: size,
      }}
      className={cn(" h-4 m-1", {
        "bg-teal-700 border-gray-100": state === "Y" && highlight === false,
        "bg-teal-400  border-orange-600 dark:border-orange-400 border-2":
          state === "Y" && highlight === true,
        "bg-slate-400": state === "n",
      })}
    />
  );
};
