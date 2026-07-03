import { createPlugin, eagerComponent } from "@lattice-php/lattice";
import SchemaTreeComponent from "./components/schema-tree";

export const spectacularComponents = createPlugin({
    name: "spectacular",
    components: {
        "spectacular.schema-tree": eagerComponent(SchemaTreeComponent),
    },
});
