import { SchemaTree, isMirroredNode, isReferenceNode, isRegularNode } from "@stoplight/json-schema-tree";
import type { MirroredRegularNode, RegularNode, SchemaNode } from "@stoplight/json-schema-tree";

export type SchemaRow = {
    id: string;
    name: string | null;
    typeLabel: string;
    required: boolean;
    description: string | null;
    details: string[];
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
        details: withData === null ? [] : schemaDetails(withData),
        children: recursive ? [] : childRows(node),
        isRecursive: recursive,
    };
}

function schemaDetails(node: RegularLikeNode): string[] {
    const details: string[] = [];
    const fragment = node.originalFragment;

    if (node.format !== null) {
        details.push(`format: ${node.format}`);
    }

    if ("const" in fragment) {
        details.push(`const: ${schemaValue(fragment.const)}`);
    } else if (node.enum !== null) {
        details.push(`enum: ${schemaValue(node.enum)}`);
    }

    if (node.annotations.default !== undefined) {
        details.push(`default: ${schemaValue(node.annotations.default)}`);
    }
    if (node.annotations.examples !== undefined) {
        details.push(`examples: ${schemaValue(node.annotations.examples)}`);
    }

    for (const [name, value] of Object.entries(node.validations)) {
        if (!["readOnly", "writeOnly", "style"].includes(name)) {
            details.push(`${name}: ${schemaValue(value)}`);
        }
    }

    if (node.deprecated) {
        details.push("deprecated");
    }
    if (node.validations.readOnly === true) {
        details.push("readOnly");
    }
    if (node.validations.writeOnly === true) {
        details.push("writeOnly");
    }

    return details;
}

function schemaValue(value: unknown): string {
    return JSON.stringify(value) ?? String(value);
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
