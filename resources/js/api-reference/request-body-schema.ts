const SCHEMA_REF_PREFIX = "#/components/schemas/";

export type RequestBodyScalar = string | number | boolean | null;

type RequestBodySchemaBase = {
    nullable: boolean;
    description?: string;
};

export type RequestBodySchema =
    | (RequestBodySchemaBase & {
          kind: "string";
          format?: string;
          enum?: RequestBodyScalar[];
          minLength?: number;
          maxLength?: number;
          pattern?: string;
      })
    | (RequestBodySchemaBase & {
          kind: "number" | "integer";
          enum?: RequestBodyScalar[];
          minimum?: number;
          maximum?: number;
          multipleOf?: number;
      })
    | (RequestBodySchemaBase & {
          kind: "boolean";
          enum?: RequestBodyScalar[];
      })
    | (RequestBodySchemaBase & {
          kind: "object";
          properties: RequestBodyProperty[];
      })
    | (RequestBodySchemaBase & {
          kind: "array";
          items: RequestBodySchema;
      });

export type RequestBodyProperty = {
    name: string;
    required: boolean;
    schema: RequestBodySchema;
};

export type RequestBodySchemaResult =
    | { schema: RequestBodySchema; error: null }
    | { schema: null; error: string };

export type RequestBodyValidationError = {
    path: string;
    message: string;
};

export function resolveRequestBodySchema(schema: unknown, components: unknown): RequestBodySchemaResult {
    try {
        return { schema: normalizeSchema(schema, components, new Set(), 0), error: null };
    } catch (error: unknown) {
        return {
            schema: null,
            error: error instanceof RequestBodySchemaError ? error.message : "This request body schema is not supported.",
        };
    }
}

export function validateRequestBodyValue(
    schema: RequestBodySchema,
    value: unknown,
    path = "body",
): RequestBodyValidationError | null {
    return validateValue(schema, value, true, path);
}

export function defaultRequestBodyValue(schema: RequestBodySchema): unknown {
    if (schema.nullable) {
        return null;
    }

    if (schema.kind === "object") {
        return Object.fromEntries(
            schema.properties
                .filter((property) => property.required)
                .map((property) => [property.name, defaultRequestBodyValue(property.schema)]),
        );
    }

    if (schema.kind === "array") {
        return [];
    }

    if (schema.enum !== undefined && schema.enum.length > 0) {
        return schema.enum[0];
    }

    if (schema.kind === "boolean") {
        return false;
    }

    if (schema.kind === "number" || schema.kind === "integer") {
        return 0;
    }

    return "";
}

function normalizeSchema(
    schema: unknown,
    components: unknown,
    visitedRefs: Set<string>,
    depth: number,
): RequestBodySchema {
    if (depth > 20) {
        throw new RequestBodySchemaError("Recursive request body schemas are not supported.");
    }

    const resolved = resolveSchema(schema, components, visitedRefs);

    if ("oneOf" in resolved) {
        throw new RequestBodySchemaError("oneOf request body schemas are not supported.");
    }

    if ("anyOf" in resolved) {
        throw new RequestBodySchemaError("anyOf request body schemas are not supported.");
    }

    const types = schemaTypes(resolved.type);
    const nullable = types.includes("null") || resolved.nullable === true;
    const nonNullTypes = types.filter((type) => type !== "null");

    if (nonNullTypes.length > 1) {
        throw new RequestBodySchemaError("Request body fields with multiple non-null types are not supported.");
    }

    const type = nonNullTypes[0] ?? inferSchemaType(resolved);
    const base = {
        nullable,
        ...(typeof resolved.description === "string" ? { description: resolved.description } : {}),
    };

    if (type === "object") {
        if (resolved.additionalProperties === true || isRecord(resolved.additionalProperties)) {
            throw new RequestBodySchemaError("Open-ended object request body schemas are not supported.");
        }

        const properties = isRecord(resolved.properties) ? resolved.properties : {};
        const required = new Set(
            Array.isArray(resolved.required)
                ? resolved.required.filter((name): name is string => typeof name === "string")
                : [],
        );

        return {
            kind: "object",
            ...base,
            properties: Object.entries(properties).flatMap(([name, propertySchema]) => {
                if (isRecord(propertySchema) && propertySchema.readOnly === true) {
                    return [];
                }

                return [{
                    name,
                    required: required.has(name),
                    schema: normalizeSchema(propertySchema, components, new Set(visitedRefs), depth + 1),
                }];
            }),
        };
    }

    if (type === "array") {
        if (resolved.items === undefined) {
            throw new RequestBodySchemaError("Array request body schemas must define items.");
        }

        return {
            kind: "array",
            ...base,
            items: normalizeSchema(resolved.items, components, new Set(visitedRefs), depth + 1),
        };
    }

    if (type === "string") {
        return {
            kind: "string",
            ...base,
            ...stringValue(resolved.format, "format"),
            ...scalarEnum(resolved.enum),
            ...numberValue(resolved.minLength, "minLength"),
            ...numberValue(resolved.maxLength, "maxLength"),
            ...stringValue(resolved.pattern, "pattern"),
        };
    }

    if (type === "number" || type === "integer") {
        return {
            kind: type,
            ...base,
            ...scalarEnum(resolved.enum),
            ...numberValue(resolved.minimum, "minimum"),
            ...numberValue(resolved.maximum, "maximum"),
            ...numberValue(resolved.multipleOf, "multipleOf"),
        };
    }

    if (type === "boolean") {
        return { kind: "boolean", ...base, ...scalarEnum(resolved.enum) };
    }

    throw new RequestBodySchemaError("Only object, array, string, number, integer, and boolean request bodies are supported.");
}

function validateValue(
    schema: RequestBodySchema,
    value: unknown,
    required: boolean,
    path: string,
): RequestBodyValidationError | null {
    if (value === undefined || (required && value === "")) {
        return required ? { path, message: "This field is required." } : null;
    }

    if (value === null) {
        return schema.nullable ? null : { path, message: "This field cannot be null." };
    }

    if (schema.kind === "object") {
        if (!isRecord(value)) {
            return { path, message: "Enter an object." };
        }

        for (const property of schema.properties) {
            if (!Object.hasOwn(value, property.name)) {
                if (property.required) {
                    return { path: childPath(path, property.name), message: "This field is required." };
                }

                continue;
            }

            const error = validateValue(
                property.schema,
                value[property.name],
                property.required,
                childPath(path, property.name),
            );
            if (error !== null) {
                return error;
            }
        }

        return null;
    }

    if (schema.kind === "array") {
        if (!Array.isArray(value)) {
            return { path, message: "Enter an array." };
        }

        for (const [index, item] of value.entries()) {
            const error = validateValue(schema.items, item, true, `${path}[${index}]`);
            if (error !== null) {
                return error;
            }
        }

        return null;
    }

    if (schema.enum !== undefined && !schema.enum.includes(value as RequestBodyScalar)) {
        return { path, message: "Select an allowed value." };
    }

    if (schema.kind === "boolean") {
        return typeof value === "boolean" ? null : { path, message: "Select true or false." };
    }

    if (schema.kind === "number" || schema.kind === "integer") {
        if (typeof value !== "number" || !Number.isFinite(value)) {
            return { path, message: "Enter a number." };
        }
        if (schema.kind === "integer" && !Number.isInteger(value)) {
            return { path, message: "Enter an integer." };
        }
        if (schema.minimum !== undefined && value < schema.minimum) {
            return { path, message: `Enter a value greater than or equal to ${schema.minimum}.` };
        }
        if (schema.maximum !== undefined && value > schema.maximum) {
            return { path, message: `Enter a value less than or equal to ${schema.maximum}.` };
        }
        if (schema.multipleOf !== undefined && schema.multipleOf > 0) {
            const quotient = value / schema.multipleOf;
            if (Math.abs(quotient - Math.round(quotient)) > 1e-9) {
                return { path, message: `Enter a multiple of ${schema.multipleOf}.` };
            }
        }

        return null;
    }

    if (schema.kind !== "string" || typeof value !== "string") {
        return { path, message: "Enter text." };
    }
    if (schema.minLength !== undefined && [...value].length < schema.minLength) {
        return { path, message: `Enter at least ${schema.minLength} characters.` };
    }
    if (schema.maxLength !== undefined && [...value].length > schema.maxLength) {
        return { path, message: `Enter no more than ${schema.maxLength} characters.` };
    }
    if (schema.pattern !== undefined) {
        try {
            if (!new RegExp(schema.pattern).test(value)) {
                return { path, message: "Match the required pattern." };
            }
        } catch {
            return null;
        }
    }

    return null;
}

function childPath(parent: string, child: string): string {
    return parent === "body" ? child : `${parent}.${child}`;
}

function resolveSchema(
    schema: unknown,
    components: unknown,
    visitedRefs: Set<string>,
): Record<string, unknown> {
    if (!isRecord(schema)) {
        throw new RequestBodySchemaError("The request body schema is missing or invalid.");
    }

    let resolved = schema;
    if (typeof schema.$ref === "string") {
        const referenced = componentSchema(schema.$ref, components);
        if (referenced === null) {
            throw new RequestBodySchemaError("The request body schema reference could not be resolved.");
        }
        if (visitedRefs.has(schema.$ref)) {
            throw new RequestBodySchemaError("Recursive request body schemas are not supported.");
        }

        visitedRefs.add(schema.$ref);
        resolved = mergeSchemas(resolveSchema(referenced, components, visitedRefs), withoutKey(schema, "$ref"));
        visitedRefs.delete(schema.$ref);
    }

    if (!Array.isArray(resolved.allOf)) {
        return resolved;
    }

    return resolved.allOf.reduce<Record<string, unknown>>(
        (combined, part) => mergeSchemas(combined, resolveSchema(part, components, new Set(visitedRefs))),
        withoutKey(resolved, "allOf"),
    );
}

function mergeSchemas(left: Record<string, unknown>, right: Record<string, unknown>): Record<string, unknown> {
    const properties = {
        ...(isRecord(left.properties) ? left.properties : {}),
        ...(isRecord(right.properties) ? right.properties : {}),
    };
    const required = [...new Set([...stringArray(left.required), ...stringArray(right.required)])];

    return {
        ...left,
        ...right,
        ...(Object.keys(properties).length > 0 ? { properties } : {}),
        ...(required.length > 0 ? { required } : {}),
    };
}

function componentSchema(ref: string, components: unknown): unknown | null {
    if (!ref.startsWith(SCHEMA_REF_PREFIX) || !isRecord(components) || !isRecord(components.schemas)) {
        return null;
    }

    const name = ref.slice(SCHEMA_REF_PREFIX.length);

    return name in components.schemas ? components.schemas[name] : null;
}

function schemaTypes(type: unknown): string[] {
    if (typeof type === "string") {
        return [type];
    }

    return Array.isArray(type) ? type.filter((value): value is string => typeof value === "string") : [];
}

function inferSchemaType(schema: Record<string, unknown>): string | null {
    if (isRecord(schema.properties) || schema.additionalProperties !== undefined) {
        return "object";
    }

    if (schema.items !== undefined) {
        return "array";
    }

    if (Array.isArray(schema.enum)) {
        const value = schema.enum.find((option) => option !== null);

        return value === undefined ? null : typeof value;
    }

    return null;
}

function scalarEnum(value: unknown): { enum?: RequestBodyScalar[] } {
    if (!Array.isArray(value) || !value.every(isScalar)) {
        return {};
    }

    return { enum: value };
}

function numberValue<Key extends string>(value: unknown, key: Key): Partial<Record<Key, number>> {
    return typeof value === "number" && Number.isFinite(value) ? ({ [key]: value } as Record<Key, number>) : {};
}

function stringValue<Key extends string>(value: unknown, key: Key): Partial<Record<Key, string>> {
    return typeof value === "string" ? ({ [key]: value } as Record<Key, string>) : {};
}

function stringArray(value: unknown): string[] {
    return Array.isArray(value) ? value.filter((item): item is string => typeof item === "string") : [];
}

function withoutKey(record: Record<string, unknown>, key: string): Record<string, unknown> {
    return Object.fromEntries(Object.entries(record).filter(([name]) => name !== key));
}

function isScalar(value: unknown): value is RequestBodyScalar {
    return value === null || ["string", "number", "boolean"].includes(typeof value);
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === "object" && value !== null && !Array.isArray(value);
}

class RequestBodySchemaError extends Error {}
