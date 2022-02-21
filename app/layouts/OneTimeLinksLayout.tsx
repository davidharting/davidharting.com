import type { FC } from "react";

const OneTimeLinksLayout: FC = ({ children }) => {
  return (
    <div className="m-auto max-w-2xl mt-12 font-sans px-2 md:px-0">
      {children}
    </div>
  );
};

export default OneTimeLinksLayout;
