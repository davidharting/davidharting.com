import type { FC } from "react";
import { useLoaderData } from "remix";
import type { LoaderFunction } from "custom.remix";
import Layout from "~/layouts/OneTimeLinksLayout";
import { CopyBlock } from "~/components/CopyBlock";

const Complete: FC = () => {
  const data = useLoaderData<Data>();
  return (
    <Layout>
      <div className="space-y-8">
        <h1 className="text-3xl font-semibold font-serif">
          Share your secret message
        </h1>
        <p>
          Copy the link below and share it with your recipient. The link can be
          used exactly once, and will only work for an hour.
        </p>
        <CopyBlock text={data.link} />
      </div>
    </Layout>
  );
};

export default Complete;

export const loader: LoaderFunction = ({ request }) => {
  return {
    link: request.url.replace("/share", ""),
  };
};

interface Data {
  link: string;
}
