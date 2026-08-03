import { describe, expect, it } from "vitest";
import { render } from "vitest-browser-react";
import { OperationView } from "./OperationView";

describe("OperationView", () => {
    it("shows a generated example by default for schema-only responses", async () => {
        const screen = await render(
            <OperationView
                operationId="get-products-id"
                spec={{
                    openapi: "3.1.0",
                    info: { title: "Products", version: "1.0.0" },
                    components: {
                        schemas: {
                            Product: {
                                allOf: [
                                    {
                                        type: "object",
                                        properties: {
                                            id: { type: "string", example: "product-1" },
                                        },
                                    },
                                    {
                                        type: "object",
                                        properties: {
                                            name: { type: "string", example: "Desk" },
                                        },
                                    },
                                ],
                            },
                        },
                    },
                    paths: {
                        "/products/{id}": {
                            get: {
                                responses: {
                                    "200": {
                                        description: "OK",
                                        content: {
                                            "application/json": {
                                                schema: {
                                                    type: "object",
                                                    properties: {
                                                        data: { $ref: "#/components/schemas/Product" },
                                                    },
                                                },
                                            },
                                        },
                                    },
                                },
                            },
                        },
                    },
                }}
            />,
        );

        await expect.element(screen.getByRole("radio", { name: "Example" })).toBeChecked();
        await expect.element(screen.getByText("Generated from schema")).toBeVisible();
        await expect.element(screen.getByRole("region", { name: "Response example" })).toBeVisible();
        await expect.poll(() => document.querySelector(".cm-content")?.textContent).toContain('"id": "product-1"');
        await expect.poll(() => document.querySelector(".cm-content")?.textContent).toContain('"name": "Desk"');
    });

    it("shows example descriptions and external values", async () => {
        const screen = await render(
            <OperationView
                operationId="get-widgets"
                spec={{
                    openapi: "3.1.0",
                    info: { title: "Widgets", version: "1.0.0" },
                    paths: {
                        "/widgets": {
                            get: {
                                responses: {
                                    "200": {
                                        description: "OK",
                                        content: {
                                            "application/json": {
                                                examples: {
                                                    inline: {
                                                        description: "A complete inline example.",
                                                        value: { id: 1 },
                                                    },
                                                    external: {
                                                        summary: "Large payload",
                                                        externalValue: "https://example.test/widgets.json",
                                                    },
                                                },
                                            },
                                        },
                                    },
                                },
                            },
                        },
                    },
                }}
            />,
        );

        await screen.getByRole("radio", { name: "Example" }).click();
        await expect.element(screen.getByText("A complete inline example.")).toBeVisible();
        await expect.element(screen.getByRole("region", { name: "Response example" })).toBeVisible();
        await expect.poll(() => document.querySelector(".cm-content")?.getAttribute("contenteditable")).toBe("false");
        await expect.poll(() => document.querySelector(".cm-lineNumbers")).not.toBeNull();

        await screen.getByRole("combobox").selectOptions("1");
        await expect.element(screen.getByRole("link", { name: "Open external example" })).toHaveAttribute(
            "href",
            "https://example.test/widgets.json",
        );
    });
});
