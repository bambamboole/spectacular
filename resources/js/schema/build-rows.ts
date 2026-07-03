import $RefParser from "@apidevtools/json-schema-ref-parser";
import { SchemaTree, isMirroredNode, isReferenceNode, isRegularNode } from "@stoplight/json-schema-tree";
import type { MirroredRegularNode, RegularNode, SchemaNode } from "@stoplight/json-schema-tree";

export type SchemaRow = {
    id: string;
    name: string | null;
    typeLabel: string;
    required: boolean;
    description: string | null;
    enumValues: (string | number)[] | null;
    children: SchemaRow[];
    isRecursive: boolean;
};

type RegularLikeNode = RegularNode | MirroredRegularNode;

function isRegularLike(node: SchemaNode): node is RegularLikeNode {
    return isRegularNode(node) || (isMirroredNode(node) && "types" in node);
}

function resolvePointer(doc: unknown, pointer: string): unknown {
    const path = pointer.replace(/^#\//, "").split("/");

    return path.reduce<unknown>((acc, key) => {
        if (acc && typeof acc === "object") {
            return (acc as Record<string, unknown>)[decodeURIComponent(key)];
        }
        return undefined;
    }, doc);
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
        enumValues: (withData?.enum as (string | number)[] | undefined) ?? null,
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

export async function buildSchemaRows(rootDocument: unknown, pointer: string): Promise<SchemaRow[]> {
    const dereferenced = await $RefParser.dereference(structuredClone(rootDocument) as object, {
        dereference: { circular: true },
    });
    const target = resolvePointer(dereferenced, pointer);

    const tree = new SchemaTree(target as object, { mergeAllOf: true, refResolver: null });
    tree.populate();

    const [schemaNode] = tree.root.children as unknown as SchemaNode[];
    return schemaNode ? childRows(schemaNode) : [];
}
