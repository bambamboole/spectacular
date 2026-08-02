import { Buffer } from "buffer";
import { createPlugin, lazyComponent } from "@lattice-php/lattice";

const globalWithBuffer = globalThis as typeof globalThis & { Buffer?: typeof Buffer };

// @apidevtools/json-schema-ref-parser reaches for the Node `Buffer` global, which
// the browser bundle does not provide. Set it before the lazily-loaded viewer (and
// everything it imports) ever evaluates.
globalWithBuffer.Buffer = globalWithBuffer.Buffer ?? Buffer;

export default createPlugin({
    name: "spectacular",
    components: {
        "spectacular.api-reference": lazyComponent(
            () => import("./api-reference/ApiReference"),
        ),
    },
});
