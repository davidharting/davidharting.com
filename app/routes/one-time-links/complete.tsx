import type { FC } from "react";
import Layout from "~/layouts/OneTimeLinksLayout";

const Complete: FC = () => {
  return (
    <Layout>
      <h1>You created a link!</h1>
      <p>
        Now you can share it with someone you trust by copying the link below.
        It will be destroyed when it is read for the first time, or at XYZ,
        whichever comes first.
      </p>
    </Layout>
  );
};

export default Complete;
