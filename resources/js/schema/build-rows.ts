import { SchemaTree, isMirroredNode, isReferenceNode, isRegularNode } from "@stoplight/json-schema-tree";
import type { MirroredRegularNode, RegularNode, SchemaNode } from "@stoplight/json-schema-tree";

export type SchemaRow = {
    id: string;
    name: string | null;
    typeLabel: string;
    required: boolean;
    description: string | null;
    children: SchemaRow[];
    isRecursive: boolean;
};

type RegularLikeNode = RegularNode | MirroredRegularNode;

function isRegularLike(node: SchemaNode): node is RegularLikeNode {
    return isRegularNode(node) || (isMirroredNode(node) && "types" in node);
}

function typeLabel(node: SchemaNode): string {
    if (isReferenceNode(node)) {
        return node.value ?? "ref";
    }
    if (isRegularLike(node)) {
        const types = node.types ?? [];
        return types.length > 0 ? types.join(" | ") : (node.primaryType ?? "any");
    }
    return "any";
}

function toRow(node: SchemaNode, name: string | null, required: Set<string>): SchemaRow {
    const recursive = isMirroredNode(node);
    const withData = isRegularLike(node) ? node : null;

    return {
        id: node.id,
        name,
        typeLabel: typeLabel(node),
        required: name !== null && required.has(name),
        description: (withData?.annotations?.description as string | undefined) ?? null,
        children: recursive ? [] : childRows(node),
        isRecursive: recursive,
    };
}

function childRows(node: SchemaNode): SchemaRow[] {
    const children = (("children" in node ? node.children : null) ?? []) as SchemaNode[];
    const required = new Set<string>(isRegularLike(node) ? (node.required ?? []) : []);

    return children.map((child) => {
        const name = child.subpath.slice(-1)[0] ?? null;
        return toRow(child, name, required);
    });
}

export async function buildSchemaRows(schema: unknown, components: unknown): Promise<SchemaRow[]> {
    const tree = new SchemaTree({
        type: "object",
        properties: { __schema: schema },
        components,
    }, { mergeAllOf: true });
    tree.populate();

    const [wrapperNode] = tree.root.children as unknown as SchemaNode[];
    const [schemaRow] = wrapperNode ? childRows(wrapperNode) : [];

    return schemaRow?.children ?? [];
}
