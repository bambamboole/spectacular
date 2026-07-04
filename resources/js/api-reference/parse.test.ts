import { describe, expect, it } from "vitest";
import { buildNavigation, parseOperation } from "./parse";

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
            },
            {
                role: "response",
                status: "404",
                mediaType: "application/json",
                schema: { type: "object", properties: { message: { type: "string" } } },
                title: "Not found",
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
            },
        ]);
    });

});