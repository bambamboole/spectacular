import { describe, expect, it } from "vitest";
import { resolveRequestBodySchema, validateRequestBodyValue } from "./request-body-schema";

describe("resolveRequestBodySchema", () => {
    it("resolves local references and merges allOf object fields", () => {
        expect(
            resolveRequestBodySchema(
                {
                    allOf: [
                        { $ref: "#/components/schemas/Named" },
                        {
                            type: "object",
                            required: ["items"],
                            properties: {
                                items: { type: "array", items: { type: "integer" } },
                            },
                        },
                    ],
                },
                {
                    schemas: {
                        Named: {
                            type: "object",
                            required: ["name"],
                            properties: {
                                name: { type: "string" },
                                id: { type: "string", readOnly: true },
                            },
                        },
                    },
                },
            ),
        ).toEqual({
            schema: {
                kind: "object",
                nullable: false,
                properties: [
                    {
                        name: "name",
                        required: true,
                        schema: { kind: "string", nullable: false },
                    },
                    {
                        name: "items",
                        required: true,
                        schema: {
                            kind: "array",
                            nullable: false,
                            items: { kind: "integer", nullable: false },
                        },
                    },
                ],
            },
            error: null,
        });
    });

    it.each([
        [{ oneOf: [{ type: "string" }, { type: "integer" }] }, "oneOf request body schemas are not supported."],
        [{ type: "object", additionalProperties: true }, "Open-ended object request body schemas are not supported."],
        [{ type: "array" }, "Array request body schemas must define items."],
    ])("reports unsupported schemas", (schema, error) => {
        expect(resolveRequestBodySchema(schema, null)).toEqual({ schema: null, error });
    });

    it("rejects recursive request body schemas", () => {
        expect(
            resolveRequestBodySchema(
                { $ref: "#/components/schemas/Node" },
                {
                    schemas: {
                        Node: {
                            type: "object",
                            properties: { child: { $ref: "#/components/schemas/Node" } },
                        },
                    },
                },
            ),
        ).toEqual({ schema: null, error: "Recursive request body schemas are not supported." });
    });

    it("validates required nested values and scalar types", () => {
        const result = resolveRequestBodySchema({
            type: "object",
            required: ["name", "address", "count"],
            properties: {
                name: { type: "string" },
                address: {
                    type: "object",
                    required: ["city"],
                    properties: { city: { type: "string" } },
                },
                count: { type: "integer" },
            },
        }, null);

        expect(result.schema).not.toBeNull();
        if (result.schema === null) {
            return;
        }

        expect(validateRequestBodyValue(result.schema, { name: "Desk", address: {}, count: 1 })).toEqual({
            path: "address.city",
            message: "This field is required.",
        });
        expect(validateRequestBodyValue(result.schema, { name: "Desk", address: { city: "Berlin" }, count: 1.5 })).toEqual({
            path: "count",
            message: "Enter an integer.",
        });
    });

    it("allows an explicitly supplied empty string for an optional scalar body", () => {
        const result = resolveRequestBodySchema({ type: "string" }, null);

        expect(result.schema).not.toBeNull();
        if (result.schema === null) {
            return;
        }

        expect(validateRequestBodyValue(result.schema, "", false)).toBeNull();
    });
});
