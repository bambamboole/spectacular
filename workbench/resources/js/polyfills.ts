import { Buffer } from "buffer";

// @apidevtools/json-schema-ref-parser reaches for the Node `Buffer` global,
// which the browser bundle does not provide. Set it before any module that
// pulls in ref-parser evaluates.
globalThis.Buffer = globalThis.Buffer ?? Buffer;
