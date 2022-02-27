import { useState } from "react";
import type { FC } from "react";
import cn from "classnames";

interface Props {
  text: string;
}

export const CopyBlock: FC<Props> = ({ text }) => {
  const [isAlertHidden, setIsAlertHidden] = useState<boolean>(true);
  const copy = () => {
    navigator.clipboard.writeText(text);
    setIsAlertHidden(false);
    setTimeout(() => {
      setIsAlertHidden(true);
    }, 3000);
  };
  return (
    <div className="space-y-4 p-8 shadow-md rounded-md  dark:bg-slate-700">
      <pre>{text}</pre>
      <div className="flex w-full justify-end">
        <div
          className={cn("text-sm", {
            "opacity-0": isAlertHidden,
            "opacity-100": !isAlertHidden,
          })}
          style={{ transition: "opacity 0.5s ease-in-out" }}
        >
          Text copied to clipboard
        </div>
        <button
          onClick={copy}
          className="p-2 rounded-md hover:opacity-80 active:shadow-inner"
        >
          Copy
        </button>
      </div>
    </div>
  );
};
