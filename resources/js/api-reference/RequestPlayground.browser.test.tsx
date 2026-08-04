import { afterEach, describe, expect, it, vi } from "vitest";
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
            required: ["name"],
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
        const requestPanel = screen.getByRole("complementary", { name: "Request" });
        const referencePanel = screen.getByRole("complementary", { name: "Reference" });
        const execute = requestPanel.getByRole("button", { name: "Execute" });
        const markdownCopy = requestPanel.getByRole("button", { name: "Copy as Markdown" });
        const statusType = screen.getByLabelText("status").element().closest("li")?.querySelectorAll("span")[1];

        await expect.element(id).toHaveValue("42");
        await expect.element(requestPanel.getByText("Try it out")).not.toBeInTheDocument();
        expect(requestPanel.element().querySelector('[data-slot="card"]')).toBeNull();
        expect(execute.element().parentElement).toBe(markdownCopy.element().parentElement);
        expect(requestPanel.element().parentElement?.classList).toContain(
            "lg:grid-cols-[minmax(0,1fr)_minmax(22rem,32rem)]",
        );
        expect(requestPanel.element().classList).toContain("lg:col-start-1");
        expect(referencePanel.element().classList).toContain("lg:col-start-2");
        expect(referencePanel.element().classList).toContain("lg:border-l");
        expect(statusType?.classList).toContain("px-2");
        expect(statusType?.classList).toContain("py-1");
        await expect.element(markdownCopy).toHaveClass("ml-auto");
        await expect.element(screen.getByLabelText("status")).toBeVisible();
        await expect.element(screen.getByLabelText("X-Debug")).toBeVisible();
        await expect.element(body).toBeVisible();
        await expect.element(screen.getByRole("radio", { name: "cURL" })).toHaveAttribute("aria-checked", "true");
        await expect.element(snippet).toHaveAttribute("data-slot", "code-block");
        await expect.poll(() => document.querySelector(".cm-content")?.getAttribute("contenteditable")).toBe("false");
        await expect.poll(() => snippet.element().querySelector(".cm-lineNumbers")).not.toBeNull();
        await expect.element(snippet).toHaveTextContent("Bearer <YOUR_TOKEN>");
        await expect.element(snippet).not.toHaveTextContent(REAL_TOKEN);

        await id.fill("a/b");
        await screen.getByLabelText("status").selectOptions("archived");
        await body.fill('{"name":"Lamp"}');
        await screen.getByRole("radio", { name: "JavaScript" }).click();

        await expect.element(snippet).toHaveTextContent(
            'fetch("https://api.example.test/v1/widgets/a%2Fb?status=archived"',
        );
        await expect.element(snippet).toHaveTextContent('\\"name\\":\\"Lamp\\"');
        await expect.element(snippet).toHaveTextContent("Bearer <YOUR_TOKEN>");
        await expect.element(snippet).not.toHaveTextContent(REAL_TOKEN);

        const selectedSnippet = snippet.element().querySelector<HTMLElement>(".cm-content")?.innerText;
        expect(selectedSnippet).not.toBeNull();
        const clipboardWrite = vi.spyOn(navigator.clipboard, "writeText").mockResolvedValue();
        await screen.getByRole("button", { name: "Copy Request snippet" }).click();
        await expect.poll(() => clipboardWrite.mock.calls.length).toBe(1);
        expect(clipboardWrite).toHaveBeenCalledWith(selectedSnippet);
        await expect.element(screen.getByRole("button", { name: "Copied Request snippet" })).toBeVisible();
        await markdownCopy.click();
        await expect.poll(() => clipboardWrite.mock.calls[1]?.[0]).toContain("# Update widget");
        await execute.click();

        await expect.poll(() => fetchMock.mock.calls.length).toBe(1);
        expect(fetchMock.mock.calls[0]?.[0]).toBe("https://api.example.test/v1/widgets/a%2Fb?status=archived");
        expect(new Headers(fetchMock.mock.calls[0]?.[1]?.headers).get("Authorization")).toBe(`Bearer ${REAL_TOKEN}`);
        await expect.element(screen.getByText("201 Created")).toBeVisible();
        const responseBody = screen.getByRole("region", { name: "Live response body" });
        await expect.element(responseBody).toHaveAttribute("data-slot", "code-block");
        await expect.element(responseBody).toHaveTextContent('"ok": true');
        await expect.element(screen.locator).not.toHaveTextContent(REAL_TOKEN);
    });

    it("edits JSON request bodies as text", async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response("{}", { status: 200, statusText: "OK" }));
        vi.stubGlobal("fetch", fetchMock);
        const screen = await render(
            <RequestPlayground
                operation={playgroundOperation({
                    summary: {
                        id: "create-order",
                        method: "POST",
                        path: "/orders",
                        title: "Create order",
                        deprecated: false,
                    },
                    paramGroups: [],
                    requests: [
                        requestContract({
                            schema: {
                                type: "object",
                                required: ["name", "active", "launchDate", "address", "items"],
                                properties: {
                                    name: { type: "string", example: "Desk" },
                                    status: { type: "string", enum: ["draft", "confirmed"] },
                                    active: { type: "boolean", default: false },
                                    launchDate: { type: "string", format: "date", example: "2026-08-03" },
                                    address: {
                                        type: "object",
                                        required: ["city"],
                                        properties: {
                                            city: { type: "string", example: "Berlin" },
                                        },
                                    },
                                    items: {
                                        type: "array",
                                        items: {
                                            type: "object",
                                            required: ["sku", "quantity"],
                                            properties: {
                                                sku: { type: "string", example: "SKU-1" },
                                                quantity: { type: "integer", default: 1 },
                                            },
                                        },
                                    },
                                },
                            },
                        }),
                    ],
                })}
                baseUrl="https://api.example.test"
                token={null}
                components={null}
            />,
        );

        const body = screen.getByLabelText("JSON body");
        await expect.element(body).toHaveValue(
            JSON.stringify(
                {
                    name: "Desk",
                    active: false,
                    launchDate: "2026-08-03",
                    address: { city: "Berlin" },
                    items: [{ sku: "SKU-1", quantity: 1 }],
                },
                null,
                2,
            ),
        );

        await body.fill("{");
        await expect.element(screen.getByText("Enter a valid JSON request body.")).toBeVisible();
        await screen.getByRole("button", { name: "Execute" }).click();
        expect(fetchMock).not.toHaveBeenCalled();
        await body.fill('{"name":"Lamp","active":true,"items":[{"sku":"SKU-2","quantity":3}]}');
        await screen.getByRole("button", { name: "Execute" }).click();

        await expect.poll(() => fetchMock.mock.calls.length).toBe(1);
        expect(JSON.parse(String(fetchMock.mock.calls[0]?.[1]?.body))).toEqual({
            name: "Lamp",
            active: true,
            items: [{ sku: "SKU-2", quantity: 3 }],
        });
    });

    it("builds Laravel Query Builder filters, sorts, includes, and fields", async () => {
        const filter = parameter({ name: "filter[name]", location: "query" });
        const sort = parameter({
            name: "sort",
            location: "query",
            style: "form",
            explode: false,
            schema: {
                type: "array",
                items: {
                    type: "string",
                    enum: [
                        "name",
                        "-name",
                        "created_at",
                        "-created_at",
                        "email",
                        "-email",
                        "id",
                        "-id",
                        "status",
                        "-status",
                    ],
                },
            },
        });
        const include = parameter({
            name: "include",
            location: "query",
            style: "form",
            explode: false,
            schema: { type: "array", items: { type: "string", enum: ["roles", "rolesCount"] } },
        });
        const fields = parameter({
            name: "fields[users]",
            location: "query",
            style: "form",
            explode: false,
            schema: { type: "array", items: { type: "string", enum: ["id", "name", "email"] } },
        });
        const screen = await render(
            <RequestPlayground
                operation={playgroundOperation({
                    summary: {
                        id: "list-users",
                        method: "GET",
                        path: "/users",
                        title: "List users",
                        deprecated: false,
                    },
                    paramGroups: [{ location: "query", params: [filter, sort, include, fields] }],
                    requests: [],
                })}
                baseUrl="https://api.example.test"
                token={null}
                components={null}
            />,
        );

        const filterField = screen.getByLabelText("filter[name]");
        const sortField = screen.getByRole("button", { name: "sort" });
        const includeField = screen.getByRole("button", { name: "include" });
        const fieldsField = screen.getByRole("button", { name: "fields[users]" });
        const snippet = screen.getByLabelText("Request snippet", { exact: true });

        await filterField.fill("Taylor");
        await sortField.click();
        await expect.element(screen.getByLabelText("Search options")).toBeVisible();
        const sortOption = screen.getByRole("option", { name: "-created_at" });
        await sortOption.click();
        document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true }));
        await includeField.click();
        await expect.element(screen.getByLabelText("Search options")).not.toBeInTheDocument();
        await screen.getByRole("option", { name: "roles", exact: true }).click();
        const rolesCountOption = screen.getByRole("option", { name: "rolesCount" });
        await rolesCountOption.click();
        document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true }));
        await fieldsField.click();
        await screen.getByRole("option", { name: "id" }).click();
        const emailOption = screen.getByRole("option", { name: "email" });
        await emailOption.click();
        document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true }));

        await expect.element(sortField).toHaveTextContent("-created_at");
        await expect.element(includeField).toHaveTextContent("roles, rolesCount");
        await expect.element(fieldsField).toHaveTextContent("id, email");
        await expect
            .element(snippet)
            .toHaveTextContent(
                "https://api.example.test/users?filter%5Bname%5D=Taylor&sort=-created_at&include=roles%2CrolesCount&fields%5Busers%5D=id%2Cemail",
            );

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

        await screen.getByRole("button", { name: "Execute" }).click();

        const idField = screen.getByLabelText("id");
        const traceField = screen.getByLabelText("X-Trace");

        await expect.element(screen.getByText("This path parameter is required.")).toBeVisible();
        await expect.element(screen.getByText("This header parameter is required.")).toBeVisible();
        await expect.element(idField).toHaveAttribute("aria-describedby");
        await expect.element(traceField).toHaveAttribute("aria-describedby");
        await expect.element(idField).toHaveAttribute("aria-labelledby");
        await expect.element(traceField).toHaveAttribute("aria-labelledby");
        await expect.element(idField).toHaveFocus();
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it("maps schema formats and constraints to native inputs", async () => {
        const email = parameter({
            name: "email",
            required: true,
            schema: {
                type: "string",
                format: "email",
                minLength: 5,
                maxLength: 64,
                pattern: "^[^@]+@[^@]+$",
            },
        });
        const rating = parameter({
            name: "rating",
            schema: { type: "number", minimum: 1, maximum: 10, multipleOf: 0.5 },
        });
        const birthday = parameter({ name: "birthday", schema: { type: "string", format: "date" } });
        const screen = await render(
            <RequestPlayground
                operation={playgroundOperation({
                    paramGroups: [{ location: "query", params: [email, rating, birthday] }],
                    requests: [],
                })}
                baseUrl="https://api.example.test"
                token={null}
                components={null}
            />,
        );

        await expect.element(screen.getByLabelText("email")).toHaveAttribute("type", "email");
        await expect.element(screen.getByLabelText("email")).toHaveAttribute("minlength", "5");
        await expect.element(screen.getByLabelText("email")).toHaveAttribute("maxlength", "64");
        await expect.element(screen.getByLabelText("email")).toHaveAttribute("pattern", "^[^@]+@[^@]+$");
        await expect.element(screen.getByLabelText("rating")).toHaveAttribute("type", "number");
        await expect.element(screen.getByLabelText("rating")).toHaveAttribute("min", "1");
        await expect.element(screen.getByLabelText("rating")).toHaveAttribute("max", "10");
        await expect.element(screen.getByLabelText("rating")).toHaveAttribute("step", "0.5");
        await expect.element(screen.getByLabelText("birthday")).toHaveAttribute("type", "date");
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
        await screen.getByRole("button", { name: "Execute" }).click();

        await expect.poll(() => fetchMock.mock.calls.length).toBe(1);
        expect(fetchMock.mock.calls[0]?.[0]).toBe("https://sandbox.operation.example/widgets");
    });

    it("switches response contracts with a select", async () => {
        const screen = await render(
            <RequestPlayground
                operation={playgroundOperation({
                    responses: [
                        requestContract({ role: "response", status: "200", title: "Successful response" }),
                        requestContract({ role: "response", status: "422", title: "Validation response" }),
                    ],
                })}
                baseUrl="https://api.example.test"
                token={null}
                components={null}
            />,
        );
        const responseStatus = screen.getByRole("combobox", { name: "Response status" });

        await expect.element(responseStatus).toHaveValue("200 application/json");
        await expect.element(screen.getByText("Successful response")).toBeVisible();
        await responseStatus.selectOptions("422 application/json");
        await expect.element(screen.getByText("Validation response")).toBeVisible();
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
        const executeButton = screen.getByRole("button", { name: "Execute" });

        await executeButton.click();
        const button = (await executeButton.element()) as HTMLButtonElement;

        if (button.form === null) {
            throw new Error("Expected the execute button to submit the playground form.");
        }

        button.disabled = false;
        await executeButton.click();

        await expect.poll(() => fetchMock.mock.calls.length).toBe(2);
        expect(firstSignal.aborted).toBe(true);
        await expect.element(screen.getByText("200 OK")).toBeVisible();
        await expect.element(screen.getByRole("region", { name: "Live response body" })).toHaveTextContent("second response");
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

        await screen.getByRole("button", { name: "Execute" }).click();
        await screen.unmount();

        expect(signal.aborted).toBe(true);
    });
});
