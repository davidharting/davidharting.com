import type { FC } from "react";

interface Props {
  disabled?: boolean;
  label: string;
}

export const Submit: FC<Props> = ({ disabled, label }) => {
  return (
    <input
      type="submit"
      value={label}
      className="bg-teal-700 text-slate-100 px-4 py-2 rounded-md hover:ring-teal-700 hover:ring hover:bg-teal-800 active:ring-0 hover:cursor-pointer disabled:cursor-not-allowed"
      disabled={disabled ?? false}
    />
  );
};
