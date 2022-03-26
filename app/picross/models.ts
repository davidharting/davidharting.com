type FilledState = "Y";
type CrossedOutState = "n";
type CellState = FilledState | CrossedOutState;

export type Piece = {
  size: number;
  state: CellState;
};

export class Row {
  private readonly cells: CellState[];
  private filledTo: number;
  private readonly size: number;

  constructor(size: number);
  constructor(cells: CellState[]);
  constructor(sizeOrCells: number | CellState[]) {
    if (typeof sizeOrCells === "number") {
      this.cells = new Array<CellState>();
      this.filledTo = 0;
      this.size = sizeOrCells;
    } else {
      this.cells = sizeOrCells;
      this.filledTo = this.cells.length;
      this.size = this.cells.length;
    }
  }

  getCells(): CellState[] {
    return [...this.cells];
  }

  addPiece(piece: Piece): boolean {
    if (this.isFull()) {
      return false;
    }
    for (let i = 0; i < piece.size; i++) {
      this.cells.push(piece.state);
      this.filledTo++;
    }
    return true;
  }

  /**
   * Return a string representation of the row.
   * Any row that has an identical string represents the same sequence of filled and crossed out cells.
   */
  toUniqueString(): string {
    return this.cells.join("");
  }

  isEqual(other: Row): boolean {
    return this.toUniqueString() === other.toUniqueString();
  }

  private isFull(): boolean {
    return this.filledTo >= this.size;
  }
}
