import PicrossPage from "./index";
import { render } from "@testing-library/react";
import "@testing-library/jest-dom";

it("should render a text input", async () => {
  const html = render(<PicrossPage />);
  html.getByText("Picross Permutations");
});
