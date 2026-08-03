import { describe, expect, it } from "vitest";
import { render } from "vitest-browser-react";
import { OperationView } from "./OperationView";

describe("OperationView", () => {
    it("shows path and query controls inline before the right request panel is enabled", async () => {
        const screen = await render(
            <OperationView
                operationId="get-products-id"
                spec={{
                    openapi: "3.1.0",
                    info: { title: "Products", version: "1.0.0" },
                    servers: [{ url: "https://api.example.test" }],
                    paths: {
                        "/products/{id}": {
                            get: {
                                parameters: [
                                    {
                                        name: "id",
                                        in: "path",
                                        required: true,
                                        example: "product-1",
                                        schema: { type: "string" },
                                    },
                                    {
                                        name: "include",
                                        in: "query",
                                        style: "form",
                                        explode: false,
                                        schema: {
                                            type: "array",
                                            items: { type: "string", enum: ["variants", "prices"] },
                                        },
                                    },
                                ],
                                responses: { "200": { description: "OK" } },
                            },
                        },
                    },
                }}
            />,
        );

        const requestPanel = screen.getByRole("complementary", { name: "Request" });
        const id = screen.getByLabelText("id");

        await expect.element(id).toBeVisible();
        await expect.element(id).toHaveValue("product-1");
        await expect.element(screen.getByRole("button", { name: "include" })).toBeVisible();
        await expect.element(requestPanel.getByRole("button", { name: "Try it out" })).toBeVisible();
        await expect.element(screen.getByLabelText("Request snippet", { exact: true })).not.toBeInTheDocument();

        await id.fill("product/2");
        await requestPanel.getByRole("button", { name: "Try it out" }).click();
        await expect
            .element(screen.getByLabelText("Request snippet", { exact: true }))
            .toHaveTextContent("https://api.example.test/products/product%2F2");

        await id.fill("");
        await requestPanel.getByRole("button", { name: "Execute" }).click();
        await expect.element(screen.getByText("This path parameter is required.")).toBeVisible();
        await expect.element(id).toHaveFocus();

        await requestPanel.getByRole("button", { name: "Cancel" }).click();
        await expect.element(id).toHaveValue("product-1");
        await expect.element(screen.getByLabelText("Request snippet", { exact: true })).not.toBeInTheDocument();
    });

    it("resets request state when the selected operation changes", async () => {
        const spec = {
            openapi: "3.1.0",
            info: { title: "Products", version: "1.0.0" },
            servers: [{ url: "https://api.example.test" }],
            paths: {
                "/products/{id}": {
                    get: {
                        parameters: [
                            {
                                name: "id",
                                in: "path",
                                required: true,
                                example: "product-1",
                                schema: { type: "string" },
                            },
                        ],
                        responses: { "200": { description: "OK" } },
                    },
                },
                "/orders/{order}": {
                    get: {
                        parameters: [
                            {
                                name: "order",
                                in: "path",
                                required: true,
                                example: "order-1",
                                schema: { type: "string" },
                            },
                        ],
                        responses: { "200": { description: "OK" } },
                    },
                },
            },
        };
        const screen = await render(
            <OperationView operationId="get-products-id" spec={spec} />,
        );

        await screen.getByLabelText("id").fill("changed");
        await screen.getByRole("button", { name: "Try it out" }).click();
        await expect.element(screen.getByRole("button", { name: "Cancel" })).toBeVisible();

        await screen.rerender(<OperationView operationId="get-orders-order" spec={spec} />);

        await expect.element(screen.getByLabelText("id")).not.toBeInTheDocument();
        await expect.element(screen.getByLabelText("order")).toHaveValue("order-1");
        await expect.element(screen.getByRole("button", { name: "Try it out" })).toBeVisible();
    });

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
