import type { FC } from "react";
import Heading from "~/element/typography/heading";
import { TextInput } from "~/form/TextInput";

const PicrossPage: FC = () => {
  return (
    <div className="m-auto max-w-2xl mt-12 font-sans px-2 md:px-0">
      <div className="space-y-4">
        <Heading as="h1" className="text-5xl">
          Picross Permutations
        </Heading>
        <p>
          This is a tool to help with nonogram puzzles. It helps determine all
          the possible permutations for a hint.
        </p>
      </div>
      <div className="mt-8">
        <form>
          {/* <input name="size" type="number" /> */}
          <TextInput label="Pattern" name="pattern" placeholder="1 3 1" />
        </form>
      </div>
    </div>
  );
};

export default PicrossPage;
