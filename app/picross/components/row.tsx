import type { FC } from "react";
import cn from "classnames";
import type { CellState } from "../models";

interface Props {
  cells: CellState[];
}

export const Row: FC<Props> = ({ cells }) => {
  const rowSize = cells.length;
  if (rowSize < 1) {
    return null;
  }

  const cellSize = Math.round((1 / rowSize) * 100);
  return (
    <div className="flex flex-row flex-nowrap w-full">
      {cells.map((cell, i) => (
        <Cell key={i} state={cell} size={cellSize} />
      ))}
    </div>
  );
};

interface CellProps {
  state: CellState;
  size: number;
}
const Cell: FC<CellProps> = ({ size, state }) => {
  return (
    <div
      style={{
        flex: size,
      }}
      className={cn("border-gray-100 h-4 m-1", {
        "bg-teal-700": state === "Y",
        "bg-slate-400": state === "n",
      })}
    />
  );
};
