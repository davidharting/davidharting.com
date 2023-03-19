import type { FunctionComponent } from 'preact'

export const HomePage: FunctionComponent = () => {
  return (
    <div className="antialiased m-auto max-w-2xl my-16 px-2">
      <div className="py-2">
        <h1 className="text-5xl font-extrabold font-serif">Hi! I am David Harting,</h1>
        <p className="text-lg">and it's a great day to build software ☀️</p>
      </div>

      <div className="prose mt-10">
        <h2 className="font-serif">About me</h2>
        <p>
          I am an experienced, full-stack software engineer from Westfield, Indiana. My focus in my
          career has been web apps that enable people to work with data. I am now working as an
          engineering manager at <a href="https://www.getdbt.com">dbt Labs</a>, building a web-based
          IDE for analytics engineers.
        </p>
        <p>
          At work, I am happiest working closely with product and design to navigate tradeoffs and
          to ship quickly. I am passionate about code review and testing.
        </p>
        <p>
          I believe in working hard and living slow. I enjoy life with my wife and my dog. I am
          fortunate enough to enjoy leisure time, which is filled with walks, wine, books, and
          games.
        </p>

        <h2 className="font-serif">Let's connect</h2>
        <div>
          <p>
            ✍️ I <a href="https://world.hey.com/david.harting">write on Hey World</a>. Or, you can
            find me on <a href="https://github.com/davidharting">GitHub</a>,{' '}
            <a href="https://www.twitter.com/davehrtng">Twitter</a>, and{' '}
            <a href="https://www.linkedin.com/in/davidharting">LinkedIn</a>.
          </p>
        </div>
      </div>
    </div>
  )
}
