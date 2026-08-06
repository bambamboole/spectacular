import { lazyComponent, type Plugin } from "@lattice-php/core/registry";

export default {
    name: "spectacular",
    components: {
        "spectacular.api-reference": lazyComponent(
            () => import("./api-reference/ApiReference"),
        ),
    },
} satisfies Plugin;
