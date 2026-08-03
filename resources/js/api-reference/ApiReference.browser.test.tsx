import { afterEach, describe, expect, it, vi } from "vitest";
import { render } from "vitest-browser-react";
import type { Node } from "@lattice-php/lattice";
import ApiReference from "./ApiReference";

function apiReferenceNode(props: Record<string, unknown>): Node<"spectacular.api-reference"> {
    return { type: "spectacular.api-reference", props };
}

afterEach(() => {
    vi.restoreAllMocks();
});

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
                                    description:
                                        "Browse the product catalog and inspect the current availability of every item.\n\nUse filters to narrow the result set.",
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

        const listProducts = screen.getByRole("button", { name: /^List products/ });
        const createProduct = screen.getByRole("button", { name: /^Create product/ });
        const listOrders = screen.getByRole("button", { name: /^List orders/ }).all();
        const copyListProductsUrl = screen.getByRole("button", { name: "Copy List products URL" });
        const copyListProductsMarkdown = screen.getByRole("button", {
            name: "Copy List products as Markdown",
        });
        const clipboardWrite = vi.spyOn(navigator.clipboard, "writeText").mockResolvedValue();
        const listProductsHeader = listProducts.element().parentElement;

        await expect.element(screen.getByRole("navigation")).not.toBeInTheDocument();
        await expect.element(screen.getByRole("heading", { name: "Products", level: 2 })).toBeVisible();
        await expect.element(screen.getByRole("heading", { name: "Orders", level: 2 })).toBeVisible();
        await expect.element(screen.getByLabelText("Select server")).toBeVisible();
        await expect.element(listProducts).toHaveAttribute("aria-expanded", "true");
        expect(listProductsHeader).not.toBeNull();
        await expect.element(listProductsHeader as HTMLElement).toHaveTextContent("https://api.example.test/products");
        expect(listProducts.element().className).toContain("absolute inset-0");
        expect(listProductsHeader?.className).toContain("bg-lt-muted");
        expect(listProductsHeader?.querySelector("svg")?.compareDocumentPosition(listProducts.element()))
            .toBe(globalThis.Node.DOCUMENT_POSITION_FOLLOWING);
        await expect.element(createProduct).toHaveAttribute("aria-expanded", "false");
        expect(listOrders).toHaveLength(2);
        await expect.element(listOrders[0]).toHaveAttribute("aria-expanded", "false");
        await expect.element(listOrders[1]).toHaveAttribute("aria-expanded", "false");
        expect(document.querySelector("h1")).toBeNull();
        const description = screen.getByText(/Browse the product catalog/);
        await expect.element(description).toBeVisible();
        expect(getComputedStyle(description.element()).whiteSpace).toBe("pre-line");
        const requestPanel = screen.getByRole("complementary", { name: "Request" });
        await expect.element(requestPanel).toBeVisible();
        await expect.element(requestPanel.getByRole("button", { name: "Copy as Markdown" })).not.toBeInTheDocument();

        await copyListProductsUrl.click();
        await expect.poll(() => clipboardWrite.mock.calls[0]?.[0]).toBe("https://api.example.test/products");
        await copyListProductsMarkdown.click();
        await expect.poll(() => clipboardWrite.mock.calls[1]?.[0]).toContain("# List products");
        await expect.element(listProducts).toHaveAttribute("aria-expanded", "true");

        await listProducts.click();
        await expect.element(listProducts).toHaveAttribute("aria-expanded", "false");
        await expect.poll(() => screen.getByRole("complementary", { name: "Request" }).all().length).toBe(0);
        await listProducts.click();
        await expect.element(listProducts).toHaveAttribute("aria-expanded", "true");

        await createProduct.click();

        await expect.element(listProducts).toHaveAttribute("aria-expanded", "false");
        await expect.element(createProduct).toHaveAttribute("aria-expanded", "true");
        expect(document.querySelector("h1")).toBeNull();
        await expect.poll(() => screen.getByRole("complementary", { name: "Request" }).all().length).toBe(1);
        expect(window.location.hash).toBe("#post-products");

        await listOrders[1].click();

        await expect.element(listOrders[0]).toHaveAttribute("aria-expanded", "false");
        await expect.element(listOrders[1]).toHaveAttribute("aria-expanded", "true");
        await expect.poll(() => screen.getByRole("complementary", { name: "Request" }).all().length).toBe(1);
    });
});
