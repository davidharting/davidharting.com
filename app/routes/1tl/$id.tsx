import { redirect, useLoaderData } from "remix";
import type { LoaderFunction } from "remix";
import type { FC } from "react";
import Layout from "~/layouts/OneTimeLinksLayout";

const Complete: FC = () => {
  const data = useLoaderData<string>();
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
          <pre>{data}</pre>
          <div className="flex w-full justify-end">
            <button>Copy</button>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default Complete;

export const loader: LoaderFunction = async ({ params }) => {
  console.log({ params });
  const id = params.id;
  if (!id) {
    return redirect("/404");
  }
  const message = await SECRET_MESSAGES.get(id);
  if (!message) {
    return redirect("/404");
  }
  return message;
};
