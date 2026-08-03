import { useMemo, useState } from "react";
import { Badge, CodeBlock, NativeSelect, SegmentedPills } from "@lattice-php/lattice/ui";
import { jsonWithLineNumbers } from "./code-block-languages";
import type { Option } from "@lattice-php/lattice/core/types";
import { SchemaView } from "../schema/SchemaView";
import { OperationHeader } from "./OperationHeader";
import { parameterAllowedValues, parameterTypeLabel } from "./parameter-schema";
import { parseOperation } from "./parse";
import { RequestPlayground } from "./RequestPlayground";
import type { Contract, ContractExample, Param, ParamGroup, SecurityRequirement, SecuritySchemeRef } from "./types";

type OperationViewProps = {
    spec: unknown;
    operationId: string | null;
    baseUrl?: string | null;
    token?: string | null;
    expandDepth?: number;
};

type SecuritySchemeDefinition = {
    type?: string;
    scheme?: string;
    bearerFormat?: string;
    in?: string;
    name?: string;
    description?: string | null;
};

function contractLabel(contract: Contract): string {
    const parts = [contract.status, contract.mediaType].filter((part): part is string => Boolean(part));

    return parts.length > 0 ? parts.join(" ") : "default";
}

function ParamRow({ param }: { param: Param }): React.ReactNode {
    const allowedValues = parameterAllowedValues(param.schema);

    return (
        <li className="border-b border-lt-border py-2 last:border-b-0">
            <div className="flex items-center gap-2">
                <span className="font-mono text-sm text-lt-fg">{param.name}</span>
                <span className="rounded-lt-xs bg-lt-muted px-1.5 py-0.5 text-xs text-lt-muted-fg">
                    {parameterTypeLabel(param.schema)}
                </span>
                {param.required ? <span className="text-lt-danger">*</span> : null}
                {param.deprecated ? <Badge color="danger">deprecated</Badge> : null}
            </div>
            {param.description ? <p className="mt-0.5 text-xs text-lt-muted-fg">{param.description}</p> : null}
            {allowedValues.length > 0 ? (
                <p className="mt-0.5 text-xs text-lt-muted-fg">
                    Available values: {allowedValues.join(", ")}
                </p>
            ) : null}
        </li>
    );
}

function ParamGroupSection({ group }: { group: ParamGroup }): React.ReactNode {
    return (
        <div className="mb-4">
            <h3 className="mb-1 text-xs font-semibold uppercase tracking-wide text-lt-muted-fg">
                {group.location} parameters
            </h3>
            <ul>
                {group.params.map((param) => (
                    <ParamRow key={`${param.location}-${param.name}`} param={param} />
                ))}
            </ul>
        </div>
    );
}

type SchemaTab = "schema" | "example";

const SCHEMA_TABS: Array<{ key: SchemaTab; label: string }> = [
    { key: "schema", label: "Schema" },
    { key: "example", label: "Example" },
];

function SchemaExampleView({
    name,
    schema,
    examples,
    components,
    noSchemaMessage,
    expandDepth,
    exampleLabel,
}: {
    name: string;
    schema: unknown;
    examples: ContractExample[];
    components: unknown;
    noSchemaMessage: string;
    expandDepth: number;
    exampleLabel: string;
}): React.ReactNode {
    const [tab, setTab] = useState<SchemaTab>("schema");
    const [selected, setSelected] = useState(0);

    if (examples.length === 0) {
        return <SchemaView schema={schema} components={components} expandDepth={expandDepth} />;
    }

    const current = examples[selected] ?? examples[0];

    return (
        <div>
            <div className="mb-2 pb-2">
                <SegmentedPills
                    name={name}
                    ariaLabel="Schema or example"
                    options={SCHEMA_TABS.map(({ key, label }) => ({ label, value: key, data: null }))}
                    value={tab}
                    onSelect={(value) => setTab(value as SchemaTab)}
                />
            </div>
            {tab === "schema" ? (
                schema ? (
                    <SchemaView schema={schema} components={components} expandDepth={expandDepth} />
                ) : (
                    <p className="text-sm text-lt-muted-fg">{noSchemaMessage}</p>
                )
            ) : (
                <div>
                    {examples.length > 1 ? (
                        <NativeSelect
                            value={selected}
                            onChange={(event) => setSelected(Number(event.target.value))}
                            className="mb-2"
                        >
                            {examples.map((example, index) => (
                                <option key={example.name ?? index} value={index}>
                                    {example.name ?? `Example ${index + 1}`}
                                    {example.summary ? ` — ${example.summary}` : ""}
                                </option>
                            ))}
                        </NativeSelect>
                    ) : current?.summary ? (
                        <p className="mb-1 text-xs text-lt-muted-fg">{current.summary}</p>
                    ) : null}
                    {current?.description ? (
                        <p className="mb-1 text-xs text-lt-muted-fg">{current.description}</p>
                    ) : null}
                    {current?.externalValue ? (
                        <a
                            href={current.externalValue}
                            target="_blank"
                            rel="noreferrer"
                            className="mb-2 block text-xs text-lt-primary underline underline-offset-2"
                        >
                            Open external example
                        </a>
                    ) : null}
                    {current?.value !== undefined ? (
                        <CodeBlock aria-label={exampleLabel} copyable language={jsonWithLineNumbers}>
                            {JSON.stringify(current.value, null, 2)}
                        </CodeBlock>
                    ) : null}
                </div>
            )}
        </div>
    );
}

function RequestBodySection({
    requests,
    components,
    expandDepth,
}: {
    requests: Contract[];
    components: unknown;
    expandDepth: number;
}): React.ReactNode {
    if (requests.length === 0) return null;

    return (
        <section className="mb-6">
            <h2 className="mb-2 text-sm font-semibold text-lt-fg">Request body</h2>
            {requests.map((request, index) => (
                <div key={`${request.mediaType ?? "none"}-${index}`} className="mb-4">
                    <p className="mb-1 font-mono text-xs text-lt-muted-fg">
                        {request.mediaType ?? "unspecified media type"}
                        {request.title ? ` — ${request.title}` : ""}
                    </p>
                    {request.schema || request.examples.length > 0 ? (
                        <SchemaExampleView
                            name={`request-${request.mediaType ?? "none"}-${index}-tab`}
                            schema={request.schema}
                            examples={request.examples}
                            components={components}
                            noSchemaMessage="No request body schema."
                            expandDepth={expandDepth}
                            exampleLabel="Request body example"
                        />
                    ) : (
                        <p className="text-sm text-lt-muted-fg">No request body schema.</p>
                    )}
                </div>
            ))}
        </section>
    );
}

function ResponsesSection({
    responses,
    components,
    expandDepth,
}: {
    responses: Contract[];
    components: unknown;
    expandDepth: number;
}): React.ReactNode {
    const [activeLabel, setActiveLabel] = useState<string | null>(null);

    if (responses.length === 0) return null;

    const current = responses.find((response) => contractLabel(response) === activeLabel) ?? responses[0];
    const options: Option[] = responses.map((response) => ({
        label: contractLabel(response),
        value: contractLabel(response),
        data: null,
    }));

    return (
        <section>
            <h2 className="mb-2 text-sm font-semibold text-lt-fg">Responses</h2>
            <div className="mb-3 pb-2">
                <SegmentedPills
                    name="response-status"
                    ariaLabel="Response status"
                    options={options}
                    value={activeLabel ?? options[0]?.value ?? ""}
                    onSelect={setActiveLabel}
                />
            </div>
            {current ? (
                <div>
                    {current.title ? <p className="mb-2 text-sm text-lt-muted-fg">{current.title}</p> : null}
                    {current.headers.length > 0 ? (
                        <div className="mb-4">
                            <h3 className="mb-1 text-xs font-semibold uppercase tracking-wide text-lt-muted-fg">
                                Response headers
                            </h3>
                            <ul>
                                {current.headers.map((header) => (
                                    <ParamRow key={header.name} param={header} />
                                ))}
                            </ul>
                        </div>
                    ) : null}
                    {current.schema || current.examples.length > 0 ? (
                        <SchemaExampleView
                            key={contractLabel(current)}
                            name={`response-${contractLabel(current)}-tab`}
                            schema={current.schema}
                            examples={current.examples}
                            components={components}
                            noSchemaMessage="No response body."
                            expandDepth={expandDepth}
                            exampleLabel="Response example"
                        />
                    ) : (
                        <p className="text-sm text-lt-muted-fg">No response body.</p>
                    )}
                </div>
            ) : null}
        </section>
    );
}

function securitySchemeLabel(name: string, definition: SecuritySchemeDefinition | null): string {
    if (!definition) return name;

    if (definition.type === "http" && definition.scheme === "bearer") {
        return definition.bearerFormat ? `HTTP Bearer (${definition.bearerFormat})` : "HTTP Bearer";
    }
    if (definition.type === "http" && definition.scheme === "basic") {
        return "HTTP Basic";
    }
    if (definition.type === "apiKey") {
        return `API key (${definition.in}: ${definition.name})`;
    }
    if (definition.type === "oauth2") {
        return "OAuth 2.0";
    }
    if (definition.type === "openIdConnect") {
        return "OpenID Connect";
    }

    return name;
}

function SecuritySchemeRow({ scheme, components }: { scheme: SecuritySchemeRef; components: unknown }): React.ReactNode {
    const definitions = (components as { securitySchemes?: Record<string, SecuritySchemeDefinition> } | null)?.securitySchemes ?? {};
    const definition = definitions[scheme.name] ?? null;

    return (
        <li className="border-b border-lt-border py-2 last:border-b-0">
            <span className="text-sm text-lt-fg">{securitySchemeLabel(scheme.name, definition)}</span>
            {definition?.description ? <p className="mt-0.5 text-xs text-lt-muted-fg">{definition.description}</p> : null}
            {scheme.scopes.length > 0 ? (
                <div className="mt-1 flex flex-wrap gap-1">
                    {scheme.scopes.map((scope) => (
                        <code key={scope} className="rounded-lt-xs bg-lt-muted px-1.5 py-0.5 text-xs text-lt-muted-fg">
                            {scope}
                        </code>
                    ))}
                </div>
            ) : null}
        </li>
    );
}

function SecurityRequirementRow({ requirement, components }: { requirement: SecurityRequirement; components: unknown }): React.ReactNode {
    if (requirement.schemes.length === 0) {
        return <p className="text-sm text-lt-muted-fg">Optional authentication</p>;
    }

    return (
        <ul>
            {requirement.schemes.map((scheme) => (
                <SecuritySchemeRow key={scheme.name} scheme={scheme} components={components} />
            ))}
        </ul>
    );
}

function SecuritySection({ security, components }: { security: SecurityRequirement[]; components: unknown }): React.ReactNode {
    if (security.length === 0) return null;

    return (
        <section className="mb-6">
            <h2 className="mb-2 text-sm font-semibold text-lt-fg">Authorization</h2>
            {security.map((requirement, index) => (
                <div key={index}>
                    {index > 0 ? (
                        <p className="my-2 text-xs font-semibold uppercase tracking-wide text-lt-muted-fg">OR</p>
                    ) : null}
                    <SecurityRequirementRow requirement={requirement} components={components} />
                </div>
            ))}
        </section>
    );
}

export function OperationView({ spec, operationId, baseUrl, token, expandDepth = 0 }: OperationViewProps): React.ReactNode {
    const operation = useMemo(
        () => (operationId ? parseOperation(spec, operationId, baseUrl ?? null) : null),
        [spec, operationId, baseUrl],
    );
    const components = (spec as { components?: unknown } | null)?.components ?? null;

    if (!operationId) {
        return <div className="flex-1 p-6 text-sm text-lt-muted-fg">Select an operation to view its details.</div>;
    }

    if (!operation) {
        return (
            <div className="flex-1 p-6 text-sm text-lt-danger">
                Operation &quot;{operationId}&quot; could not be found in this specification.
            </div>
        );
    }

    return (
        <div className="min-w-0 flex-1 overflow-y-auto p-6">
            <OperationHeader operation={operation} baseUrl={operation.serverUrl} components={components} />

            <SecuritySection security={operation.security} components={components} />

            {operation.paramGroups.length > 0 ? (
                <section className="mb-6">
                    <h2 className="mb-2 text-sm font-semibold text-lt-fg">Parameters</h2>
                    {operation.paramGroups.map((group) => (
                        <ParamGroupSection key={group.location} group={group} />
                    ))}
                </section>
            ) : null}

            <RequestBodySection requests={operation.requests} components={components} expandDepth={expandDepth} />
            <RequestPlayground
                operation={operation}
                baseUrl={operation.serverUrl}
                token={token ?? null}
                components={components}
            />
            <ResponsesSection responses={operation.responses} components={components} expandDepth={expandDepth} />
        </div>
    );
}

export default OperationView;
