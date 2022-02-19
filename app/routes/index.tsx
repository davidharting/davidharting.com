import Link from "~/element/link/external";

export default function Index() {
  return (
    <div className="antialiased m-auto max-w-2xl mt-16 font-sans px-2">
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
          <Link href="https://world.hey.com/david.harting">
            write on Hey World
          </Link>
          . Or, you can find me on{" "}
          <Link href="https://github.com/davidharting">GitHub</Link>,{" "}
          <Link href="https://www.twitter.com/davehrtng">Twitter</Link>, and{" "}
          <Link href="https://www.linkedin.com/in/davidharting">LinkedIn</Link>.
        </p>
      </div>
    </div>
  );
}
