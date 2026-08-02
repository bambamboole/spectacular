import type { Contract } from "./types";

const SCHEMA_REF_PREFIX = "#/components/schemas/";

export function exampleFromSchema(schema: unknown, components?: unknown): unknown {
    return schemaExample(schema, components, new Set());
}

export function initialContractExample(contract: Contract, components?: unknown): unknown {
    if (contract.examples.length > 0) {
        return contract.examples[0].value;
    }

    return exampleFromSchema(contract.schema, components);
}

function schemaExample(schema: unknown, components: unknown, visitedRefs: Set<string>): unknown {
    if (!isRecord(schema)) {
        return null;
    }

    const ref = localSchemaRef(schema);
    if (ref !== null) {
        if (visitedRefs.has(ref)) {
            return null;
        }

        const referencedSchema = componentSchema(ref, components);
        if (referencedSchema === null) {
            return null;
        }

        visitedRefs.add(ref);
        const example = schemaExample(referencedSchema, components, visitedRefs);
        visitedRefs.delete(ref);

        return example;
    }

    const example = schema.example;
    if (example !== undefined) {
        return example;
    }

    const defaultValue = schema.default;
    if (defaultValue !== undefined) {
        return defaultValue;
    }

    if (Array.isArray(schema.enum) && schema.enum.length > 0) {
        return schema.enum[0];
    }

    if (schema.type === "object") {
        return objectExample(schema.properties, components, visitedRefs);
    }

    if (schema.type === "array") {
        return [schemaExample(schema.items, components, visitedRefs)];
    }

    if (schema.type === "string") {
        return "string";
    }

    if (schema.type === "integer" || schema.type === "number") {
        return 0;
    }

    if (schema.type === "boolean") {
        return false;
    }

    return null;
}

function localSchemaRef(schema: Record<string, unknown>): string | null {
    if (typeof schema.$ref !== "string" || !schema.$ref.startsWith(SCHEMA_REF_PREFIX)) {
        return null;
    }

    return schema.$ref;
}

function componentSchema(ref: string, components: unknown): unknown | null {
    if (!isRecord(components) || !isRecord(components.schemas)) {
        return null;
    }

    const name = ref.slice(SCHEMA_REF_PREFIX.length);
    if (name === "" || !(name in components.schemas)) {
        return null;
    }

    return components.schemas[name];
}

function objectExample(properties: unknown, components: unknown, visitedRefs: Set<string>): Record<string, unknown> {
    if (!isRecord(properties)) {
        return {};
    }

    return Object.fromEntries(
        Object.entries(properties).map(([name, propertySchema]) => [name, schemaExample(propertySchema, components, visitedRefs)]),
    );
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === "object" && value !== null && !Array.isArray(value);
}
