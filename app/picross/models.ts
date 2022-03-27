type FilledState = "Y";
type CrossedOutState = "n";
export type CellState = FilledState | CrossedOutState;

export type Piece = {
  size: number;
  state: CellState;
};

export class Row {
  readonly cells: CellState[];
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

  getSize(): number {
    return this.size;
  }

  private isFull(): boolean {
    return this.filledTo >= this.size;
  }

  /**
   * Return the "slot numbers" for all the filled cells.
   * Slot numbers start at 0 (0-indexed).
   * So for final presentation to a user, this likely needs adjusted.
   */
  filledCells(): Set<number> {
    const set = new Set<number>();
    this.cells.forEach((state, index) => {
      if (state === "Y") {
        set.add(index);
      }
    });
    return set;
  }
}
