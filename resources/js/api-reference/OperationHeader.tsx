import { useMemo } from "react";
import { Badge, CopyButton } from "@lattice-php/lattice/ui";
import { httpMethodColor } from "./http-method-color";
import { operationToMarkdown } from "./operation-markdown";
import type { Operation } from "./types";

export function OperationHeader({
    operation,
    baseUrl,
    components,
}: {
    operation: Operation;
    baseUrl?: string | null;
    components?: unknown;
}): React.ReactNode {
    const markdown = useMemo(() => operationToMarkdown(operation, components), [operation, components]);
    const operationUrl = `${(baseUrl ?? "").replace(/\/+$/, "")}/${operation.summary.path.replace(/^\/+/, "")}`;

    return (
        <header className="mb-6">
            <div className="flex flex-wrap items-center gap-2">
                <Badge color={httpMethodColor(operation.summary.method)} className="text-xs font-semibold uppercase">
                    {operation.summary.method}
                </Badge>
                <div className="inline-flex min-w-0 items-center gap-1">
                    <span className="font-mono text-sm text-lt-muted-fg">{operationUrl}</span>
                    <CopyButton value={operationUrl} label="operation URL" iconOnly className="size-7" />
                </div>
                {operation.summary.deprecated ? <Badge color="danger">deprecated</Badge> : null}
                <CopyButton
                    value={markdown}
                    label="as Markdown"
                    testId="copy-operation-markdown"
                    className="ml-auto"
                >
                    Copy as Markdown
                </CopyButton>
            </div>
            <h1 className="mt-2 text-lg font-semibold text-lt-fg">{operation.summary.title}</h1>
            {operation.description ? <p className="mt-1 text-sm text-lt-muted-fg">{operation.description}</p> : null}
        </header>
    );
}
