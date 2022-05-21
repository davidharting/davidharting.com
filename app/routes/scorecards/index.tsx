import type { FC } from "react";
import { ClientOnly } from "~/components/ClientOnly";
import Heading from "~/element/typography/heading";

const ScorecardsPage: FC = () => {
  return (
    <div className="m-auto max-w-2xl mt-12 font-sans px-2 md:px-0">
      <div className="space-y-4">
        <Heading as="h1" className="text-5xl">
          Scorecards
        </Heading>
        <p>Keep score while you play games.</p>
      </div>
      <div className="mt-8">
        <ClientOnly>
          <p>Hello from client-side render</p>
        </ClientOnly>
      </div>
    </div>
  );
};

export default ScorecardsPage;
