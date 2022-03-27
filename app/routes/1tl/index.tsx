import type { FC } from "react";
import { Form, redirect } from "remix";
import type { ActionFunction } from "custom.remix";
import Layout from "~/layouts/OneTimeLinksLayout";

const OneTimeLinks: FC = () => {
  return (
    <Layout>
      <h1 className="text-3xl font-semibold font-serif">One-Time Links</h1>
      <p className="mt-8">
        Create a link to a message. The contents of your message will be stored
        in plain text. This is something I built for fun, and not appropriate
        for sharing sensitive information. The message will be deleted 1 hour
        after it's creation, or after it is viewed for the first time (whichever
        comes first).
      </p>
      <hr className="mt-4" />
      <div className="mt-8">
        <Form method="post" reloadDocument>
          <fieldset>
            <div className="flex flex-col">
              <label htmlFor="message" className="">
                Secret Message
              </label>
              <textarea
                name="message"
                rows={3}
                maxLength={500}
                required
                className="mt-1 p-2 roundeed ring-1 ring-slate-900/10 shadow-sm rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500 caret-teal-500 dark:bg-slate-700 dark:focus:ring-teal-800 dark:caret-teal-800 dark:focus:bg-slate-900"
              />
            </div>

            <br />
            <input
              type="submit"
              value="Create"
              className="bg-teal-700 text-slate-100 px-4 py-2 rounded-md hover:ring-teal-700 hover:ring hover:bg-teal-800 active:ring-0 hover:cursor-pointer"
            />
          </fieldset>
        </Form>
      </div>
    </Layout>
  );
};

export default OneTimeLinks;

export const action: ActionFunction<null> = async ({ request, context }) => {
  console.log("form action context", { context });
  const formData = await request.formData();
  const message = formData.get("message");
  // @ts-ignore
  const id = crypto.randomUUID(); // This is available in the Web Worker API, but TS does not know that here

  await context.SECRET_MESSAGES.put(id, message as string, {
    expirationTtl: 60 * 60,
  });
  const messageShareUrl = `${request.url}/share/${id}/`;
  return redirect(messageShareUrl);
};
