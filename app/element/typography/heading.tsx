import type { FC } from "react";
import cn from "classnames";

interface Props {
  as: "h1" | "h2" | "h3" | "h4" | "h5" | "h6";
  className?: string;
}

const Heading: FC<Props> = ({ children, className }) => {
  const cx = cn("font-semibold font-serif", className);
  return <h1 className={cx}>{children}</h1>;
};

export default Heading;
