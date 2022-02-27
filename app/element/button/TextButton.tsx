import type { FC, HTMLProps } from "react";

export const TextButton: FC<HTMLProps<HTMLButtonElement>> = ({
  children,
  onClick,
}) => {
  return (
    <button
      onClick={onClick}
      className="p-2 rounded-md active:shadow-inner hover:opacity-90"
    >
      {children}
    </button>
  );
};
