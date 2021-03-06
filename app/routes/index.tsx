import { ExternalLink } from "~/element/link/external";
import { InternalLink } from "~/element/link/internal";

export default function Index() {
  return (
    <div className="antialiased m-auto max-w-2xl my-16 font-sans px-2">
      <h1 className="text-3xl font-serif font-semibold">
        Hi! I'm David Harting,
      </h1>
      <p className="text-lg mt-2">and it's a great day to build software ☀️</p>

      <div className="mt-8 w-full flex justify-center ">
        <img
          src="images/david-headshot.jpg"
          className="rounded-lg shadow-lg"
          style={{ width: "288px", height: "415.8px" }}
        />
      </div>

      <h2 className="text-2xl mt-8 font-serif font-semibold">About me</h2>
      <div className="space-y-4">
        <p>
          I am an experienced, full-stack software engineer from Westfield,
          Indiana. My focus in my career has been web apps that enable people to
          work with data. I am now working as an engineering manager at{" "}
          <a href="https://www.getdbt.com">dbt Labs</a>, building a web-based
          IDE for analytics engineers.
        </p>
        <p>
          At work, I am happiest working closely with product and design to
          navigate tradeoffs and to ship quickly. I am passionate about code
          review and testing.
        </p>
        <p>
          I believe in working hard and living slow. I enjoy life with my wife
          and my dog. I am fortunate enough to enjoy leisure time, which is
          filled with walks, wine, books, and games.
        </p>
      </div>
      <h2 className="text-2xl mt-8 font-serif font-semibold">Let's connect</h2>
      <div>
        <p>
          ✍️ I{" "}
          <ExternalLink href="https://world.hey.com/david.harting">
            write on Hey World
          </ExternalLink>
          . Or, you can find me on{" "}
          <ExternalLink href="https://github.com/davidharting">
            GitHub
          </ExternalLink>
          ,{" "}
          <ExternalLink href="https://www.twitter.com/davehrtng">
            Twitter
          </ExternalLink>
          , and{" "}
          <ExternalLink href="https://www.linkedin.com/in/davidharting">
            LinkedIn
          </ExternalLink>
          .
        </p>
      </div>

      <h2 className="text-2xl mt-8 font-serif font-semibold">Lil' Apps</h2>
      <div>
        <p>
          I've got a couple little projects on this site, because it's fun to
          tinker. This site is built with Remix and hosted on Cloudflare Pages.
          Server rendering happens in Cloudflare Workers, and I use their KV
          store.
        </p>
        <ul className="mt-4 px-4">
          <li>
            <InternalLink to="/1tl">One-Time Links</InternalLink>
          </li>
          <li>
            <InternalLink to="/picross">Picross Permutations</InternalLink>
          </li>
        </ul>
      </div>
    </div>
  );
}
