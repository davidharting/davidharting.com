import { redirect, useLoaderData } from "remix";
import type { LoaderFunction } from "remix";
import type { FC } from "react";
import { useState } from "react";
import cn from "classnames";
import Layout from "~/layouts/OneTimeLinksLayout";
import { CopyBlock } from "~/components/CopyBlock";
import { TextButton } from "~/element/button/TextButton";
import { decrypt } from "~/crypto/aes";

const Complete: FC = () => {
  const data = useLoaderData<Data | null>();
  const [plaintext, setPlaintext] = useState<string | null>(null);

  const onRevealClick = async () => {
    if (data) {
      const result = await decrypt(data.key, data.iv, data.ciphertext);
      setPlaintext(result);
    }
  };

  if (data === null) {
    return (
      <Layout>
        <div className="space-y-8">
          <h1 className="text-3xl font-semibold font-serif">
            Message not found
          </h1>
          <p>
            This looks like a link to a message, but this particular message
            does not exist.
          </p>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <div className="space-y-8">
        <h1 className="text-3xl font-semibold font-serif">
          Someone sent you a secret message.
        </h1>
        <p>
          <span className="font-semibold">
            This is your only chance to decrypt and view this message.
          </span>
          &nbsp; When you reload the page or use this link again, it will be
          gone.
        </p>
        <div className="relative">
          <div className={cn({ blur: plaintext === null })}>
            <CopyBlock text={plaintext ?? data.ciphertext} />
          </div>
          <div
            className={cn(
              "absolute flex justify-center items-center h-full w-full top-0 left-0",
              { hidden: plaintext !== null }
            )}
          >
            <TextButton onClick={onRevealClick}>Decrypt and Reveal</TextButton>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default Complete;

interface Data {
  ciphertext: string;
  key: string;
  iv: string;
}

export const loader: LoaderFunction = async ({ request, params }) => {
  console.log({ params });
  const id = params.id;
  if (!id) {
    return redirect("/404");
  }
  const message = await SECRET_MESSAGES.get(id);
  if (!message) {
    return null;
  }
  // await SECRET_MESSAGES.delete(id);
  const url = new URL(request.url);

  return {
    ciphertext: message,
    key: url.searchParams.get("key"),
    iv: url.searchParams.get("iv"),
  };
};
