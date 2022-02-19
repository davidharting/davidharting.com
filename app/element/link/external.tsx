import type { FC } from "react";

interface Props {
  href: string;
}

const ExternalLink: FC<Props> = ({ children, href }) => {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className="font-semibold text-teal-700 dark:text-teal-400 hover:underline decoration-2 hover:animate-pulse hover:shadow-lg"
    >
      {children}
    </a>
  );
};

export default ExternalLink;
