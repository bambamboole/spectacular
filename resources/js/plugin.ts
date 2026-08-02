import { createPlugin, lazyComponent } from "@lattice-php/lattice";

export default createPlugin({
    name: "spectacular",
    components: {
        "spectacular.api-reference": lazyComponent(
            () => import("./api-reference/ApiReference"),
        ),
    },
});
