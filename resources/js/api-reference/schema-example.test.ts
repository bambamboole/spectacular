import { describe, expect, it } from "vitest";
import { exampleFromSchema, initialContractExample } from "./schema-example";

describe("exampleFromSchema", () => {
    it("prefers examples, defaults, and enums before type placeholders", () => {
        expect(exampleFromSchema({ type: "string", example: "shown" })).toBe("shown");
        expect(exampleFromSchema({ type: "integer", default: 10 })).toBe(10);
        expect(exampleFromSchema({ type: "string", enum: ["first", "second"] })).toBe("first");
    });

    it("builds nested object and array samples", () => {
        expect(
            exampleFromSchema({
                type: "object",
                properties: {
                    name: { type: "string" },
                    enabled: { type: "boolean" },
                    tags: { type: "array", items: { type: "string" } },
                },
            }),
        ).toEqual({ name: "string", enabled: false, tags: ["string"] });
    });

    it("resolves local component schema references without recursing forever", () => {
        const components = {
            schemas: {
                User: {
                    type: "object",
                    properties: {
                        name: { type: "string" },
                        manager: { $ref: "#/components/schemas/User" },
                    },
                },
            },
        };

        expect(exampleFromSchema({ $ref: "#/components/schemas/User" }, components)).toEqual({
            name: "string",
            manager: null,
        });
    });
});

describe("initialContractExample", () => {
    it("prefers the first explicit contract example", () => {
        const contract = {
            role: "request" as const,
            status: null,
            mediaType: "application/json",
            schema: { type: "string", example: "schema" },
            title: null,
            examples: [{ name: "named", summary: null, value: { explicit: true } }],
            headers: [],
            required: false,
        };

        expect(initialContractExample(contract)).toEqual({ explicit: true });
    });
});
