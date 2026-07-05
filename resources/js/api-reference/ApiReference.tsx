import { useEffect, useMemo, useState } from "react";
import type { RendererComponent } from "@lattice-php/lattice";
import { ApiReferenceNav } from "./ApiReferenceNav";
import { OperationView } from "./OperationView";
import { buildNavigation, filterNavigationByTags } from "./parse";
import type { ApiInfo, Navigation } from "./types";

type ApiReferenceProps = {
    spec?: unknown;
    url?: string;
    operation?: string | null;
    tags?: string[] | null;
    hideNav?: boolean;
    layout?: "sidebar" | "stacked";
    defaultOperation?: string | null;
    hideHeader?: boolean;
    title?: string | null;
    expandDepth?: number;
};

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

function InfoHeader({ title, info }: { title: string | null; info: ApiInfo }): React.ReactNode {
    const resolvedTitle = title ?? info.title;

    if (!resolvedTitle && !info.version && !info.description) return null;

    return (
        <header className="border-b border-lt-border p-6">
            {resolvedTitle ? <h1 className="text-lg font-semibold text-lt-fg">{resolvedTitle}</h1> : null}
            {info.version ? <p className="mt-1 text-xs text-lt-muted-fg">v{info.version}</p> : null}
            {info.description ? <p className="mt-2 text-sm text-lt-muted-fg">{info.description}</p> : null}
        </header>
    );
}

const ApiReference: RendererComponent<"spectacular.api-reference"> = ({ node }) => {
    const {
        spec: inlineSpec,
        url,
        operation,
        tags,
        hideNav = false,
        layout = "sidebar",
        defaultOperation,
        hideHeader = false,
        title = null,
        expandDepth = 0,
    } = node.props as ApiReferenceProps;

    const [spec, setSpec] = useState<unknown>(inlineSpec ?? null);
    const [loading, setLoading] = useState<boolean>(Boolean(url));
    const [error, setError] = useState<string | null>(null);
    const [selectedId, setSelectedId] = useState<string | null>(() => currentHashId());
    const [selectedServerUrl, setSelectedServerUrl] = useState<string | null>(null);

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

    const rawNavigation = useMemo(() => (spec ? buildNavigation(spec) : null), [spec]);
    const navigation = useMemo(
        () => (rawNavigation && tags?.length ? filterNavigationByTags(rawNavigation, tags) : rawNavigation),
        [rawNavigation, tags],
    );

    useEffect(() => {
        if (selectedId !== null || !navigation) return;

        const initial = currentHashId() ?? defaultOperation ?? firstSummaryId(navigation);
        if (initial) setSelectedId(initial);
    }, [navigation, selectedId, defaultOperation]);

    useEffect(() => {
        if (selectedServerUrl !== null || !navigation) return;

        const initial = navigation.servers[0]?.url ?? null;
        if (initial) setSelectedServerUrl(initial);
    }, [navigation, selectedServerUrl]);

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

    function selectStackedOperation(id: string): void {
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

    if (operation) {
        return (
            <div className="flex w-full">
                <div className="flex min-w-0 flex-1 flex-col">
                    {!hideHeader ? <InfoHeader title={title} info={navigation.info} /> : null}
                    <OperationView
                        key={operation}
                        spec={spec}
                        operationId={operation}
                        baseUrl={selectedServerUrl}
                        expandDepth={expandDepth}
                    />
                </div>
            </div>
        );
    }

    if (layout === "stacked") {
        const operationIds = Array.from(new Set(navigation.groups.flatMap((group) => group.operationIds)));

        return (
            <div className="flex w-full">
                {!hideNav ? (
                    <ApiReferenceNav
                        navigation={navigation}
                        selectedId={selectedId}
                        onSelect={selectStackedOperation}
                        servers={navigation.servers}
                        selectedServerUrl={selectedServerUrl}
                        onServerChange={setSelectedServerUrl}
                    />
                ) : null}
                <div className="flex min-w-0 flex-1 flex-col overflow-y-auto">
                    {!hideHeader ? <InfoHeader title={title} info={navigation.info} /> : null}
                    {operationIds.map((id) => (
                        <section id={id} key={id}>
                            <OperationView
                                spec={spec}
                                operationId={id}
                                baseUrl={selectedServerUrl}
                                expandDepth={expandDepth}
                            />
                        </section>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <div className="flex w-full">
            {!hideNav ? (
                <ApiReferenceNav
                    navigation={navigation}
                    selectedId={selectedId}
                    onSelect={selectOperation}
                    servers={navigation.servers}
                    selectedServerUrl={selectedServerUrl}
                    onServerChange={setSelectedServerUrl}
                />
            ) : null}
            <div className="flex min-w-0 flex-1 flex-col">
                {!hideHeader ? <InfoHeader title={title} info={navigation.info} /> : null}
                <OperationView
                    key={selectedId}
                    spec={spec}
                    operationId={selectedId}
                    baseUrl={selectedServerUrl}
                    expandDepth={expandDepth}
                />
            </div>
        </div>
    );
};

export default ApiReference;
