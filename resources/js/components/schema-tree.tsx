import type { RendererComponent } from "@lattice-php/lattice";
import { SchemaView } from "../schema/SchemaView";

const SchemaTreeComponent: RendererComponent<"spectacular.schema-tree"> = ({ node }) => {
    const { schema, components } = node.props as { schema: unknown; components: unknown };

    return <SchemaView schema={schema} components={components} />;
};

export default SchemaTreeComponent;
