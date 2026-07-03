import { describe, expect, it } from "vitest";
import { buildSchemaRows } from "./build-rows";

const components = {
    schemas: {
        Node: {
            type: "object",
            required: ["id"],
            properties: {
                id: { type: "integer" },
                label: { type: ["string", "null"] },
                parent: { $ref: "#/components/schemas/Node" },
            },
        },
    },
};
const nodeSchema = { $ref: "#/components/schemas/Node" };

describe("buildSchemaRows", () => {
    it("maps object properties to rows", async () => {
        const rows = await buildSchemaRows(nodeSchema, components);
        const byName = Object.fromEntries(rows.map((r) => [r.name, r]));
        expect(byName.id.typeLabel).toBe("integer");
        expect(byName.id.required).toBe(true);
        expect(byName.label.typeLabel).toContain("string");
        expect(byName.label.required).toBe(false);
    });

    it("terminates on a self-referential schema", async () => {
        const rows = await buildSchemaRows(nodeSchema, components);
        const parent = rows.find((r) => r.name === "parent");
        expect(parent).toBeDefined();
        expect(parent!.isRecursive).toBe(true);
        expect(parent!.children).toEqual([]);
    });

    it("renders an inline object schema without a top-level $ref", async () => {
        const inline = {
            type: "object",
            required: ["data"],
            properties: { data: { $ref: "#/components/schemas/Node" } },
        };
        const rows = await buildSchemaRows(inline, components);
        expect(rows.find((r) => r.name === "data")).toBeDefined();
    });
});
