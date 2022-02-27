import type { FC } from "react";
import { NavLink } from "remix";

const OneTimeLinksLayout: FC = ({ children }) => {
  return (
    <div className="m-auto max-w-2xl mt-12 font-sans px-2 md:px-0">
      <div>
        <NavLink
          to="/1tl"
          className="text-teal-600 dark:text-teal-400 hover:underline"
        >
          One Time Links
        </NavLink>
      </div>
      {children}
    </div>
  );
};

export default OneTimeLinksLayout;
