import { afterEach, describe, expect, it, vi } from "vitest";
import { userEvent } from "vitest/browser";
import { render } from "vitest-browser-react";
import type { Node } from "@lattice-php/lattice";
import ApiReference from "./ApiReference";
import { OperationView } from "./OperationView";
import { parseOperation } from "./parse";
import { RequestPlayground } from "./RequestPlayground";
import type { Contract, Operation, Param } from "./types";

const REAL_TOKEN = "real-secret-token";

afterEach(() => {
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
    };
}

function deferred<T>(): {
    promise: Promise<T>;
    resolve: (value: T) => void;
    reject: (reason?: unknown) => void;
} {
    let resolve!: (value: T) => void;
    let reject!: (reason?: unknown) => void;
    const promise = new Promise<T>((promiseResolve, promiseReject) => {
        resolve = promiseResolve;
        reject = promiseReject;
    });

    return { promise, resolve, reject };
}

function apiReferenceNode(props: Record<string, unknown>): Node<"spectacular.api-reference"> {
    return { type: "spectacular.api-reference", props };
}

describe("RequestPlayground", () => {
    it("renders labelled Lattice controls and keeps the selected live snippet copyable and token-safe", async () => {
        const screen = await render(
            <RequestPlayground
                operation={playgroundOperation()}
                baseUrl="https://api.example.test/v1"
                token={REAL_TOKEN}
                components={null}
            />,
        );

        const id = screen.getByLabelText("id");
        const status = screen.getByLabelText("status");
        const debug = screen.getByLabelText("X-Debug");
        const body = screen.getByLabelText("JSON body");
        const curl = screen.getByRole("radio", { name: "cURL" });
        const snippet = screen.getByLabelText("Request snippet", { exact: true });

        await expect.element(id).toBeVisible();
        await expect.element(status).toBeVisible();
        await expect.element(debug).toBeVisible();
        await expect.element(body).toBeVisible();
        await expect.element(curl).toHaveAttribute("aria-checked", "true");
        await expect.element(snippet).toHaveTextContent("Bearer <YOUR_TOKEN>");
        await expect.element(snippet).not.toHaveTextContent(REAL_TOKEN);

        await id.fill("a/b");
        await expect.element(snippet).toHaveTextContent("/widgets/a%2Fb");

        await status.selectOptions("archived");
        await expect.element(snippet).toHaveTextContent("status=archived");

        await body.fill('{"name":"Lamp"}');
        await expect.element(snippet).toHaveTextContent('{"name":"Lamp"}');

        await screen.getByRole("radio", { name: "JavaScript" }).click();
        await expect.element(snippet).toHaveTextContent('fetch("https://api.example.test/v1/widgets/a%2Fb?status=archived"');
        await expect.element(snippet).toHaveTextContent("Bearer <YOUR_TOKEN>");
        await expect.element(snippet).not.toHaveTextContent(REAL_TOKEN);

        const selectedSnippet = await snippet.element();
        await screen.getByRole("button", { name: "Copy request snippet" }).click();
        await body.fill("");
        await body.click();
        await userEvent.paste();

        await expect.element(body).toHaveValue(selectedSnippet.textContent);
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
        const idError = screen.getByText("This path parameter is required.");
        const traceField = screen.getByLabelText("X-Trace");
        const traceError = screen.getByText("This header parameter is required.");

        await expect.element(idError).toBeVisible();
        await expect.element(traceError).toBeVisible();
        await expect.element(idField).toHaveAttribute("aria-describedby");
        await expect.element(traceField).toHaveAttribute("aria-describedby");
        await expect.element(idField).toHaveFocus();
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it("preserves invalid JSON, shows its stable error, and focuses the body", async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal("fetch", fetchMock);
        const screen = await render(
            <RequestPlayground
                operation={playgroundOperation()}
                baseUrl="https://api.example.test"
                token={null}
                components={null}
            />,
        );
        const body = screen.getByLabelText("JSON body");

        await body.fill("{invalid");
        await screen.getByRole("button", { name: "Try it out" }).click();

        await expect.element(body).toHaveValue("{invalid");
        await expect.element(screen.getByText("Enter a valid JSON request body.")).toBeVisible();
        await expect.element(body).toHaveAttribute("aria-describedby");
        await expect.element(body).toHaveFocus();
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it("keeps complex parameters and non-JSON request data documented but out of the playground", async () => {
        const spec = {
            openapi: "3.1.0",
            info: { title: "Test API", version: "1.0.0" },
            paths: {
                "/widgets": {
                    post: {
                        parameters: [
                            { name: "filters", in: "query", schema: { type: "array", items: { type: "string" } } },
                            { name: "metadata", in: "header", schema: { type: "object" } },
                        ],
                        requestBody: {
                            content: {
                                "multipart/form-data": { schema: { type: "object" } },
                            },
                        },
                        responses: { "204": { description: "No content" } },
                    },
                },
            },
        };
        const operation = parseOperation(spec, "post-widgets");

        if (operation === null) {
            throw new Error("Expected the operation fixture to parse.");
        }

        const screen = await render(
            <OperationView spec={spec} operationId="post-widgets" baseUrl="https://api.example.test" />,
        );

        await expect.element(screen.getByText("filters", { exact: true })).toBeVisible();
        await expect.element(screen.getByText("metadata", { exact: true })).toBeVisible();
        await expect.element(screen.getByText("multipart/form-data")).toBeVisible();
        await expect.element(screen.getByLabelText("filters")).not.toBeInTheDocument();
        await expect.element(screen.getByLabelText("metadata")).not.toBeInTheDocument();
        await expect.element(screen.getByLabelText("JSON body")).not.toBeInTheDocument();
        await expect.element(screen.getByRole("button", { name: /Try it out/ })).toBeVisible();
    });

    it("shows limitations and blocks required unsupported parameters", async () => {
        const filters = parameter({
            name: "filters",
            required: true,
            schema: { type: "array", items: { type: "string" } },
        });
        const fetchMock = vi.fn();
        vi.stubGlobal("fetch", fetchMock);
        const screen = await render(
            <RequestPlayground
                operation={playgroundOperation({
                    paramGroups: [{ location: "query", params: [filters] }],
                    requests: [],
                })}
                baseUrl="https://api.example.test"
                token={null}
                components={null}
            />,
        );

        await screen.getByRole("button", { name: "Try it out" }).click();

        await expect.element(screen.getByText("Only primitive parameters can be executed.")).toBeVisible();
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it("keeps an empty optional complex parameter documented without blocking execution", async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response("ok", { status: 200, statusText: "OK" }));
        vi.stubGlobal("fetch", fetchMock);
        const spec = {
            openapi: "3.1.0",
            info: { title: "Test API", version: "1.0.0" },
            paths: {
                "/widgets": {
                    get: {
                        parameters: [
                            { name: "filters", in: "query", schema: { type: "array", items: { type: "string" } } },
                        ],
                        responses: { "200": { description: "OK" } },
                    },
                },
            },
        };
        const screen = await render(
            <OperationView spec={spec} operationId="get-widgets" baseUrl="https://api.example.test" />,
        );
        const tryButton = screen.getByRole("button", { name: "Try it out" });

        await expect.element(screen.getByText("filters", { exact: true })).toBeVisible();
        await expect.element(screen.getByText("Only primitive parameters can be executed.")).toBeVisible();
        await expect.element(screen.getByLabelText("filters")).not.toBeInTheDocument();
        await expect
            .element(screen.getByLabelText("Request snippet", { exact: true }))
            .toHaveTextContent("curl --request 'GET' --url 'https://api.example.test/widgets'");
        await expect.element(tryButton).not.toBeDisabled();

        await tryButton.click();

        await expect.poll(() => fetchMock.mock.calls.length).toBe(1);
    });

    it("blocks execution when an operation has only unsupported request bodies", async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal("fetch", fetchMock);
        const screen = await render(
            <RequestPlayground
                operation={playgroundOperation({
                    paramGroups: [],
                    requests: [requestContract({ mediaType: "multipart/form-data" })],
                })}
                baseUrl="https://api.example.test"
                token={null}
                components={null}
            />,
        );
        const tryButton = screen.getByRole("button", { name: "Try it out" });

        await expect.element(screen.getByText("Only JSON request bodies can be sent from the playground.")).toBeVisible();
        await expect.element(tryButton).toBeDisabled();

        const button = (await tryButton.element()) as HTMLButtonElement;
        button.disabled = false;
        await tryButton.click();

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it("assigns unique control, error, and test IDs to identical playground instances", async () => {
        const id = parameter({ name: "id", location: "path", required: true });
        const operation = playgroundOperation({
            paramGroups: [{ location: "path", params: [id] }],
            requests: [],
        });
        const screen = await render(
            <div>
                <RequestPlayground
                    operation={operation}
                    baseUrl="https://api.example.test"
                    token={null}
                    components={null}
                />
                <RequestPlayground
                    operation={operation}
                    baseUrl="https://api.example.test"
                    token={null}
                    components={null}
                />
            </div>,
        );
        const idFields = screen.getByLabelText("id").all();
        const copyButtons = screen.getByRole("button", { name: "Copy request snippet" }).all();
        const inputIds = await Promise.all(idFields.map(async (field) => (await field.element()).id));
        const errorIds = await Promise.all(
            idFields.map(async (field) => (await field.element()).getAttribute("aria-describedby")),
        );
        const testIds = await Promise.all(
            copyButtons.map(async (button) => (await button.element()).getAttribute("data-test")),
        );

        expect(new Set(inputIds).size).toBe(2);
        expect(new Set(errorIds).size).toBe(2);
        expect(new Set(testIds).size).toBe(2);
        expect(testIds).toEqual(expect.arrayContaining([expect.stringMatching(/^request-snippet-copy-/)]));
    });

    it("passes the token into single-operation, stacked, and sidebar playgrounds", async () => {
        const spec = {
            openapi: "3.1.0",
            info: { title: "Test API", version: "1.0.0" },
            servers: [{ url: "https://api.example.test" }],
            paths: {
                "/widgets/{id}": {
                    patch: {
                        parameters: [
                            { name: "id", in: "path", required: true, example: "42", schema: { type: "string" } },
                        ],
                        requestBody: {
                            required: true,
                            content: {
                                "application/json": {
                                    example: { name: "Desk" },
                                    schema: { type: "object" },
                                },
                            },
                        },
                        responses: { "200": { description: "OK" } },
                    },
                },
            },
        };
        const screen = await render(
            <div>
                <ApiReference
                    node={apiReferenceNode({
                        spec,
                        operation: "patch-widgets-id",
                        token: REAL_TOKEN,
                        hideHeader: true,
                    })}
                >
                    {null}
                </ApiReference>
                <ApiReference
                    node={apiReferenceNode({
                        spec,
                        layout: "stacked",
                        token: REAL_TOKEN,
                        hideHeader: true,
                        hideNav: true,
                    })}
                >
                    {null}
                </ApiReference>
                <ApiReference
                    node={apiReferenceNode({ spec, token: REAL_TOKEN, hideHeader: true, hideNav: true })}
                >
                    {null}
                </ApiReference>
            </div>,
        );

        await expect
            .poll(() => screen.getByLabelText("Request snippet", { exact: true }).all().length)
            .toBe(3);

        for (const snippet of screen.getByLabelText("Request snippet", { exact: true }).all()) {
            await expect.element(snippet).toHaveTextContent("Bearer <YOUR_TOKEN>");
            await expect.element(snippet).not.toHaveTextContent(REAL_TOKEN);
        }
    });

    it("disables while loading, presents a successful live response, and preserves it across edits", async () => {
        const pendingResponse = deferred<Response>();
        const fetchMock = vi.fn().mockReturnValue(pendingResponse.promise);
        vi.stubGlobal("fetch", fetchMock);
        const screen = await render(
            <RequestPlayground
                operation={playgroundOperation()}
                baseUrl="https://api.example.test"
                token={REAL_TOKEN}
                components={null}
            />,
        );
        const tryButton = screen.getByRole("button", { name: /Try it out/ });

        await tryButton.click();

        await expect.element(tryButton).toBeDisabled();
        await expect.element(screen.getByRole("status", { name: "Loading" })).toBeVisible();

        pendingResponse.resolve(
            new Response('{"ok":true}', {
                status: 201,
                statusText: "Created",
                headers: {
                    "Content-Type": "application/json",
                    "X-Request-Id": "request-123",
                },
            }),
        );

        await expect.element(screen.getByText("Live response")).toBeVisible();
        await expect.element(screen.getByText("201 Created")).toBeVisible();
        await expect.element(screen.getByText(/^\d+ ms$/)).toBeVisible();
        await expect.element(screen.getByText("content-type", { exact: true })).toBeVisible();
        await expect.element(screen.getByText("x-request-id", { exact: true })).toBeVisible();
        await expect.element(screen.getByText("request-123", { exact: true })).toBeVisible();
        await expect.element(screen.getByLabelText("Live response body")).toHaveTextContent('"ok": true');

        await screen.getByLabelText("id").fill("99");

        await expect.element(screen.getByText("201 Created")).toBeVisible();
        await expect.element(screen.getByLabelText("Live response body")).toHaveTextContent('"ok": true');
    });

    it("presents HTTP 422 as a live response", async () => {
        vi.stubGlobal(
            "fetch",
            vi.fn().mockResolvedValue(
                new Response('{"message":"Invalid widget"}', {
                    status: 422,
                    statusText: "Unprocessable Content",
                    headers: { "Content-Type": "application/json" },
                }),
            ),
        );
        const screen = await render(
            <RequestPlayground
                operation={playgroundOperation()}
                baseUrl="https://api.example.test"
                token={null}
                components={null}
            />,
        );

        await screen.getByRole("button", { name: /Try it out/ }).click();

        await expect.element(screen.getByText("422 Unprocessable Content")).toBeVisible();
        await expect
            .element(screen.getByLabelText("Live response body"))
            .toHaveTextContent('"message": "Invalid widget"');
    });

    it("presents the generic safe message for a network failure", async () => {
        vi.stubGlobal(
            "fetch",
            vi.fn().mockRejectedValue(new Error(`Failed with ${REAL_TOKEN} at https://private.example.test`)),
        );
        const screen = await render(
            <RequestPlayground
                operation={playgroundOperation()}
                baseUrl="https://api.example.test"
                token={REAL_TOKEN}
                components={null}
            />,
        );

        await screen.getByRole("button", { name: /Try it out/ }).click();

        await expect
            .element(screen.getByText("Request failed. Check the browser console and CORS configuration."))
            .toBeVisible();
        await expect.element(screen.locator).not.toHaveTextContent(REAL_TOKEN);
        await expect.element(screen.locator).not.toHaveTextContent("private.example.test");
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
