import type { FC } from "react";

interface Props {
  error?: string;
  label: string;
  name: string;
  onChange: (newValue: string) => void;
  placeholder: string;
  required: boolean;
  value: string;
}

export const TextInput: FC<Props> = ({
  error,
  label,
  name,
  onChange,
  placeholder,
  required,
  value,
}) => {
  return (
    <div className="flex flex-col space-y-1">
      <label htmlFor={name}>{label}</label>
      <input
        name={name}
        className="ring-1 p-2 ring-slate-900/10 shadow-sm rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500 caret-teal-500 dark:bg-slate-700 dark:focus:ring-teal-800 dark:caret-teal-800 dark:focus:bg-slate-900"
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        value={value}
        required={required}
      />
      {<p className="text-sm text-red-700 dark:text-red-500">{error}</p>}
    </div>
  );
};
