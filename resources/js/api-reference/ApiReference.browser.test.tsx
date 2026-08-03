import { describe, expect, it } from "vitest";
import { render } from "vitest-browser-react";
import type { Node } from "@lattice-php/lattice";
import ApiReference from "./ApiReference";

function apiReferenceNode(props: Record<string, unknown>): Node<"spectacular.api-reference"> {
    return { type: "spectacular.api-reference", props };
}

describe("ApiReference", () => {
    it("groups stacked operations by tag and mounts only the selected operation", async () => {
        window.history.replaceState(null, "", window.location.pathname);

        const screen = await render(
            <ApiReference
                node={apiReferenceNode({
                    layout: "stacked",
                    defaultOperation: "get-products",
                    hideHeader: true,
                    spec: {
                        openapi: "3.1.0",
                        info: { title: "Catalog", version: "1.0.0" },
                        servers: [
                            { url: "https://api.example.test", description: "Production" },
                            { url: "https://sandbox.example.test", description: "Sandbox" },
                        ],
                        paths: {
                            "/products": {
                                get: {
                                    summary: "List products",
                                    tags: ["Products"],
                                    responses: { "200": { description: "OK" } },
                                },
                                post: {
                                    summary: "Create product",
                                    tags: ["Products"],
                                    responses: { "201": { description: "Created" } },
                                },
                            },
                            "/orders": {
                                get: {
                                    summary: "List orders",
                                    tags: ["Products", "Orders"],
                                    responses: { "200": { description: "OK" } },
                                },
                            },
                        },
                    },
                })}
            >
                {null}
            </ApiReference>,
        );

        const listProducts = screen.getByRole("button", { name: /List products/ });
        const createProduct = screen.getByRole("button", { name: /Create product/ });
        const listOrders = screen.getByRole("button", { name: /List orders/ }).all();

        await expect.element(screen.getByRole("navigation")).not.toBeInTheDocument();
        await expect.element(screen.getByRole("heading", { name: "Products", level: 2 })).toBeVisible();
        await expect.element(screen.getByRole("heading", { name: "Orders", level: 2 })).toBeVisible();
        await expect.element(screen.getByLabelText("Select server")).toBeVisible();
        await expect.element(listProducts).toHaveAttribute("aria-expanded", "true");
        await expect.element(createProduct).toHaveAttribute("aria-expanded", "false");
        expect(listOrders).toHaveLength(2);
        await expect.element(listOrders[0]).toHaveAttribute("aria-expanded", "false");
        await expect.element(listOrders[1]).toHaveAttribute("aria-expanded", "false");
        await expect.element(screen.getByRole("heading", { name: "List products", level: 1 })).toBeVisible();
        await expect.element(screen.getByRole("heading", { name: "Create product", level: 1 })).not.toBeInTheDocument();
        await expect.poll(() => screen.getByRole("complementary", { name: "Request" }).all().length).toBe(1);

        await createProduct.click();

        await expect.element(listProducts).toHaveAttribute("aria-expanded", "false");
        await expect.element(createProduct).toHaveAttribute("aria-expanded", "true");
        await expect.element(screen.getByRole("heading", { name: "List products", level: 1 })).not.toBeInTheDocument();
        await expect.element(screen.getByRole("heading", { name: "Create product", level: 1 })).toBeVisible();
        await expect.poll(() => screen.getByRole("complementary", { name: "Request" }).all().length).toBe(1);
        expect(window.location.hash).toBe("#post-products");

        await listOrders[1].click();

        await expect.element(listOrders[0]).toHaveAttribute("aria-expanded", "false");
        await expect.element(listOrders[1]).toHaveAttribute("aria-expanded", "true");
        await expect.poll(() => screen.getByRole("complementary", { name: "Request" }).all().length).toBe(1);
    });
});
