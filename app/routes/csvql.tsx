import type { FC } from "react";
import { Outlet } from "remix";
import Heading from "~/element/typography/heading";

const Csvql: FC = () => {
  return (
    <div className="m-auto max-w-2xl mt-12 font-sans px-2 md:px-0">
      <div className="space-y-6">
        <Heading as="h1" className="text-5xl">
          csvql
        </Heading>
        <Outlet />
      </div>
    </div>
  );
};

export default Csvql;
