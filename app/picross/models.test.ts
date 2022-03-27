import { Piece, Row } from "./models";

describe("Row", () => {
  describe("addPiece", () => {
    it("should not update a row that is already full", () => {
      const row = new Row(["Y", "Y", "n", "n", "n"]);
      const didAdd = row.addPiece({ size: 1, state: "Y" });
      expect(didAdd).toBe(false);
      expect(row.getCells()).toEqual(["Y", "Y", "n", "n", "n"]);
    });

    it("should add the piece to the row if it fits", () => {
      const row = new Row(5);

      const didAdd = row.addPiece({ size: 3, state: "n" });
      expect(didAdd).toBe(true);
      expect(row.getCells()).toEqual(["n", "n", "n"]);

      const didAddAgain = row.addPiece({ size: 2, state: "Y" });
      expect(didAddAgain).toBe(true);
      expect(row.getCells()).toEqual(["n", "n", "n", "Y", "Y"]);
    });
  });
});
