import { afterEach, describe, expect, it, vi } from "vitest";
import { userEvent } from "vitest/browser";
import { render } from "vitest-browser-react";
import type { Node } from "@lattice-php/lattice";
import ApiReference from "./ApiReference";
import { RequestPlayground } from "./RequestPlayground";
import type { Contract, Operation, Param } from "./types";

const REAL_TOKEN = "real-secret-token";

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

function parameter(overrides: Partial<Param>): Param {
    return {
        name: "value",
        location: "query",
        required: false,
        deprecated: false,
        description: null,
        schema: { type: "string" },
        example: null,
        ...overrides,
    };
}

function requestContract(overrides: Partial<Contract> = {}): Contract {
    return {
        role: "request",
        status: null,
        mediaType: "application/json",
        schema: {
            type: "object",
            properties: {
                name: { type: "string", example: "Desk" },
            },
        },
        title: null,
        examples: [],
        headers: [],
        required: true,
        ...overrides,
    };
}

function playgroundOperation(overrides: Partial<Operation> = {}): Operation {
    const id = parameter({ name: "id", location: "path", required: true, example: "42" });
    const status = parameter({
        name: "status",
        location: "query",
        schema: { type: "string", enum: ["active", "archived"] },
    });
    const debug = parameter({
        name: "X-Debug",
        location: "header",
        schema: { type: "boolean", default: false },
    });

    return {
        summary: {
            id: "update-widget",
            method: "PATCH",
            path: "/widgets/{id}",
            title: "Update widget",
            deprecated: false,
        },
        description: null,
        tags: [],
        paramGroups: [
            { location: "path", params: [id] },
            { location: "query", params: [status] },
            { location: "header", params: [debug] },
        ],
        requests: [requestContract()],
        responses: [],
        security: [],
        ...overrides,
        serverUrl: overrides.serverUrl ?? "https://api.example.test",
        servers: overrides.servers ?? [{ url: "https://api.example.test", description: null }],
        usesRootServers: overrides.usesRootServers ?? true,
    };
}

function apiReferenceNode(props: Record<string, unknown>): Node<"spectacular.api-reference"> {
    return { type: "spectacular.api-reference", props };
}

describe("RequestPlayground", () => {
    it("builds, copies, executes, and presents a request without exposing its token", async () => {
        const fetchMock = vi.fn().mockResolvedValue(
            new Response('{"ok":true}', {
                status: 201,
                statusText: "Created",
                headers: { "Content-Type": "application/json" },
            }),
        );
        vi.stubGlobal("fetch", fetchMock);
        const screen = await render(
            <RequestPlayground
                operation={playgroundOperation()}
                baseUrl="https://api.example.test/v1"
                token={REAL_TOKEN}
                components={null}
            />,
        );
        const id = screen.getByLabelText("id");
        const body = screen.getByLabelText("JSON body");
        const snippet = screen.getByLabelText("Request snippet", { exact: true });

        await expect.element(screen.getByLabelText("status")).toBeVisible();
        await expect.element(screen.getByLabelText("X-Debug")).toBeVisible();
        await expect.element(body).toBeVisible();
        await expect.element(screen.getByRole("radio", { name: "cURL" })).toHaveAttribute("aria-checked", "true");
        await expect.element(snippet).toHaveTextContent("Bearer <YOUR_TOKEN>");
        await expect.element(snippet).not.toHaveTextContent(REAL_TOKEN);

        await id.fill("a/b");
        await screen.getByLabelText("status").selectOptions("archived");
        await body.fill('{"name":"Lamp"}');
        await screen.getByRole("radio", { name: "JavaScript" }).click();

        await expect.element(snippet).toHaveTextContent(
            'fetch("https://api.example.test/v1/widgets/a%2Fb?status=archived"',
        );
        await expect.element(snippet).toHaveTextContent('{\\"name\\":\\"Lamp\\"}');
        await expect.element(snippet).toHaveTextContent("Bearer <YOUR_TOKEN>");
        await expect.element(snippet).not.toHaveTextContent(REAL_TOKEN);

        const selectedSnippet = await snippet.element();
        await screen.getByRole("button", { name: "Copy request snippet" }).click();
        await body.fill("");
        await body.click();
        await userEvent.paste();
        await expect.element(body).toHaveValue(selectedSnippet.textContent);
        await body.fill('{"name":"Lamp"}');

        await screen.getByRole("button", { name: "Try it out" }).click();

        await expect.poll(() => fetchMock.mock.calls.length).toBe(1);
        expect(fetchMock.mock.calls[0]?.[0]).toBe("https://api.example.test/v1/widgets/a%2Fb?status=archived");
        expect(new Headers(fetchMock.mock.calls[0]?.[1]?.headers).get("Authorization")).toBe(`Bearer ${REAL_TOKEN}`);
        await expect.element(screen.getByText("201 Created")).toBeVisible();
        await expect.element(screen.getByLabelText("Live response body")).toHaveTextContent('"ok": true');
        await expect.element(screen.locator).not.toHaveTextContent(REAL_TOKEN);
    });

    it("shows stable required errors without fetching and focuses the first invalid field", async () => {
        const id = parameter({ name: "id", location: "path", required: true });
        const trace = parameter({
            name: "X-Trace",
            location: "header",
            required: true,
            schema: { type: "boolean" },
        });
        const fetchMock = vi.fn();
        vi.stubGlobal("fetch", fetchMock);
        const screen = await render(
            <RequestPlayground
                operation={playgroundOperation({
                    paramGroups: [
                        { location: "path", params: [id] },
                        { location: "header", params: [trace] },
                    ],
                    requests: [],
                })}
                baseUrl="https://api.example.test"
                token={null}
                components={null}
            />,
        );

        await screen.getByRole("button", { name: "Try it out" }).click();

        const idField = screen.getByLabelText("id");
        const traceField = screen.getByLabelText("X-Trace");

        await expect.element(screen.getByText("This path parameter is required.")).toBeVisible();
        await expect.element(screen.getByText("This header parameter is required.")).toBeVisible();
        await expect.element(idField).toHaveAttribute("aria-describedby");
        await expect.element(traceField).toHaveAttribute("aria-describedby");
        await expect.element(idField).toHaveFocus();
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it("shows and executes the selected operation-level server instead of the root server", async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response("ok", { status: 200, statusText: "OK" }));
        vi.stubGlobal("fetch", fetchMock);
        const spec = {
            openapi: "3.1.0",
            info: { title: "Test API", version: "1.0.0" },
            servers: [
                { url: "https://production.example.test", description: "Production" },
                { url: "https://staging.example.test", description: "Staging" },
            ],
            paths: {
                "/widgets": {
                    get: {
                        servers: [
                            { url: "https://canary.operation.example", description: "Canary operation" },
                            { url: "https://sandbox.operation.example", description: "Sandbox operation" },
                        ],
                        responses: { "200": { description: "OK" } },
                    },
                },
            },
        };
        const screen = await render(
            <ApiReference node={apiReferenceNode({ spec, defaultOperation: "get-widgets", hideHeader: true })}>
                {null}
            </ApiReference>,
        );
        const serverPicker = screen.getByLabelText("Select server");
        const snippet = screen.getByLabelText("Request snippet", { exact: true });

        await expect.element(serverPicker).toHaveValue("https://canary.operation.example");
        await expect.element(snippet).toHaveTextContent("https://canary.operation.example/widgets");
        await serverPicker.selectOptions("https://sandbox.operation.example");
        await expect.element(snippet).toHaveTextContent("https://sandbox.operation.example/widgets");
        await screen.getByRole("button", { name: "Try it out" }).click();

        await expect.poll(() => fetchMock.mock.calls.length).toBe(1);
        expect(fetchMock.mock.calls[0]?.[0]).toBe("https://sandbox.operation.example/widgets");
    });

    it("aborts the active request before starting another", async () => {
        let firstSignal = new AbortController().signal;
        const fetchMock = vi.fn();
        fetchMock.mockImplementationOnce((_input: RequestInfo | URL, init?: RequestInit) => {
            firstSignal = init?.signal ?? firstSignal;

            return new Promise<Response>((_resolve, reject) => {
                firstSignal.addEventListener(
                    "abort",
                    () => reject(new DOMException("The operation was aborted.", "AbortError")),
                    { once: true },
                );
            });
        });
        fetchMock.mockResolvedValueOnce(new Response("second response", { status: 200, statusText: "OK" }));
        vi.stubGlobal("fetch", fetchMock);
        const screen = await render(
            <RequestPlayground
                operation={playgroundOperation()}
                baseUrl="https://api.example.test"
                token={null}
                components={null}
            />,
        );
        const tryButton = screen.getByRole("button", { name: /Try it out/ });

        await tryButton.click();
        const button = (await tryButton.element()) as HTMLButtonElement;

        if (button.form === null) {
            throw new Error("Expected the try button to submit the playground form.");
        }

        button.disabled = false;
        await tryButton.click();

        await expect.poll(() => fetchMock.mock.calls.length).toBe(2);
        expect(firstSignal.aborted).toBe(true);
        await expect.element(screen.getByText("200 OK")).toBeVisible();
        await expect.element(screen.getByLabelText("Live response body")).toHaveTextContent("second response");
    });

    it("aborts the active request when unmounted", async () => {
        let signal = new AbortController().signal;
        const fetchMock = vi.fn((_input: RequestInfo | URL, init?: RequestInit) => {
            signal = init?.signal ?? signal;

            return new Promise<Response>((_resolve, reject) => {
                signal.addEventListener(
                    "abort",
                    () => reject(new DOMException("The operation was aborted.", "AbortError")),
                    { once: true },
                );
            });
        });
        vi.stubGlobal("fetch", fetchMock);
        const screen = await render(
            <RequestPlayground
                operation={playgroundOperation()}
                baseUrl="https://api.example.test"
                token={null}
                components={null}
            />,
        );

        await screen.getByRole("button", { name: /Try it out/ }).click();
        await screen.unmount();

        expect(signal.aborted).toBe(true);
    });
});
