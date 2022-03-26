import type { FC } from "react";
import { Link } from "remix";

interface Props {
  to: string;
}

export const InternalLink: FC<Props> = ({ children, to }) => {
  return (
    <Link
      to={to}
      className="font-semibold text-teal-700 dark:text-teal-400 hover:underline decoration-2 hover:animate-pulse hover:shadow-lg"
    >
      {children}
    </Link>
  );
};
