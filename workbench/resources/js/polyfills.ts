import { Buffer } from "buffer";

const globalWithBuffer = globalThis as typeof globalThis & { Buffer?: typeof Buffer };

// @apidevtools/json-schema-ref-parser reaches for the Node `Buffer` global,
// which the browser bundle does not provide. Set it before any module that
// pulls in ref-parser evaluates.
globalWithBuffer.Buffer = globalWithBuffer.Buffer ?? Buffer;
