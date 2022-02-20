import type { FC } from "react";
import Layout from "~/layouts/OneTimeLinksLayout";

const Complete: FC = () => {
  return (
    <Layout>
      <div className="space-y-8">
        <h1 className="text-3xl font-semibold font-serif">
          Someone sent you a secret message.
        </h1>
        <p>
          <span className="font-semibold">
            This is your only chance to view this message.
          </span>
          &nbsp; When you reload the page it will be gone.
        </p>
        <div className="space-y-4 p-8 shadow-md rounded-md  dark:bg-slate-700">
          <pre>Loader data goes here</pre>
          <div className="flex w-full justify-end">
            <button>Copy</button>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default Complete;
