import { useMemo, useState } from "react";
import type { Navigation, NavGroup } from "./types";

type ApiReferenceNavProps = {
    navigation: Navigation;
    selectedId: string | null;
    onSelect: (id: string) => void;
};

function filterGroups(navigation: Navigation, needle: string): NavGroup[] {
    if (needle === "") return navigation.groups;

    return navigation.groups
        .map((group) => ({
            ...group,
            operationIds: group.operationIds.filter((id) => {
                const summary = navigation.summaries[id];
                if (!summary) return false;

                return summary.path.toLowerCase().includes(needle) || summary.title.toLowerCase().includes(needle);
            }),
        }))
        .filter((group) => group.operationIds.length > 0);
}

export function ApiReferenceNav({ navigation, selectedId, onSelect }: ApiReferenceNavProps): React.ReactNode {
    const [filter, setFilter] = useState("");

    const groups = useMemo(
        () => filterGroups(navigation, filter.trim().toLowerCase()),
        [navigation, filter],
    );

    return (
        <nav className="sticky top-0 flex h-screen w-72 shrink-0 flex-col border-r border-lt-border bg-lt-surface text-lt-surface-fg">
            <div className="border-b border-lt-border p-3">
                <input
                    type="text"
                    value={filter}
                    onChange={(event) => setFilter(event.target.value)}
                    placeholder="Filter operations…"
                    aria-label="Filter operations"
                    className="w-full rounded-lt-sm border border-lt-input bg-lt-bg px-2 py-1 text-sm text-lt-fg placeholder:text-lt-muted-fg focus:outline-none focus-visible:ring-2 focus-visible:ring-lt-ring"
                />
            </div>
            <div className="flex-1 overflow-y-auto p-2">
                {groups.map((group) => (
                    <div key={group.id} className="mb-4">
                        <h3 className="px-2 py-1 text-xs font-semibold uppercase tracking-wide text-lt-muted-fg">
                            {group.title}
                        </h3>
                        <ul>
                            {group.operationIds.map((id) => {
                                const summary = navigation.summaries[id];
                                if (!summary) return null;

                                const active = id === selectedId;

                                return (
                                    <li key={id}>
                                        <button
                                            type="button"
                                            onClick={() => onSelect(id)}
                                            aria-current={active ? "page" : undefined}
                                            className={`flex w-full items-center gap-2 rounded-lt-sm px-2 py-1 text-left text-sm transition-colors ${
                                                active
                                                    ? "bg-lt-accent text-lt-accent-fg"
                                                    : "text-lt-fg hover:bg-lt-muted"
                                            }`}
                                        >
                                            <span className="font-mono text-xs text-lt-muted-fg">{summary.method}</span>
                                            <span className="truncate">{summary.path}</span>
                                            {summary.deprecated ? (
                                                <span className="ml-auto shrink-0 text-xs text-lt-danger">deprecated</span>
                                            ) : null}
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
                {groups.length === 0 ? (
                    <p className="px-2 py-1 text-sm text-lt-muted-fg">No matching operations.</p>
                ) : null}
            </div>
        </nav>
    );
}

export default ApiReferenceNav;
