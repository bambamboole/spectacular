import { afterEach, describe, expect, it, vi } from "vitest";
import { render } from "vitest-browser-react";
import { OperationHeader } from "./OperationHeader";
import type { Operation } from "./types";

const operation: Operation = {
    summary: {
        id: "list-users",
        method: "GET",
        path: "/users",
        title: "List users",
        deprecated: false,
    },
    serverUrl: "https://api.example.test",
    servers: [{ url: "https://api.example.test", description: null }],
    usesRootServers: true,
    description: null,
    tags: [],
    paramGroups: [],
    requests: [],
    responses: [],
    security: [],
};

afterEach(() => {
    vi.restoreAllMocks();
});

describe("OperationHeader", () => {
    it("copies the URL from a compact action and places the labeled Markdown action on the right", async () => {
        const writeText = vi.spyOn(navigator.clipboard, "writeText");
        const screen = await render(
            <OperationHeader operation={operation} baseUrl="https://api.example.test" components={null} />,
        );
        const urlCopy = screen.getByRole("button", { name: "Copy operation URL" });
        const markdownCopy = screen.getByRole("button", {
            name: "Copy as Markdown",
        });

        await expect.element(screen.getByText("https://api.example.test/users", { exact: true })).toBeVisible();
        await expect.element(urlCopy).not.toHaveTextContent("Copy");
        await expect.element(markdownCopy).toHaveTextContent("Copy as Markdown");
        await expect.element(markdownCopy).toHaveClass("ml-auto");

        await urlCopy.click();
        await expect.poll(() => writeText.mock.calls[0]?.[0]).toBe("https://api.example.test/users");

        await markdownCopy.click();
        await expect.poll(() => writeText.mock.calls[1]?.[0]).toContain("# List users");
    });
});
