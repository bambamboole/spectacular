import { createPlugin, eagerComponent } from "@lattice-php/lattice";
import ApiReference from "./api-reference/ApiReference";

export const spectacularComponents = createPlugin({
    name: "spectacular",
    components: {
        "spectacular.api-reference": eagerComponent(ApiReference),
    },
});
