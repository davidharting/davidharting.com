// I took this setup from the Remix "blues stack"
// https://github.com/remix-run/blues-stack/blob/56e1732c11e50559cef02f620ad4fdb16b6f99e7/test/setup-test-env.ts

// Installing globals required to polyfill browser APIs into the Node.js test runner environment
// https://remix.run/docs/en/v1/other-api/node#polyfills
import { installGlobals } from "@remix-run/node/globals";
import "@testing-library/jest-dom/extend-expect";

installGlobals();
