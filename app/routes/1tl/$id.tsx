import { useLoaderData } from "remix";
import type { LoaderFunction } from "custom.remix";
import type { FC } from "react";
import { useState } from "react";
import cn from "classnames";
import Layout from "~/layouts/OneTimeLinksLayout";
import { CopyBlock } from "~/components/CopyBlock";
import { TextButton } from "~/element/button/TextButton";

const Complete: FC = () => {
  const data = useLoaderData<Data | null>();
  const [plaintext, setPlaintext] = useState<string | null>(null);

  const onRevealClick = async () => {
    if (data) {
      setPlaintext(data.message);
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
            <CopyBlock text={plaintext ?? "..."} />
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
  message: string;
}

export const loader: LoaderFunction = async ({ context, request, params }) => {
  const id = params.id;
  if (!id || Array.isArray(id)) {
    return null;
  }
  const message = await context.SECRET_MESSAGES.get(id);
  if (!message) {
    return null;
  }
  await context.SECRET_MESSAGES.delete(id);
  const url = new URL(request.url);

  return {
    message,
  };
};
