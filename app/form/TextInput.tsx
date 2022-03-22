import type { FC } from "react";

interface Props {
  label: string;
  name: string;
  placeholder: string;
}

export const TextInput: FC<Props> = ({ label, name, placeholder }) => {
  return (
    <div className="flex flex-col space-y-1">
      <label htmlFor={name}>{label}</label>
      <input
        name={name}
        className="ring-1 p-2 ring-slate-900/10 shadow-sm rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500 caret-teal-500 dark:bg-slate-700 dark:focus:ring-teal-800 dark:caret-teal-800 dark:focus:bg-slate-900"
        placeholder={placeholder}
      />
    </div>
  );
};
