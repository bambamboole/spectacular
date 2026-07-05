import { useMemo, useState } from "react";
import { SchemaView } from "../schema/SchemaView";
import { parseOperation } from "./parse";
import type { Contract, ContractExample, Param, ParamGroup, SecurityRequirement, SecuritySchemeRef } from "./types";

type OperationViewProps = {
    spec: unknown;
    operationId: string | null;
    baseUrl?: string | null;
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

function paramTypeLabel(schema: unknown): string {
    if (schema === null || typeof schema !== "object") return "any";
    const node = schema as Record<string, unknown>;

    if (typeof node.$ref === "string") {
        return node.$ref.split("/").pop() ?? "ref";
    }
    if (Array.isArray(node.type)) {
        return node.type.join(" | ");
    }
    if (typeof node.type === "string") {
        return node.type === "array" && node.items ? `${paramTypeLabel(node.items)}[]` : node.type;
    }
    if (Array.isArray(node.enum)) {
        return "enum";
    }

    return "any";
}

function contractLabel(contract: Contract): string {
    const parts = [contract.status, contract.mediaType].filter((part): part is string => Boolean(part));

    return parts.length > 0 ? parts.join(" ") : "default";
}

function ParamRow({ param }: { param: Param }): React.ReactNode {
    return (
        <li className="border-b border-lt-border py-2 last:border-b-0">
            <div className="flex items-center gap-2">
                <span className="font-mono text-sm text-lt-fg">{param.name}</span>
                <span className="rounded-lt-xs bg-lt-muted px-1.5 py-0.5 text-xs text-lt-muted-fg">
                    {paramTypeLabel(param.schema)}
                </span>
                {param.required ? <span className="text-lt-danger">*</span> : null}
                {param.deprecated ? <span className="text-xs text-lt-muted-fg">deprecated</span> : null}
            </div>
            {param.description ? <p className="mt-0.5 text-xs text-lt-muted-fg">{param.description}</p> : null}
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
    schema,
    examples,
    components,
    noSchemaMessage,
    expandDepth,
}: {
    schema: unknown;
    examples: ContractExample[];
    components: unknown;
    noSchemaMessage: string;
    expandDepth: number;
}): React.ReactNode {
    const [tab, setTab] = useState<SchemaTab>("schema");
    const [selected, setSelected] = useState(0);

    if (examples.length === 0) {
        return <SchemaView schema={schema} components={components} expandDepth={expandDepth} />;
    }

    const current = examples[selected] ?? examples[0];

    return (
        <div>
            <div className="mb-2 flex flex-wrap gap-1 border-b border-lt-border pb-2">
                {SCHEMA_TABS.map(({ key, label }) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => setTab(key)}
                        aria-pressed={tab === key}
                        className={`rounded-lt-sm px-2 py-1 text-xs transition-colors ${
                            tab === key
                                ? "bg-lt-primary text-lt-primary-fg"
                                : "bg-lt-muted text-lt-muted-fg hover:bg-lt-accent hover:text-lt-accent-fg"
                        }`}
                    >
                        {label}
                    </button>
                ))}
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
                        <select
                            value={selected}
                            onChange={(event) => setSelected(Number(event.target.value))}
                            className="mb-2 rounded-lt-sm border border-lt-border bg-lt-muted px-2 py-1 text-xs text-lt-fg"
                        >
                            {examples.map((example, index) => (
                                <option key={example.name ?? index} value={index}>
                                    {example.name ?? `Example ${index + 1}`}
                                    {example.summary ? ` — ${example.summary}` : ""}
                                </option>
                            ))}
                        </select>
                    ) : current?.summary ? (
                        <p className="mb-1 text-xs text-lt-muted-fg">{current.summary}</p>
                    ) : null}
                    <pre className="overflow-x-auto rounded-lt-sm bg-lt-muted p-3 text-xs text-lt-fg">
                        {JSON.stringify(current?.value, null, 2)}
                    </pre>
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
                            schema={request.schema}
                            examples={request.examples}
                            components={components}
                            noSchemaMessage="No request body schema."
                            expandDepth={expandDepth}
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
    const [active, setActive] = useState(0);

    if (responses.length === 0) return null;

    const current = responses[active] ?? responses[0];

    return (
        <section>
            <h2 className="mb-2 text-sm font-semibold text-lt-fg">Responses</h2>
            <div className="mb-3 flex flex-wrap gap-1 border-b border-lt-border pb-2">
                {responses.map((response, index) => (
                    <button
                        key={`${response.status ?? "default"}-${response.mediaType ?? "none"}-${index}`}
                        type="button"
                        onClick={() => setActive(index)}
                        aria-pressed={index === active}
                        className={`rounded-lt-sm px-2 py-1 text-xs transition-colors ${
                            index === active
                                ? "bg-lt-primary text-lt-primary-fg"
                                : "bg-lt-muted text-lt-muted-fg hover:bg-lt-accent hover:text-lt-accent-fg"
                        }`}
                    >
                        {contractLabel(response)}
                    </button>
                ))}
            </div>
            {current ? (
                <div>
                    {current.title ? <p className="mb-2 text-sm text-lt-muted-fg">{current.title}</p> : null}
                    {current.schema || current.examples.length > 0 ? (
                        <SchemaExampleView
                            key={contractLabel(current)}
                            schema={current.schema}
                            examples={current.examples}
                            components={components}
                            noSchemaMessage="No response body."
                            expandDepth={expandDepth}
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

export function OperationView({ spec, operationId, baseUrl, expandDepth = 0 }: OperationViewProps): React.ReactNode {
    const operation = useMemo(
        () => (operationId ? parseOperation(spec, operationId) : null),
        [spec, operationId],
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
            <header className="mb-6">
                <div className="flex items-center gap-2">
                    <span className="rounded-lt-xs bg-lt-primary px-2 py-0.5 text-xs font-semibold uppercase text-lt-primary-fg">
                        {operation.summary.method}
                    </span>
                    <span className="font-mono text-sm">
                        {baseUrl ? <span className="text-lt-muted-fg">{baseUrl}</span> : null}
                        <span className="text-lt-muted-fg">{operation.summary.path}</span>
                    </span>
                    {operation.summary.deprecated ? (
                        <span className="rounded-lt-xs bg-lt-danger px-2 py-0.5 text-xs text-lt-danger-fg">
                            deprecated
                        </span>
                    ) : null}
                </div>
                <h1 className="mt-2 text-lg font-semibold text-lt-fg">{operation.summary.title}</h1>
                {operation.description ? (
                    <p className="mt-1 text-sm text-lt-muted-fg">{operation.description}</p>
                ) : null}
            </header>

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
            <ResponsesSection responses={operation.responses} components={components} expandDepth={expandDepth} />
        </div>
    );
}

export default OperationView;
