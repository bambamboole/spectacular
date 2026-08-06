import { lazyComponent, type Plugin } from "@lattice-php/lattice";

const plugin: Plugin = {
    name: "spectacular",
    components: {
        "spectacular.api-reference": lazyComponent(
            () => import("./api-reference/ApiReference"),
        ),
    },
};

export default plugin;
