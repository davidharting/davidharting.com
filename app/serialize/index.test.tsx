import { base64ToObject, objectToBase64 } from "~/serialize";

const exampleObjects = [
  { first: "Tom", last: "Bombadil" },
  { age: 27 },
  { a: 1, b: 2, c: 3, d: { e: 4, f: 5 } },
];
describe("serialize", () => {
  describe("#objectToBase64", () => {
    test.each(exampleObjects)(
      "The same object should serialize to the same thing each time it is serialized (%o)",
      (obj) => {
        const firstResult = objectToBase64(obj);
        const secondResult = objectToBase64(obj);
        const serializedCopy = objectToBase64({ ...obj });
        expect(firstResult).toEqual(secondResult);
        expect(secondResult).toEqual(serializedCopy);
      }
    );

    test.each(exampleObjects)(
      "serialized objects should not contain = or + (%o)",
      (obj) => {
        const serialized = objectToBase64(obj);
        expect(serialized).not.toContain("+");
        expect(serialized).not.toContain("=");
      }
    );

    test.each(exampleObjects)(
      "serialized objects should deserialize back into their original form (%o)",
      (obj) => {
        const serialized = objectToBase64(obj);
        const deserialized = base64ToObject(serialized);
        expect(deserialized).not.toBe(obj);
        expect(deserialized).toEqual(obj);
      }
    );
  });
});
