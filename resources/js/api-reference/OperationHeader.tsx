import { useMemo } from "react";
import { Badge, CopyButton, CopyableText } from "@lattice-php/lattice/ui";
import { httpMethodColor } from "./http-method-color";
import { operationToMarkdown } from "./operation-markdown";
import type { Operation } from "./types";

export function OperationHeader({ operation, baseUrl }: { operation: Operation; baseUrl?: string | null }): React.ReactNode {
    const markdown = useMemo(() => operationToMarkdown(operation), [operation]);
    const operationUrl = `${baseUrl ?? ""}${operation.summary.path}`;

    return (
        <header className="mb-6">
            <div className="flex flex-wrap items-center gap-2">
                <Badge color={httpMethodColor(operation.summary.method)} className="text-xs font-semibold uppercase">
                    {operation.summary.method}
                </Badge>
                <CopyableText value={operationUrl} label="operation URL">
                    <span className="font-mono text-sm text-lt-muted-fg">{operationUrl}</span>
                </CopyableText>
                <CopyButton value={markdown} label="operation as Markdown" testId="copy-operation-markdown" />
                {operation.summary.deprecated ? <Badge color="danger">deprecated</Badge> : null}
            </div>
            <h1 className="mt-2 text-lg font-semibold text-lt-fg">{operation.summary.title}</h1>
            {operation.description ? <p className="mt-1 text-sm text-lt-muted-fg">{operation.description}</p> : null}
        </header>
    );
}
