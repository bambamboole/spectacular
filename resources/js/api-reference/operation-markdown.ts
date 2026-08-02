import { initialContractExample } from "./schema-example";
import type { Contract, ContractExample, Operation, Param, SecurityRequirement } from "./types";

const SCHEMA_REF_PREFIX = "#/components/schemas/";

const GENERATED_SCHEMA_REF_PREFIX = "spectacular-internal://schema/";

export function operationToMarkdown(operation: Operation, components?: unknown): string {
    const sections = [
        [`# ${operation.summary.title}`, `\`${operation.summary.method} ${operation.summary.path}\``, operation.description]
            .filter((section): section is string => Boolean(section))
            .join("\n\n"),
        securitySection(operation.security),
        parametersSection(operation.paramGroups.flatMap((group) => group.params)),
        requestSection(operation.requests, components),
        responsesSection(operation.responses, components),
    ].filter((section): section is string => Boolean(section));

    return sections.join("\n\n");
}

function securitySection(security: SecurityRequirement[]): string | null {
    if (security.length === 0) {
        return null;
    }

    const groups = security.map((requirement) => securityRequirementLabel(requirement));

    return ["## Authorization", groups.map((group, index) => (index === 0 ? `- ${group}` : `- OR\n- ${group}`)).join("\n")].join("\n\n");
}

function securityRequirementLabel(requirement: SecurityRequirement): string {
    if (requirement.schemes.length === 0) {
        return "optional authentication";
    }

    return requirement.schemes
        .map((scheme) => (scheme.scopes.length > 0 ? `${scheme.name} (${scheme.scopes.join(", ")})` : scheme.name))
        .join(" + ");
}

function parametersSection(parameters: Param[]): string | null {
    if (parameters.length === 0) {
        return null;
    }

    return ["## Parameters", parameterTable(parameters)].join("\n\n");
}

function parameterTable(parameters: Param[]): string {
    return [
        "| Name | In | Type | Required | Description |",
        "| --- | --- | --- | --- | --- |",
        ...parameters.map(
            (parameter) =>
                `| ${tableCell(parameter.name)} | ${tableCell(parameter.location)} | ${tableCell(typeLabel(parameter.schema))} | ${parameter.required ? "yes" : "no"} | ${tableCell(parameter.description)} |`,
        ),
    ].join("\n");
}

function requestSection(requests: Contract[], components: unknown): string | null {
    if (requests.length === 0) {
        return null;
    }

    return ["## Request body", ...requests.map((contract) => requestContractSection(contract, components))].join("\n\n");
}

function requestContractSection(contract: Contract, components: unknown): string {
    return [
        contract.mediaType ? `**Content-Type:** \`${contract.mediaType}\`` : "**Content-Type:** unspecified",
        contract.title,
        contractSections(contract, components, 3),
    ]
        .filter((section): section is string => Boolean(section))
        .join("\n\n");
}

function responsesSection(responses: Contract[], components: unknown): string | null {
    if (responses.length === 0) {
        return null;
    }

    return ["## Responses", ...responses.map((contract) => responseContractSection(contract, components))].join("\n\n");
}

function responseContractSection(contract: Contract, components: unknown): string {
    return [
        `### ${contractLabel(contract)}`,
        contract.title,
        contract.headers.length > 0 ? ["#### Headers", parameterTable(contract.headers)].join("\n\n") : null,
        contractSections(contract, components, 4),
    ]
        .filter((section): section is string => Boolean(section))
        .join("\n\n");
}

function contractLabel(contract: Contract): string {
    return [contract.status, contract.mediaType].filter((part): part is string => Boolean(part)).join(" ") || "default";
}

function contractSections(contract: Contract, components: unknown, headingLevel: number): string | null {
    const sections: Array<string | null> = [];

    if (contract.schema !== null) {
        sections.push(
            [`${"#".repeat(headingLevel)} Schema`, jsonFence(schemaForMarkdown(contract.schema, components))]
                .filter((section): section is string => Boolean(section))
                .join("\n\n"),
        );
    }

    const examples = contract.examples.length > 0
        ? contract.examples
        : contract.schema === null
          ? []
          : [{ name: null, summary: null, value: initialContractExample(contract, components) }];

    sections.push(...examples.map((example) => exampleSection(example, headingLevel)));

    const rendered = sections.filter((section): section is string => Boolean(section));

    return rendered.length === 0 ? null : rendered.join("\n\n");
}

function exampleSection(example: ContractExample, headingLevel: number): string {
    const label = example.name ? `Example: ${example.name}` : "Example";

    return [
        `${"#".repeat(headingLevel)} ${label}`,
        example.summary,
        jsonFence(example.value),
    ]
        .filter((section): section is string => Boolean(section))
        .join("\n\n");
}

function jsonFence(value: unknown): string | null {
    const json = JSON.stringify(value, null, 2);

    return json === undefined ? null : `\`\`\`json\n${json}\n\`\`\``;
}

type SchemaResolutionContext = {
    components: unknown;
    definitions: Record<string, unknown>;
    definitionsInProgress: Set<string>;
};

function schemaForMarkdown(schema: unknown, components: unknown): unknown {
    const context: SchemaResolutionContext = {
        components,
        definitions: {},
        definitionsInProgress: new Set(),
    };
    const resolved = resolveLocalSchemaRefs(schema, context, new Set());

    if (Object.keys(context.definitions).length === 0 || !isRecord(resolved)) {
        return resolved;
    }

    const existingDefinitions = isRecord(resolved.$defs) ? resolved.$defs : {};
    const usedNames = new Set(Object.keys(existingDefinitions));
    const definitionNames = new Map<string, string>();

    for (const name of Object.keys(context.definitions)) {
        const definitionName = availableDefinitionName(name, usedNames);
        usedNames.add(definitionName);
        definitionNames.set(generatedDefinitionRefForName(name), definitionName);
    }

    const resolvedSchema = withoutSchemaIds(replaceGeneratedDefinitionRefs(resolved, definitionNames));
    const definitions = Object.fromEntries(
        Object.entries(context.definitions).map(([name, definition]) => [
            definitionNames.get(generatedDefinitionRefForName(name)) ?? name,
            withoutSchemaIds(replaceGeneratedDefinitionRefs(definition, definitionNames)),
        ]),
    );

    return isRecord(resolvedSchema)
        ? { ...resolvedSchema, $defs: { ...existingDefinitions, ...definitions } }
        : resolvedSchema;
}

function resolveLocalSchemaRefs(
    schema: unknown,
    context: SchemaResolutionContext,
    visitedRefs: Set<string>,
): unknown {
    if (Array.isArray(schema)) {
        return schema.map((value) => resolveLocalSchemaRefs(value, context, visitedRefs));
    }

    if (!isRecord(schema)) {
        return schema;
    }

    const ref = typeof schema.$ref === "string" && schema.$ref.startsWith(SCHEMA_REF_PREFIX)
        ? schema.$ref
        : null;

    if (ref !== null && !visitedRefs.has(ref)) {
        const referencedSchema = componentSchema(ref, context.components);

        if (referencedSchema !== null) {
            visitedRefs.add(ref);
            const resolved = resolveLocalSchemaRefs(referencedSchema, context, visitedRefs);
            const siblings = Object.fromEntries(
                Object.entries(schema)
                    .filter(([key]) => key !== "$ref")
                    .map(([key, value]) => [key, resolveLocalSchemaRefs(value, context, visitedRefs)]),
            );
            visitedRefs.delete(ref);

            return isRecord(resolved) ? { ...resolved, ...siblings } : resolved;
        }
    }

    if (ref !== null && addSchemaDefinition(ref, context)) {
        const siblings = Object.fromEntries(
            Object.entries(schema)
                .filter(([key]) => key !== "$ref")
                .map(([key, value]) => [key, resolveLocalSchemaRefs(value, context, visitedRefs)]),
        );

        return { $ref: generatedDefinitionRef(ref), ...siblings };
    }

    return Object.fromEntries(
        Object.entries(schema).map(([key, value]) => [key, resolveLocalSchemaRefs(value, context, visitedRefs)]),
    );
}

function addSchemaDefinition(ref: string, context: SchemaResolutionContext): boolean {
    const name = componentName(ref);
    const referencedSchema = componentSchema(ref, context.components);

    if (name === null || referencedSchema === null) return false;
    if (name in context.definitions || context.definitionsInProgress.has(name)) return true;

    context.definitionsInProgress.add(name);
    context.definitions[name] = rewriteDefinitionRefs(referencedSchema, context);
    context.definitionsInProgress.delete(name);

    return true;
}

function rewriteDefinitionRefs(schema: unknown, context: SchemaResolutionContext): unknown {
    if (Array.isArray(schema)) {
        return schema.map((value) => rewriteDefinitionRefs(value, context));
    }

    if (!isRecord(schema)) {
        return schema;
    }

    const ref = typeof schema.$ref === "string" && schema.$ref.startsWith(SCHEMA_REF_PREFIX)
        ? schema.$ref
        : null;

    if (ref !== null && addSchemaDefinition(ref, context)) {
        return Object.fromEntries(
            Object.entries(schema).map(([key, value]) => [
                key,
                key === "$ref" ? generatedDefinitionRef(ref) : rewriteDefinitionRefs(value, context),
            ]),
        );
    }

    return Object.fromEntries(
        Object.entries(schema).map(([key, value]) => [key, rewriteDefinitionRefs(value, context)]),
    );
}

function generatedDefinitionRef(ref: string): string {
    return generatedDefinitionRefForName(ref.slice(SCHEMA_REF_PREFIX.length));
}

function generatedDefinitionRefForName(name: string): string {
    return `${GENERATED_SCHEMA_REF_PREFIX}${encodeURIComponent(name)}`;
}

function availableDefinitionName(name: string, usedNames: Set<string>): string {
    if (!usedNames.has(name)) return name;

    const baseName = `${name}Component`;
    let candidate = baseName;
    let suffix = 2;

    while (usedNames.has(candidate)) {
        candidate = `${baseName}${suffix}`;
        suffix += 1;
    }

    return candidate;
}

function replaceGeneratedDefinitionRefs(value: unknown, definitionNames: Map<string, string>): unknown {
    if (typeof value === "string") {
        const definitionName = definitionNames.get(value);

        return definitionName === undefined ? value : `#/$defs/${definitionName}`;
    }

    if (Array.isArray(value)) {
        return value.map((item) => replaceGeneratedDefinitionRefs(item, definitionNames));
    }

    if (!isRecord(value)) {
        return value;
    }

    return Object.fromEntries(
        Object.entries(value).map(([key, item]) => [key, replaceGeneratedDefinitionRefs(item, definitionNames)]),
    );
}

function withoutSchemaIds(value: unknown): unknown {
    if (Array.isArray(value)) {
        return value.map(withoutSchemaIds);
    }

    if (!isRecord(value)) {
        return value;
    }

    return Object.fromEntries(
        Object.entries(value)
            .filter(([key]) => key !== "$id")
            .map(([key, item]) => [key, withoutSchemaIds(item)]),
    );
}

function componentName(ref: string): string | null {
    const name = ref.slice(SCHEMA_REF_PREFIX.length);

    return name === "" ? null : name;
}

function componentSchema(ref: string, components: unknown): unknown | null {
    if (!isRecord(components) || !isRecord(components.schemas)) {
        return null;
    }

    const name = componentName(ref);

    return name === null || !(name in components.schemas) ? null : components.schemas[name];
}

function typeLabel(schema: unknown): string {
    if (schema === null || typeof schema !== "object") {
        return "any";
    }

    const node = schema as Record<string, unknown>;

    if (typeof node.$ref === "string") {
        return node.$ref.split("/").pop() ?? "ref";
    }
    if (Array.isArray(node.type)) {
        return node.type.join(" | ");
    }
    if (typeof node.type === "string") {
        return node.type === "array" && node.items ? `${typeLabel(node.items)}[]` : node.type;
    }
    if (Array.isArray(node.enum)) {
        return "enum";
    }

    return "any";
}

function tableCell(value: string | null): string {
    return (value ?? "").replaceAll("|", "\\|").replaceAll(/\r?\n/g, "<br>");
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === "object" && value !== null && !Array.isArray(value);
}
