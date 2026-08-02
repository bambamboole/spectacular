import { describe, expect, it } from "vitest";
import { operationToMarkdown } from "./operation-markdown";
import type { Operation } from "./types";

const operation: Operation = {
    summary: {
        id: "create-widget",
        method: "POST",
        path: "/widgets/{widget}",
        title: "Create widget",
        deprecated: false,
    },
    description: "Creates a widget.",
    tags: [],
    security: [
        {
            schemes: [
                { name: "bearer", scopes: ["widgets:write"] },
                { name: "service-key", scopes: [] },
            ],
        },
        { schemes: [{ name: "signed-request", scopes: [] }] },
    ],
    paramGroups: [
        {
            location: "path",
            params: [
                {
                    name: "widget",
                    location: "path",
                    required: true,
                    deprecated: false,
                    description: "Widget identifier",
                    schema: { type: "string" },
                    example: null,
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
                    schema: { type: "array", items: { type: "string" } },
                    example: null,
                },
            ],
        },
    ],
    requests: [
        {
            role: "request",
            status: null,
            mediaType: "application/json",
            schema: { type: "object", properties: { name: { type: "string", example: "Widget" } } },
            title: null,
            examples: [],
            headers: [],
            required: true,
        },
    ],
    responses: [
        {
            role: "response",
            status: "201",
            mediaType: "application/json",
            schema: { type: "object", properties: { id: { type: "string", example: "widget_123" } } },
            title: "Widget created",
            examples: [],
            headers: [
                {
                    name: "X-Request-Id",
                    location: "header",
                    required: false,
                    deprecated: false,
                    description: "Correlates the request",
                    schema: { type: "string" },
                    example: null,
                },
            ],
            required: false,
        },
        {
            role: "response",
            status: "422",
            mediaType: "application/json",
            schema: null,
            title: "Validation failed",
            examples: [{ name: null, summary: null, value: { message: "Invalid widget" } }],
            headers: [],
            required: false,
        },
    ],
};

describe("operationToMarkdown", () => {
    it("serializes documented operation details as Markdown", () => {
        expect(operationToMarkdown(operation)).toBe(`# Create widget

\`POST /widgets/{widget}\`

Creates a widget.

## Authorization

- bearer (widgets:write) + service-key
- OR
- signed-request

## Parameters

| Name | In | Type | Required | Description |
| --- | --- | --- | --- | --- |
| widget | path | string | yes | Widget identifier |
| include | query | string[] | no |  |

## Request body

**Content-Type:** \`application/json\`

\`\`\`json
{
  "name": "Widget"
}
\`\`\`

## Responses

### 201 application/json

Widget created

#### Headers

| Name | In | Type | Required | Description |
| --- | --- | --- | --- | --- |
| X-Request-Id | header | string | no | Correlates the request |

\`\`\`json
{
  "id": "widget_123"
}
\`\`\`

### 422 application/json

Validation failed

\`\`\`json
{
  "message": "Invalid widget"
}
\`\`\``);
    });

    it("escapes parameter table cells and omits absent sections", () => {
        expect(
            operationToMarkdown({
                ...operation,
                description: null,
                security: [],
                paramGroups: [
                    {
                        location: "query",
                        params: [
                            {
                                ...operation.paramGroups[1]!.params[0]!,
                                name: "filter|status",
                                description: "One\ntwo|three",
                            },
                        ],
                    },
                ],
                requests: [],
                responses: [],
            }),
        ).toBe(`# Create widget

\`POST /widgets/{widget}\`

## Parameters

| Name | In | Type | Required | Description |
| --- | --- | --- | --- | --- |
| filter\\|status | query | string[] | no | One<br>two\\|three |`);
    });
});
