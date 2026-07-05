import { describe, expect, it } from "vitest";
import { buildNavigation, filterNavigationByTags, parseOperation } from "./parse";

const spec = {
    openapi: "3.0.0",
    info: { title: "Test API", version: "1.0.0", description: "A tiny test API" },
    paths: {
        "/users/{id}": {
            parameters: [{ name: "id", in: "path", required: true, schema: { type: "string" } }],
            get: {
                operationId: "getUser",
                summary: "Get user",
                description: "Fetches a single user by id.",
                tags: ["Users", "Admin"],
                parameters: [{ name: "include", in: "query", required: false, schema: { type: "string" } }],
                requestBody: {
                    description: "User payload",
                    content: {
                        "application/json": {
                            schema: { type: "object", properties: { name: { type: "string" } } },
                        },
                    },
                },
                responses: {
                    "200": {
                        description: "OK",
                        content: {
                            "application/json": { schema: { type: "object" } },
                        },
                    },
                    "404": { $ref: "#/components/responses/NotFound" },
                },
            },
        },
        "/pets": {
            get: {
                summary: "List pets",
                tags: ["Pets"],
                responses: { "200": { description: "OK" } },
            },
            post: {
                deprecated: true,
                responses: { "201": { description: "Created" } },
            },
        },
        "/posts": {
            get: {
                operationId: "listPosts",
                summary: "List posts",
                tags: ["Posts"],
                parameters: [{ $ref: "#/components/parameters/PageParam" }],
                responses: {
                    "200": {
                        description: "OK",
                        content: { "application/json": { schema: { type: "array" } } },
                    },
                },
            },
        },
        "/articles": {
            post: {
                operationId: "createArticle",
                summary: "Create article",
                tags: ["Articles"],
                requestBody: { $ref: "#/components/requestBodies/UserBody" },
                responses: {
                    "201": {
                        description: "Created",
                        content: { "application/json": { schema: { type: "object" } } },
                    },
                },
            },
        },
    },
    components: {
        parameters: {
            PageParam: {
                name: "page",
                in: "query",
                required: false,
                schema: { type: "integer" },
                description: "Page number",
            },
        },
        requestBodies: {
            UserBody: {
                description: "User creation payload",
                content: {
                    "application/json": {
                        schema: {
                            type: "object",
                            properties: {
                                name: { type: "string" },
                            },
                        },
                    },
                },
            },
        },
        responses: {
            NotFound: {
                description: "Not found",
                content: {
                    "application/json": {
                        schema: { type: "object", properties: { message: { type: "string" } } },
                    },
                },
            },
        },
    },
};

describe("buildNavigation", () => {
    it("exposes the spec info", () => {
        const nav = buildNavigation(spec);
        expect(nav.info).toEqual({ title: "Test API", version: "1.0.0", description: "A tiny test API" });
    });

    it("groups operations by tag, in first-appearance order, with a Default fallback", () => {
        const nav = buildNavigation(spec);
        expect(nav.groups.map((g) => g.title)).toEqual(["Users", "Admin", "Pets", "Default", "Posts", "Articles"]);
    });

    it("puts a multi-tag operation's id into every one of its tag groups", () => {
        const nav = buildNavigation(spec);
        const users = nav.groups.find((g) => g.title === "Users")!;
        const admin = nav.groups.find((g) => g.title === "Admin")!;
        expect(users.operationIds).toEqual(["get-users-id"]);
        expect(admin.operationIds).toEqual(["get-users-id"]);
    });

    it("groups tagless operations under Default", () => {
        const nav = buildNavigation(spec);
        const fallback = nav.groups.find((g) => g.title === "Default")!;
        expect(fallback.operationIds).toEqual(["post-pets"]);
    });

    it("builds a cheap summary per operation, without parsing params/bodies", () => {
        const nav = buildNavigation(spec);

        expect(nav.summaries["get-users-id"]).toEqual({
            id: "get-users-id",
            method: "GET",
            path: "/users/{id}",
            title: "getUser",
            deprecated: false,
        });

        expect(nav.summaries["get-pets"]).toEqual({
            id: "get-pets",
            method: "GET",
            path: "/pets",
            title: "List pets",
            deprecated: false,
        });

        expect(nav.summaries["post-pets"]).toEqual({
            id: "post-pets",
            method: "POST",
            path: "/pets",
            title: "POST /pets",
            deprecated: true,
        });
    });
});

describe("parseOperation", () => {
    it("returns null for an unknown operation id", () => {
        expect(parseOperation(spec, "get-does-not-exist")).toBeNull();
    });

    it("merges shared and operation-level params, bucketed by location", () => {
        const op = parseOperation(spec, "get-users-id")!;

        expect(op.paramGroups).toEqual([
            {
                location: "path",
                params: [
                    {
                        name: "id",
                        location: "path",
                        required: true,
                        deprecated: false,
                        description: null,
                        schema: { type: "string" },
                    },
                ],
            },
            {
                location: "query",
                params: [
                    {
                        name: "include",
                        location: "query",
                        required: false,
                        deprecated: false,
                        description: null,
                        schema: { type: "string" },
                    },
                ],
            },
        ]);
    });

    it("carries the summary, description and tags", () => {
        const op = parseOperation(spec, "get-users-id")!;

        expect(op.summary).toEqual({
            id: "get-users-id",
            method: "GET",
            path: "/users/{id}",
            title: "getUser",
            deprecated: false,
        });
        expect(op.description).toBe("Fetches a single user by id.");
        expect(op.tags).toEqual(["Users", "Admin"]);
    });

    it("builds the request Contract from requestBody", () => {
        const op = parseOperation(spec, "get-users-id")!;

        expect(op.requests).toEqual([
            {
                role: "request",
                status: null,
                mediaType: "application/json",
                schema: { type: "object", properties: { name: { type: "string" } } },
                title: "User payload",
                examples: [],
            },
        ]);
    });

    it("builds a response Contract per status/mediaType, resolving a $ref response", () => {
        const op = parseOperation(spec, "get-users-id")!;

        expect(op.responses).toEqual([
            {
                role: "response",
                status: "200",
                mediaType: "application/json",
                schema: { type: "object" },
                title: "OK",
                examples: [],
            },
            {
                role: "response",
                status: "404",
                mediaType: "application/json",
                schema: { type: "object", properties: { message: { type: "string" } } },
                title: "Not found",
                examples: [],
            },
        ]);
    });

    it("produces a bodyless response Contract with a null schema", () => {
        const op = parseOperation(spec, "get-pets")!;

        expect(op.responses).toEqual([
            {
                role: "response",
                status: "200",
                mediaType: null,
                schema: null,
                title: "OK",
                examples: [],
            },
        ]);
    });

    it("resolves a $ref parameter and merges it into paramGroups", () => {
        const op = parseOperation(spec, "get-posts")!;

        expect(op.paramGroups).toEqual([
            {
                location: "query",
                params: [
                    {
                        name: "page",
                        location: "query",
                        required: false,
                        deprecated: false,
                        description: "Page number",
                        schema: { type: "integer" },
                    },
                ],
            },
        ]);
    });

    it("resolves a $ref requestBody and builds the request Contract", () => {
        const op = parseOperation(spec, "post-articles")!;

        expect(op.requests).toEqual([
            {
                role: "request",
                status: null,
                mediaType: "application/json",
                schema: { type: "object", properties: { name: { type: "string" } } },
                title: "User creation payload",
                examples: [],
            },
        ]);
    });

    it("resolves to no security requirements when neither the operation nor the spec declares any", () => {
        const op = parseOperation(spec, "get-users-id")!;

        expect(op.security).toEqual([]);
    });
});

describe("buildExamples", () => {
    it("returns a single unnamed example from `example` on both a request and a response mediaType", () => {
        const op = parseOperation(
            {
                openapi: "3.0.0",
                info: { title: "Examples API", version: "1.0.0", description: null },
                paths: {
                    "/widgets": {
                        post: {
                            operationId: "createWidget",
                            requestBody: {
                                content: {
                                    "application/json": {
                                        schema: { type: "object" },
                                        example: { name: "Widget" },
                                    },
                                },
                            },
                            responses: {
                                "201": {
                                    description: "Created",
                                    content: {
                                        "application/json": {
                                            schema: { type: "object" },
                                            example: { id: 1, name: "Widget" },
                                        },
                                    },
                                },
                            },
                        },
                    },
                },
            },
            "post-widgets",
        )!;

        expect(op.requests[0].examples).toEqual([{ name: null, summary: null, value: { name: "Widget" } }]);
        expect(op.responses[0].examples).toEqual([{ name: null, summary: null, value: { id: 1, name: "Widget" } }]);
    });

    it("returns one ContractExample per key in an `examples` map, with name and summary", () => {
        const op = parseOperation(
            {
                openapi: "3.0.0",
                info: { title: "Examples API", version: "1.0.0", description: null },
                paths: {
                    "/widgets": {
                        get: {
                            operationId: "getWidgets",
                            responses: {
                                "200": {
                                    description: "OK",
                                    content: {
                                        "application/json": {
                                            schema: { type: "object" },
                                            examples: {
                                                basic: { summary: "A basic widget", value: { id: 1 } },
                                                deluxe: { value: { id: 2, deluxe: true } },
                                            },
                                        },
                                    },
                                },
                            },
                        },
                    },
                },
            },
            "get-widgets",
        )!;

        expect(op.responses[0].examples).toEqual([
            { name: "basic", summary: "A basic widget", value: { id: 1 } },
            { name: "deluxe", summary: null, value: { id: 2, deluxe: true } },
        ]);
    });

    it("resolves a `{ $ref }` example from components.examples", () => {
        const op = parseOperation(
            {
                openapi: "3.0.0",
                info: { title: "Examples API", version: "1.0.0", description: null },
                paths: {
                    "/widgets": {
                        get: {
                            operationId: "getWidgets",
                            responses: {
                                "200": {
                                    description: "OK",
                                    content: {
                                        "application/json": {
                                            schema: { type: "object" },
                                            examples: {
                                                shared: { $ref: "#/components/examples/SharedWidget" },
                                            },
                                        },
                                    },
                                },
                            },
                        },
                    },
                },
                components: {
                    examples: {
                        SharedWidget: { summary: "Shared widget example", value: { id: 3 } },
                    },
                },
            },
            "get-widgets",
        )!;

        expect(op.responses[0].examples).toEqual([{ name: "shared", summary: "Shared widget example", value: { id: 3 } }]);
    });

    it("prefers a non-empty `examples` map over `example` when both are present", () => {
        const op = parseOperation(
            {
                openapi: "3.0.0",
                info: { title: "Examples API", version: "1.0.0", description: null },
                paths: {
                    "/widgets": {
                        get: {
                            operationId: "getWidgets",
                            responses: {
                                "200": {
                                    description: "OK",
                                    content: {
                                        "application/json": {
                                            schema: { type: "object" },
                                            example: { id: 999 },
                                            examples: {
                                                named: { value: { id: 1 } },
                                            },
                                        },
                                    },
                                },
                            },
                        },
                    },
                },
            },
            "get-widgets",
        )!;

        expect(op.responses[0].examples).toEqual([{ name: "named", summary: null, value: { id: 1 } }]);
    });

    it("returns an empty array when neither `example` nor `examples` is present", () => {
        const op = parseOperation(spec, "get-users-id")!;

        expect(op.requests[0].examples).toEqual([]);
        expect(op.responses[0].examples).toEqual([]);
    });
});

describe("buildNavigation servers", () => {
    it("extracts servers with url and description", () => {
        const withServers = {
            ...spec,
            servers: [
                { url: "https://api.example.com", description: "Production" },
                { url: "https://staging.example.com" },
            ],
        };

        const nav = buildNavigation(withServers);

        expect(nav.servers).toEqual([
            { url: "https://api.example.com", description: "Production" },
            { url: "https://staging.example.com", description: null },
        ]);
    });

    it("returns an empty array when servers is absent", () => {
        const nav = buildNavigation(spec);

        expect(nav.servers).toEqual([]);
    });
});

describe("filterNavigationByTags", () => {
    it("keeps only groups whose title is in the tag set, pruning summaries to the kept operation ids", () => {
        const nav = buildNavigation(spec);
        const filtered = filterNavigationByTags(nav, ["Users", "Pets"]);

        expect(filtered.groups.map((g) => g.title)).toEqual(["Users", "Pets"]);
        expect(Object.keys(filtered.summaries).sort()).toEqual(["get-pets", "get-users-id"]);
    });

    it("leaves info and servers intact", () => {
        const withServers = { ...spec, servers: [{ url: "https://api.example.com" }] };
        const nav = buildNavigation(withServers);
        const filtered = filterNavigationByTags(nav, ["Users"]);

        expect(filtered.info).toEqual(nav.info);
        expect(filtered.servers).toEqual(nav.servers);
    });

    it("returns no groups and no summaries when no tag matches", () => {
        const nav = buildNavigation(spec);
        const filtered = filterNavigationByTags(nav, ["DoesNotExist"]);

        expect(filtered.groups).toEqual([]);
        expect(filtered.summaries).toEqual({});
    });

    it("returns no groups when the tag list is empty", () => {
        const nav = buildNavigation(spec);
        const filtered = filterNavigationByTags(nav, []);

        expect(filtered.groups).toEqual([]);
        expect(filtered.summaries).toEqual({});
    });
});

describe("effective security resolution", () => {
    const securitySpec = {
        openapi: "3.0.0",
        info: { title: "Security API", version: "1.0.0", description: null },
        security: [{ http: [] }],
        paths: {
            "/inherited": {
                get: {
                    operationId: "getInherited",
                    responses: { "200": { description: "OK" } },
                },
            },
            "/public": {
                get: {
                    operationId: "getPublic",
                    security: [],
                    responses: { "200": { description: "OK" } },
                },
            },
            "/override": {
                get: {
                    operationId: "getOverride",
                    security: [{ oauth2: ["read", "write"] }],
                    responses: { "200": { description: "OK" } },
                },
            },
            "/optional": {
                get: {
                    operationId: "getOptional",
                    security: [{}],
                    responses: { "200": { description: "OK" } },
                },
            },
        },
    };

    it("inherits the top-level security when the operation omits it", () => {
        const op = parseOperation(securitySpec, "get-inherited")!;

        expect(op.security).toEqual([{ schemes: [{ name: "http", scopes: [] }] }]);
    });

    it("treats an explicit empty security array as public, overriding the top-level default", () => {
        const op = parseOperation(securitySpec, "get-public")!;

        expect(op.security).toEqual([]);
    });

    it("uses the operation's own security requirements when present, with scopes carried through", () => {
        const op = parseOperation(securitySpec, "get-override")!;

        expect(op.security).toEqual([{ schemes: [{ name: "oauth2", scopes: ["read", "write"] }] }]);
    });

    it("resolves an empty requirement object to a requirement with no schemes", () => {
        const op = parseOperation(securitySpec, "get-optional")!;

        expect(op.security).toEqual([{ schemes: [] }]);
    });
});