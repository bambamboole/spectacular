import { useEffect, useMemo, useState } from "react";
import { Badge, Button, copyToClipboard } from "@lattice-php/lattice/ui";
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
                    <ClipboardButton value={operationUrl} label="Copy operation URL" compact />
                </div>
                {operation.summary.deprecated ? <Badge color="danger">deprecated</Badge> : null}
                <ClipboardButton
                    value={markdown}
                    label="Copy as Markdown"
                    testId="copy-operation-markdown"
                    className="ml-auto"
                />
            </div>
            <h1 className="mt-2 text-lg font-semibold text-lt-fg">{operation.summary.title}</h1>
            {operation.description ? <p className="mt-1 text-sm text-lt-muted-fg">{operation.description}</p> : null}
        </header>
    );
}

function ClipboardButton({
    value,
    label,
    testId,
    className,
    compact = false,
}: {
    value: string;
    label: string;
    testId?: string;
    className?: string;
    compact?: boolean;
}): React.ReactNode {
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) {
            return;
        }

        const timeout = window.setTimeout(() => setCopied(false), 1500);

        return () => window.clearTimeout(timeout);
    }, [copied]);

    async function copy(): Promise<void> {
        if (await copyToClipboard(value)) {
            setCopied(true);
        }
    }

    return (
        <Button
            type="button"
            size={compact ? "icon" : "sm"}
            emphasis={compact ? "ghost" : "outline"}
            icon={copied ? "check" : "copy"}
            aria-label={copied ? `Copied ${label.replace(/^Copy /, "")}` : label}
            data-test={testId}
            className={compact ? `size-7 ${className ?? ""}` : className}
            onClick={copy}
        >
            {compact ? null : copied ? "Copied" : label}
        </Button>
    );
}
