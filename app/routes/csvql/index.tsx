import type { FC } from "react";
import Heading from "~/element/typography/heading";

const Index: FC = () => {
  return (
    <>
      <p>
        Upload a CSV. This site will convert it into a sqlite database and let
        you write sql queries against it.
      </p>
      <div className="space-y-2">
        <Heading as="h2" className="text-3xl">
          Upload a csv file
        </Heading>
        <form onSubmit={(e) => e.preventDefault()}>
          <input type="file" accept=".csv" />
        </form>
      </div>
    </>
  );
};

export default Index;
