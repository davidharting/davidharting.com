import { useEffect, useState } from "react";
import type { FC } from "react";
import { Form, redirect } from "remix";
import type { ActionFunction } from "remix";
import Layout from "~/layouts/OneTimeLinksLayout";
import { createKey, encrypt } from "~/crypto/aes";

const OneTimeLinks: FC = () => {
  const [key, setKey] = useState<CryptoKey | null>(null);
  const [exportedKey, setExportedKey] = useState<string | null>(null);
  const [encryptedMessage, setEncryptedMessage] = useState<string>("");
  const [iv, setIv] = useState<string | null>(null);
  useEffect(() => {
    const setupKey = async () => {
      const { key, exported } = await createKey();
      setKey(key);
      setExportedKey(exported);
    };
    setupKey();
  }, []);

  return (
    <Layout>
      <h1 className="text-3xl font-semibold font-serif">One-Time Links</h1>
      <p className="mt-8">
        Create a link to a private message. The contents of your message will be
        encrypted client-side, so the site owner can't read it. The message will
        be deleted after 1 hour or when it is viewed, whichever comes first.
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
                onChange={async (e) => {
                  if (key) {
                    const { ciphertext, iv } = await encrypt(
                      key,
                      e.target.value
                    );
                    setEncryptedMessage(ciphertext);
                    setIv(iv);
                  }
                }}
                id="plaintextMessage"
                disabled={key === null ?? true}
                rows={3}
                maxLength={500}
                required
                className="mt-1 p-2 roundeed ring-1 ring-slate-900/10 shadow-sm rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500 caret-teal-500 dark:bg-slate-700 dark:focus:ring-teal-800 dark:caret-teal-800 dark:focus:bg-slate-900"
              />
              <input
                name="encryptedMessage"
                type="hidden"
                value={encryptedMessage}
              />
              <input name="key" type="hidden" value={exportedKey ?? ""} />
              <input name="iv" type="hidden" value={iv ?? ""} />
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

export const action: ActionFunction = async ({ request }) => {
  const formData = await request.formData();
  const encryptedMessage = formData.get("encryptedMessage");
  const key = formData.get("key");
  const iv = formData.get("iv");
  // @ts-ignore
  const id = crypto.randomUUID(); // This is available in the Web Worker API, but TS does not know that here

  await SECRET_MESSAGES.put(id, encryptedMessage as string, {
    expirationTtl: 60 * 60,
  });
  const oneTimeLink = `${request.url}/share/${id}/?key=${encodeURIComponent(
    key as string
  )}&iv=${encodeURIComponent(iv as string)}`;
  return redirect(oneTimeLink);
};
