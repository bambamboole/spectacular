import { initialContractExample } from "./schema-example";
import type { Contract, Operation, Param, SecurityRequirement } from "./types";

export function operationToMarkdown(operation: Operation): string {
    const sections = [
        [`# ${operation.summary.title}`, `\`${operation.summary.method} ${operation.summary.path}\``, operation.description]
            .filter((section): section is string => Boolean(section))
            .join("\n\n"),
        securitySection(operation.security),
        parametersSection(operation.paramGroups.flatMap((group) => group.params)),
        requestSection(operation.requests),
        responsesSection(operation.responses),
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

function requestSection(requests: Contract[]): string | null {
    if (requests.length === 0) {
        return null;
    }

    return ["## Request body", ...requests.map(requestContractSection)].join("\n\n");
}

function requestContractSection(contract: Contract): string {
    return [
        contract.mediaType ? `**Content-Type:** \`${contract.mediaType}\`` : "**Content-Type:** unspecified",
        contract.title,
        jsonFence(contract),
    ]
        .filter((section): section is string => Boolean(section))
        .join("\n\n");
}

function responsesSection(responses: Contract[]): string | null {
    if (responses.length === 0) {
        return null;
    }

    return ["## Responses", ...responses.map(responseContractSection)].join("\n\n");
}

function responseContractSection(contract: Contract): string {
    return [
        `### ${contractLabel(contract)}`,
        contract.title,
        contract.headers.length > 0 ? ["#### Headers", parameterTable(contract.headers)].join("\n\n") : null,
        jsonFence(contract),
    ]
        .filter((section): section is string => Boolean(section))
        .join("\n\n");
}

function contractLabel(contract: Contract): string {
    return [contract.status, contract.mediaType].filter((part): part is string => Boolean(part)).join(" ") || "default";
}

function jsonFence(contract: Contract): string | null {
    if (contract.examples.length === 0 && contract.schema === null) {
        return null;
    }

    const json = JSON.stringify(initialContractExample(contract), null, 2);

    return json === undefined ? null : `\`\`\`json\n${json}\n\`\`\``;
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
