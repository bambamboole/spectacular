import { describe, expect, it } from "vitest";
import { buildRequest, redactAuthorization } from "./request-builder";
import { parameterKey, type RequestValues } from "./request-state";
import { curlSnippet } from "./snippets/curl";
import { javascriptSnippet } from "./snippets/javascript";
import type { Contract, Operation, Param } from "./types";

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
        schema: { type: "object" },
        title: null,
        examples: [],
        headers: [],
        required: false,
        ...overrides,
    };
}

function operation(params: Param[] = [], requests: Contract[] = []): Operation {
    return {
        summary: {
            id: "post-widgets-id",
            method: "POST",
            path: "/widgets/{id}",
            title: "Update widget",
            deprecated: false,
        },
        description: null,
        tags: [],
        paramGroups: [
            { location: "path", params: params.filter((param) => param.location === "path") },
            { location: "query", params: params.filter((param) => param.location === "query") },
            { location: "header", params: params.filter((param) => param.location === "header") },
            { location: "cookie", params: params.filter((param) => param.location === "cookie") },
        ].filter((group) => group.params.length > 0),
        requests,
        responses: [],
        security: [],
    };
}

function values(parameters: Array<[Param, string]>, overrides: Partial<RequestValues> = {}): RequestValues {
    return {
        parameters: Object.fromEntries(parameters.map(([param, value]) => [parameterKey(param), value])),
        mediaType: null,
        body: "",
        ...overrides,
    };
}

describe("buildRequest", () => {
    it("encodes path and query parameters and builds scalar headers, JSON, and authorization", () => {
        const id = parameter({ name: "id", location: "path", required: true });
        const search = parameter({ name: "search", location: "query", required: true });
        const page = parameter({ name: "page", location: "query", schema: { type: "integer" } });
        const trace = parameter({ name: "X-Trace", location: "header", schema: { type: "boolean" } });
        const result = buildRequest({
            operation: operation([id, search, page, trace], [requestContract({ mediaType: "application/problem+json" })]),
            baseUrl: "https://api.example.test/v1/",
            values: values(
                [
                    [id, "a/b c"],
                    [search, "desk & chair"],
                    [page, "2"],
                    [trace, "false"],
                ],
                { mediaType: "application/problem+json", body: '{"title":"can\'t save"}' },
            ),
            token: "real-secret-token",
        });

        expect(result).toEqual({
            request: {
                method: "POST",
                url: "https://api.example.test/v1/widgets/a%2Fb%20c?search=desk%20%26%20chair&page=2",
                headers: {
                    "X-Trace": "false",
                    "Content-Type": "application/problem+json",
                    Authorization: "Bearer real-secret-token",
                },
                body: '{"title":"can\'t save"}',
            },
            errors: null,
        });
    });

    it("omits empty optional query and header values and does not invent authorization", () => {
        const id = parameter({ name: "id", location: "path", required: true });
        const filter = parameter({ name: "filter", location: "query" });
        const trace = parameter({ name: "X-Trace", location: "header" });

        expect(
            buildRequest({
                operation: operation([id, filter, trace]),
                baseUrl: "https://api.example.test",
                values: values([
                    [id, "7"],
                    [filter, ""],
                    [trace, ""],
                ]),
                token: null,
            }),
        ).toEqual({
            request: { method: "POST", url: "https://api.example.test/widgets/7", headers: {}, body: null },
            errors: null,
        });
    });

    it("omits an empty optional complex parameter", () => {
        const id = parameter({ name: "id", location: "path", required: true });
        const filters = parameter({ name: "filters", location: "query", schema: { type: "array" } });

        expect(
            buildRequest({
                operation: operation([id, filters]),
                baseUrl: "https://api.example.test",
                values: values([
                    [id, "7"],
                    [filters, ""],
                ]),
                token: null,
            }),
        ).toEqual({
            request: { method: "POST", url: "https://api.example.test/widgets/7", headers: {}, body: null },
            errors: null,
        });
    });

    it.each([
        ["cookie", parameter({ name: "session", location: "cookie" })],
        ["forbidden header", parameter({ name: "Host", location: "header" })],
    ])("omits an empty optional %s parameter", (_label, unsupported) => {
        expect(
            buildRequest({
                operation: operation([unsupported]),
                baseUrl: "https://api.example.test",
                values: values([[unsupported, ""]]),
                token: null,
            }),
        ).toEqual({
            request: { method: "POST", url: "https://api.example.test/widgets/{id}", headers: {}, body: null },
            errors: null,
        });
    });

    it.each([
        [
            "complex",
            parameter({ name: "filters", location: "query", required: true, schema: { type: "array" } }),
            "Only primitive parameters can be executed.",
        ],
        [
            "cookie",
            parameter({ name: "session", location: "cookie", required: true }),
            "Cookie parameters cannot be sent from a browser.",
        ],
        [
            "forbidden header",
            parameter({ name: "Host", location: "header", required: true }),
            "This header cannot be sent from a browser.",
        ],
    ])("blocks an empty required %s parameter", (_label, unsupported, message) => {
        expect(
            buildRequest({
                operation: operation([unsupported]),
                baseUrl: "https://api.example.test",
                values: values([[unsupported, ""]]),
                token: null,
            }),
        ).toEqual({
            request: null,
            errors: {
                parameters: { [parameterKey(unsupported)]: message },
                body: null,
                request: null,
            },
        });
    });

    it("blocks a populated cookie parameter", () => {
        const cookie = parameter({ name: "session", location: "cookie" });

        expect(
            buildRequest({
                operation: operation([cookie]),
                baseUrl: "https://api.example.test",
                values: values([[cookie, "secret"]]),
                token: null,
            }),
        ).toEqual({
            request: null,
            errors: {
                parameters: { "cookie:session": "Cookie parameters cannot be sent from a browser." },
                body: null,
                request: null,
            },
        });
    });

    it("deduplicates case-insensitive generated header collisions", () => {
        const id = parameter({ name: "id", location: "path", required: true });
        const contentType = parameter({ name: "content-type", location: "header" });
        const authorization = parameter({ name: "authorization", location: "header" });
        const result = buildRequest({
            operation: operation([id, contentType, authorization], [requestContract()]),
            baseUrl: "https://api.example.test",
            values: values(
                [
                    [id, "7"],
                    [contentType, "text/plain"],
                    [authorization, "Basic stale-credential"],
                ],
                { mediaType: "application/json", body: '{"name":"Desk"}' },
            ),
            token: "real-secret-token",
        });

        expect(result).toEqual({
            request: {
                method: "POST",
                url: "https://api.example.test/widgets/7",
                headers: {
                    "Content-Type": "application/json",
                    Authorization: "Bearer real-secret-token",
                },
                body: '{"name":"Desk"}',
            },
            errors: null,
        });

        if (result.request === null) {
            throw new Error("Expected a built request.");
        }

        const redacted = redactAuthorization(result.request);
        for (const snippet of [curlSnippet.generate(redacted), javascriptSnippet.generate(redacted)]) {
            expect(snippet.match(/Content-Type/g)).toHaveLength(1);
            expect(snippet.match(/Authorization/g)).toHaveLength(1);
            expect(snippet).toContain("application/json");
            expect(snippet).toContain("Bearer <YOUR_TOKEN>");
            expect(snippet).not.toContain("text/plain");
            expect(snippet).not.toContain("stale-credential");
            expect(snippet).not.toContain("real-secret-token");
        }
    });

    it("returns field errors for empty required path, query, and header values", () => {
        const id = parameter({ name: "id", location: "path", required: true });
        const filter = parameter({ name: "filter", location: "query", required: true });
        const trace = parameter({ name: "X-Trace", location: "header", required: true });

        expect(
            buildRequest({
                operation: operation([id, filter, trace]),
                baseUrl: "https://api.example.test",
                values: values([
                    [id, ""],
                    [filter, ""],
                    [trace, ""],
                ]),
                token: null,
            }),
        ).toEqual({
            request: null,
            errors: {
                parameters: {
                    "path:id": "This path parameter is required.",
                    "query:filter": "This query parameter is required.",
                    "header:X-Trace": "This header parameter is required.",
                },
                body: null,
                request: null,
            },
        });
    });

    it("rejects missing and invalid required JSON bodies", () => {
        const requiredJson = requestContract({ required: true });

        expect(
            buildRequest({
                operation: operation([], [requiredJson]),
                baseUrl: "https://api.example.test",
                values: values([], { mediaType: "application/json", body: "" }),
                token: null,
            }),
        ).toEqual({
            request: null,
            errors: { parameters: {}, body: "A JSON request body is required.", request: null },
        });

        expect(
            buildRequest({
                operation: operation([], [requiredJson]),
                baseUrl: "https://api.example.test",
                values: values([], { mediaType: "application/json", body: "{invalid" }),
                token: null,
            }),
        ).toEqual({
            request: null,
            errors: { parameters: {}, body: "Enter a valid JSON request body.", request: null },
        });
    });

    it.each(["Cookie", "Host", "Content-Length", "Origin"])("rejects the forbidden %s header", (name) => {
        const forbidden = parameter({ name, location: "header" });

        expect(
            buildRequest({
                operation: operation([forbidden]),
                baseUrl: "https://api.example.test",
                values: values([[forbidden, "unsafe"]]),
                token: null,
            }),
        ).toEqual({
            request: null,
            errors: {
                parameters: { [`header:${name}`]: "This header cannot be sent from a browser." },
                body: null,
                request: null,
            },
        });
    });

    it.each(["array", "object"])("rejects %s parameters as non-executable", (type) => {
        const complex = parameter({ name: "complex", schema: { type } });

        expect(
            buildRequest({
                operation: operation([complex]),
                baseUrl: "https://api.example.test",
                values: values([[complex, "value"]]),
                token: null,
            }),
        ).toEqual({
            request: null,
            errors: {
                parameters: { "query:complex": "Only primitive parameters can be executed." },
                body: null,
                request: null,
            },
        });
    });

    it.each([
        ["$ref", { $ref: "#/components/schemas/Identifier" }],
        ["oneOf", { oneOf: [{ type: "string" }, { type: "integer" }] }],
        ["allOf", { allOf: [{ type: "string" }] }],
        ["anyOf", { anyOf: [{ type: "string" }] }],
        ["missing type", {}],
    ])("rejects the %s non-scalar parameter schema", (_label, schema) => {
        const unresolved = parameter({ name: "unresolved", schema });

        expect(
            buildRequest({
                operation: operation([unresolved]),
                baseUrl: "https://api.example.test",
                values: values([[unresolved, "value"]]),
                token: null,
            }),
        ).toEqual({
            request: null,
            errors: {
                parameters: { "query:unresolved": "Only primitive parameters can be executed." },
                body: null,
                request: null,
            },
        });
    });

    it.each([
        [
            "absolute",
            "https://api.example.test/v1?locale=en#documentation",
            "https://api.example.test/v1/widgets/a%2Fb?locale=en&search=desk%20%26%20chair",
        ],
        [
            "relative",
            "../api/v1?locale=en#documentation",
            "../api/v1/widgets/a%2Fb?locale=en&search=desk%20%26%20chair",
        ],
    ])("joins an %s base URL query and fragment at the pathname", (_kind, baseUrl, expectedUrl) => {
        const id = parameter({ name: "id", location: "path", required: true });
        const search = parameter({ name: "search", location: "query" });

        expect(
            buildRequest({
                operation: operation([id, search]),
                baseUrl,
                values: values([
                    [id, "a/b"],
                    [search, "desk & chair"],
                ]),
                token: null,
            }),
        ).toEqual({
            request: { method: "POST", url: expectedUrl, headers: {}, body: null },
            errors: null,
        });
    });

    it("returns a request error when no server URL is selected", () => {
        expect(
            buildRequest({ operation: operation(), baseUrl: null, values: values([]), token: null }),
        ).toEqual({
            request: null,
            errors: { parameters: {}, body: null, request: "Select a server URL before sending the request." },
        });
    });
});

describe("redactAuthorization", () => {
    it("clones the request and redacts a case-insensitive bearer authorization header", () => {
        const request = {
            method: "GET",
            url: "https://api.example.test/widgets",
            headers: { authorization: "bearer real-secret-token", Accept: "application/json" },
            body: null,
        };

        expect(redactAuthorization(request)).toEqual({
            ...request,
            headers: { authorization: "Bearer <YOUR_TOKEN>", Accept: "application/json" },
        });
        expect(request.headers.authorization).toBe("bearer real-secret-token");
    });
});
