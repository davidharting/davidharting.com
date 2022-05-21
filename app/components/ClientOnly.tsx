// Implementation taken from Ryan Florence in this GitHub discussion: https://github.com/remix-run/remix/discussions/2852
import type { FC, ReactNode } from "react";
import { useEffect, useState } from "react";

let hydrating = true;

interface Props {
  fallback?: ReactNode;
}

export const ClientOnly: FC<Props> = ({ children, fallback }) => {
  let [hydrated, setHydrated] = useState(() => !hydrating);
  useEffect(() => {
    hydrating = false;
    setHydrated(true);
  }, []);
  if (hydrated) {
    return <>{children}</>;
  }
  return <>{fallback}</>;
};
