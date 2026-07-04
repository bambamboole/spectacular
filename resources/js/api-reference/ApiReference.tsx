import { useEffect, useMemo, useState } from "react";
import type { RendererComponent } from "@lattice-php/lattice";
import { ApiReferenceNav } from "./ApiReferenceNav";
import { OperationView } from "./OperationView";
import { buildNavigation } from "./parse";
import type { Navigation } from "./types";

type ApiReferenceProps = { spec?: unknown; url?: string };

function firstSummaryId(navigation: Navigation | null): string | null {
    if (!navigation) return null;

    for (const group of navigation.groups) {
        const [id] = group.operationIds;
        if (id) return id;
    }

    return null;
}

function currentHashId(): string | null {
    const hash = window.location.hash.slice(1);

    return hash === "" ? null : hash;
}

const ApiReference: RendererComponent<"spectacular.api-reference"> = ({ node }) => {
    const { spec: inlineSpec, url } = node.props as ApiReferenceProps;

    const [spec, setSpec] = useState<unknown>(inlineSpec ?? null);
    const [loading, setLoading] = useState<boolean>(Boolean(url));
    const [error, setError] = useState<string | null>(null);
    const [selectedId, setSelectedId] = useState<string | null>(() => currentHashId());

    useEffect(() => {
        if (!url) return;

        let active = true;
        setLoading(true);
        setError(null);

        fetch(url)
            .then((res) => {
                if (!res.ok) {
                    throw new Error(`Failed to fetch spec: ${res.status} ${res.statusText}`);
                }

                return res.json();
            })
            .then((json: unknown) => {
                if (active) setSpec(json);
            })
            .catch((e: unknown) => {
                if (active) setError(e instanceof Error ? e.message : String(e));
            })
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => {
            active = false;
        };
    }, [url]);

    const navigation = useMemo(() => (spec ? buildNavigation(spec) : null), [spec]);

    useEffect(() => {
        if (selectedId !== null || !navigation) return;

        const initial = firstSummaryId(navigation);
        if (initial) setSelectedId(initial);
    }, [navigation, selectedId]);

    useEffect(() => {
        function onHashChange(): void {
            setSelectedId(currentHashId());
        }

        window.addEventListener("hashchange", onHashChange);

        return () => window.removeEventListener("hashchange", onHashChange);
    }, []);

    function selectOperation(id: string): void {
        setSelectedId(id);
        window.location.hash = id;
    }

    if (loading) {
        return <div className="p-6 text-sm text-lt-muted-fg">Loading API reference…</div>;
    }

    if (error) {
        return <div className="p-6 text-sm text-lt-danger">{error}</div>;
    }

    if (!spec || !navigation) {
        return <div className="p-6 text-sm text-lt-muted-fg">No API specification provided.</div>;
    }

    return (
        <div className="flex w-full">
            <ApiReferenceNav navigation={navigation} selectedId={selectedId} onSelect={selectOperation} />
            <OperationView key={selectedId} spec={spec} operationId={selectedId} />
        </div>
    );
};

export default ApiReference;
