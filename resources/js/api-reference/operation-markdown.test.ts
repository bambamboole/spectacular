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
    serverUrl: "https://api.example.test",
    servers: [{ url: "https://api.example.test", description: null }],
    usesRootServers: true,
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
                    schema: { type: "array", items: { type: "string", enum: ["roles", "rolesCount"] } },
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
| include | query | string[] | no | Available values: \`roles\`, \`rolesCount\` |

## Request body

**Content-Type:** \`application/json\`

### Schema

\`\`\`json
{
  "type": "object",
  "properties": {
    "name": {
      "type": "string",
      "example": "Widget"
    }
  }
}
\`\`\`

### Example

\`\`\`json
{}
\`\`\`

## Responses

### 201 application/json

Widget created

#### Headers

| Name | In | Type | Required | Description |
| --- | --- | --- | --- | --- |
| X-Request-Id | header | string | no | Correlates the request |

#### Schema

\`\`\`json
{
  "type": "object",
  "properties": {
    "id": {
      "type": "string",
      "example": "widget_123"
    }
  }
}
\`\`\`

#### Example

\`\`\`json
{
  "id": "widget_123"
}
\`\`\`

### 422 application/json

Validation failed

#### Example

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
| filter\\|status | query | string[] | no | One<br>two\\|three<br>Available values: \`roles\`, \`rolesCount\` |`);
    });

    it("serializes resolved schemas separately from named and derived examples", () => {
        const components = {
            schemas: {
                WidgetInput: {
                    type: "object",
                    required: ["name"],
                    properties: { name: { type: "string", minLength: 2, example: "Widget" } },
                },
                Widget: {
                    type: "object",
                    required: ["id"],
                    properties: { id: { type: "string", pattern: "^widget_", example: "widget_123" } },
                },
            },
        };
        const markdown = operationToMarkdown(
            {
                ...operation,
                requests: [
                    {
                        ...operation.requests[0]!,
                        schema: { $ref: "#/components/schemas/WidgetInput" },
                        examples: [{ name: "desk", summary: "A desk widget", value: { name: "Desk" } }],
                    },
                ],
                responses: [
                    {
                        ...operation.responses[0]!,
                        schema: { $ref: "#/components/schemas/Widget" },
                        examples: [],
                    },
                ],
            },
            components,
        );

        expect(markdown).toContain("### Schema");
        expect(markdown).toContain('"required": [\n    "name"\n  ]');
        expect(markdown).toContain('"minLength": 2');
        expect(markdown).toContain("### Example: desk");
        expect(markdown).toContain("A desk widget");
        expect(markdown).toContain('"name": "Desk"');
        expect(markdown).toContain("#### Schema");
        expect(markdown).toContain('"pattern": "^widget_"');
        expect(markdown).toContain("#### Example");
        expect(markdown).toContain('"id": "widget_123"');
        expect(markdown).not.toContain("#/components/schemas/");
    });

    it("serializes example descriptions and external values", () => {
        const markdown = operationToMarkdown({
            ...operation,
            requests: [],
            responses: [
                {
                    ...operation.responses[0]!,
                    schema: null,
                    examples: [
                        {
                            name: "large",
                            summary: "Large payload",
                            description: "Stored outside the specification.",
                            externalValue: "https://example.test/widgets.json",
                            value: undefined,
                        },
                    ],
                },
            ],
        });

        expect(markdown).toContain("Large payload");
        expect(markdown).toContain("Stored outside the specification.");
        expect(markdown).toContain("[Open external example](https://example.test/widgets.json)");
        expect(markdown).not.toContain("```json\nundefined");
    });

    it("derives minimal request examples while keeping response examples comprehensive", () => {
        const markdown = operationToMarkdown({
            ...operation,
            requests: [
                {
                    ...operation.requests[0]!,
                    schema: {
                        type: "object",
                        required: ["name"],
                        properties: {
                            name: { type: "string", example: "Widget" },
                            note: { type: "string", example: "Optional request note" },
                        },
                    },
                },
            ],
            responses: [
                {
                    ...operation.responses[0]!,
                    schema: {
                        type: "object",
                        properties: {
                            id: { type: "string", example: "widget_123" },
                            note: { type: "string", example: "Included response note" },
                        },
                    },
                },
            ],
        });

        expect(markdown).toContain('### Example\n\n```json\n{\n  "name": "Widget"\n}\n```');
        expect(markdown).toContain(
            '#### Example\n\n```json\n{\n  "id": "widget_123",\n  "note": "Included response note"\n}\n```',
        );
    });

    it("keeps direct recursive schemas finite and self-contained", () => {
        const markdown = operationToMarkdown(
            {
                ...operation,
                requests: [],
                responses: [
                    {
                        ...operation.responses[0]!,
                        schema: { $ref: "#/components/schemas/Node" },
                        examples: [],
                    },
                ],
            },
            {
                schemas: {
                    Node: {
                        type: "object",
                        properties: {
                            value: { type: "string", example: "root" },
                            child: { $ref: "#/components/schemas/Node" },
                        },
                    },
                },
            },
        );

        expect(markdown).toContain('"$ref": "#/$defs/Node"');
        expect(markdown).toContain('"$defs": {');
        expect(markdown).toContain('"Node": {');
        expect(markdown).not.toContain("#/components/schemas/");
        expect(markdown).toContain("#### Example");
    });

    it("keeps indirect recursive schemas finite and self-contained", () => {
        const markdown = operationToMarkdown(
            {
                ...operation,
                requests: [
                    {
                        ...operation.requests[0]!,
                        schema: { $ref: "#/components/schemas/Parent" },
                        examples: [],
                    },
                ],
                responses: [],
            },
            {
                schemas: {
                    Parent: {
                        $id: "https://schemas.example.test/parent",
                        type: "object",
                        properties: { child: { $ref: "#/components/schemas/Child" } },
                    },
                    Child: {
                        $id: "https://schemas.example.test/child",
                        type: "object",
                        properties: { parent: { $ref: "#/components/schemas/Parent" } },
                    },
                },
            },
        );

        expect(markdown).toContain('"$ref": "#/$defs/Parent"');
        expect(markdown).toContain('"$defs": {');
        expect(markdown).toContain('"Parent": {');
        expect(markdown).toContain('"Child": {');
        expect(markdown).not.toContain("#/components/schemas/");
        expect(markdown).not.toContain('"$id"');
        expect(markdown).toContain("### Example");
    });

    it("preserves existing definitions when packaging recursive component schemas", () => {
        const markdown = operationToMarkdown(
            {
                ...operation,
                requests: [
                    {
                        ...operation.requests[0]!,
                        schema: { $ref: "#/components/schemas/Node" },
                        examples: [],
                    },
                ],
                responses: [],
            },
            {
                schemas: {
                    Node: {
                        type: "object",
                        $defs: { Node: { type: "string", pattern: "^existing$" } },
                        properties: {
                            existing: { $ref: "#/$defs/Node" },
                            child: { $ref: "#/components/schemas/Node" },
                        },
                    },
                },
            },
        );

        expect(markdown).toContain('"Node": {\n      "type": "string",\n      "pattern": "^existing$"');
        expect(markdown).toContain('"NodeComponent": {');
        expect(markdown).toMatch(/"existing": \{\s+"\$ref": "#\/\$defs\/Node"\s+\}/);
        expect(markdown).toMatch(/"child": \{\s+"\$ref": "#\/\$defs\/NodeComponent"\s+\}/);
        expect(markdown).not.toContain("#/components/schemas/");
    });

    it("normalizes existing definitions that participate in component recursion", () => {
        const markdown = operationToMarkdown(
            {
                ...operation,
                requests: [
                    {
                        ...operation.requests[0]!,
                        schema: { $ref: "#/components/schemas/Node" },
                        examples: [],
                    },
                ],
                responses: [],
            },
            {
                schemas: {
                    Node: {
                        type: "object",
                        $defs: {
                            Node: { type: "string" },
                            Envelope: {
                                $id: "https://schemas.example.test/envelope",
                                type: "object",
                                properties: { node: { $ref: "#/components/schemas/Node" } },
                            },
                        },
                        properties: {
                            envelope: { $ref: "#/$defs/Envelope" },
                            child: { $ref: "#/components/schemas/Node" },
                        },
                    },
                },
            },
        );

        expect(markdown).toContain('"$ref": "#/$defs/Envelope"');
        expect(markdown).toContain('"$ref": "#/$defs/NodeComponent"');
        expect(markdown).not.toContain("spectacular-internal://schema/");
        expect(markdown).not.toContain("#/components/schemas/");
        expect(markdown).not.toContain('"$id"');
    });
});
