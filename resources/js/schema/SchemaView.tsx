import { useEffect, useState } from "react";
import { buildSchemaRows, type SchemaRow } from "./build-rows";
import { Icon } from "@lattice-php/lattice/icons";

function Row({ row, depth, expandDepth }: { row: SchemaRow; depth: number; expandDepth: number }): React.ReactNode {
    const [open, setOpen] = useState(depth < expandDepth);
    const hasChildren = row.children.length > 0 || row.isRecursive;

    return (
        <div className="border-l border-lt-border pl-3">
            <div className="flex items-center gap-2 py-1">
                {hasChildren ? (
                    <button
                        type="button"
                        onClick={() => setOpen((v) => !v)}
                        className="text-lt-muted-fg"
                        aria-expanded={open}
                    >
                        <Icon
                            name="chevron-down"
                            className={`size-lt-icon-xs transition-transform${open ? "" : " -rotate-90"}`}
                        />
                    </button>
                ) : (
                    <span className="w-3" />
                )}
                <span className="font-mono text-lt-fg">{row.name ?? "—"}</span>
                <span className="text-xs text-lt-muted-fg">{row.typeLabel}</span>
                {row.required ? <span className="text-lt-danger">*</span> : null}
                {row.isRecursive ? <span className="text-xs text-lt-muted-fg">↩ recursive</span> : null}
            </div>
            {(!hasChildren || open) && row.description ? (
                <p className="pl-5 text-xs text-lt-muted-fg">{row.description}</p>
            ) : null}
            {row.details.length > 0 ? (
                <p className="pl-5 font-mono text-xs text-lt-muted-fg">{row.details.join(" · ")}</p>
            ) : null}
            {open && !row.isRecursive
                ? row.children.map((c) => <Row key={c.id} row={c} depth={depth + 1} expandDepth={expandDepth} />)
                : null}
        </div>
    );
}

export function SchemaView({
    schema,
    components,
    expandDepth = 0,
}: {
    schema: unknown;
    components: unknown;
    expandDepth?: number;
}): React.ReactNode {
    const [rows, setRows] = useState<SchemaRow[]>([]);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let active = true;
        buildSchemaRows(schema, components)
            .then((result) => active && setRows(result))
            .catch((e: unknown) => active && setError(String(e)));
        return () => {
            active = false;
        };
    }, [schema, components]);

    if (error) {
        return <div className="text-lt-danger">{error}</div>;
    }

    return (
        <div className="text-sm">
            {rows.map((row) => (
                <Row key={row.id} row={row} depth={0} expandDepth={expandDepth} />
            ))}
        </div>
    );
}
