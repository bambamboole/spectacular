import { useMemo, useState } from "react";
import { SchemaView } from "../schema/SchemaView";
import { parseOperation } from "./parse";
import type { Contract, Param, ParamGroup } from "./types";

type OperationViewProps = {
    spec: unknown;
    operationId: string | null;
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

function RequestBodySection({ requests, components }: { requests: Contract[]; components: unknown }): React.ReactNode {
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
                    {request.schema ? (
                        <SchemaView schema={request.schema} components={components} />
                    ) : (
                        <p className="text-sm text-lt-muted-fg">No request body schema.</p>
                    )}
                </div>
            ))}
        </section>
    );
}

function ResponsesSection({ responses, components }: { responses: Contract[]; components: unknown }): React.ReactNode {
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
                    {current.schema ? (
                        <SchemaView schema={current.schema} components={components} />
                    ) : (
                        <p className="text-sm text-lt-muted-fg">No response body.</p>
                    )}
                </div>
            ) : null}
        </section>
    );
}

export function OperationView({ spec, operationId }: OperationViewProps): React.ReactNode {
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
                    <span className="font-mono text-sm text-lt-muted-fg">{operation.summary.path}</span>
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

            {operation.paramGroups.length > 0 ? (
                <section className="mb-6">
                    <h2 className="mb-2 text-sm font-semibold text-lt-fg">Parameters</h2>
                    {operation.paramGroups.map((group) => (
                        <ParamGroupSection key={group.location} group={group} />
                    ))}
                </section>
            ) : null}

            <RequestBodySection requests={operation.requests} components={components} />
            <ResponsesSection responses={operation.responses} components={components} />
        </div>
    );
}

export default OperationView;
