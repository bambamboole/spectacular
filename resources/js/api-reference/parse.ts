import type {
    ApiInfo,
    Contract,
    Navigation,
    NavGroup,
    Operation,
    OperationSummary,
    Param,
    ParamGroup,
} from "./types";

const HTTP_METHODS = ["get", "post", "put", "patch", "delete", "options", "head", "trace"];

const PARAM_LOCATION_ORDER = ["path", "query", "header", "cookie"];

const DEFAULT_GROUP_TITLE = "Default";

type RawParameter = {
    name: string;
    in: string;
    required?: boolean;
    deprecated?: boolean;
    description?: string | null;
    schema?: unknown;
    $ref?: string;
};

type RawOperation = {
    operationId?: string;
    summary?: string;
    description?: string | null;
    tags?: string[];
    deprecated?: boolean;
    parameters?: RawParameter[];
    requestBody?: { $ref?: string; description?: string | null; content?: Record<string, { schema?: unknown }> };
    responses?: Record<string, { $ref?: string; description?: string | null; content?: Record<string, { schema?: unknown }> }>;
};

type RawPathItem = Record<string, unknown> & {
    parameters?: RawParameter[];
};

/**
 * Derives a stable slug from a path so client-derived operation ids stay stable for deep-linking.
 */
function slug(path: string): string {
    const stripped = path.replaceAll("/", "-").replaceAll("{", "").replaceAll("}", "");
    const trimmed = stripped.replace(/^-+|-+$/g, "");

    return trimmed;
}

function operationId(method: string, path: string): string {
    const pathSlug = slug(path);

    return pathSlug === "" ? `${method}-root` : `${method}-${pathSlug}`;
}

function operationTitle(operation: RawOperation, method: string, path: string): string {
    if (typeof operation.operationId === "string" && operation.operationId !== "") {
        return operation.operationId;
    }
    if (typeof operation.summary === "string" && operation.summary !== "") {
        return operation.summary;
    }

    return `${method.toUpperCase()} ${path}`;
}

function resolveRef<T>(spec: any, ref: string | undefined, kind: "parameters" | "requestBodies" | "responses"): T | null {
    if (typeof ref !== "string") return null;
    const name = ref.split("/").pop();
    if (!name) return null;

    return (spec?.components?.[kind]?.[name] as T | undefined) ?? null;
}

function findOperation(spec: any, opId: string): { path: string; method: string; pathItem: RawPathItem; operation: RawOperation } | null {
    const paths = spec?.paths ?? {};

    for (const path of Object.keys(paths)) {
        const pathItem = paths[path] as RawPathItem;

        for (const method of HTTP_METHODS) {
            const operation = pathItem[method] as RawOperation | undefined;
            if (!operation || typeof operation !== "object") continue;
            if (operationId(method, path) === opId) {
                return { path, method, pathItem, operation };
            }
        }
    }

    return null;
}

export function buildNavigation(spec: any): Navigation {
    const info: ApiInfo = {
        title: spec?.info?.title ?? "",
        version: spec?.info?.version ?? null,
        description: spec?.info?.description ?? null,
    };

    const summaries: Record<string, OperationSummary> = {};
    const operationIdsByTag = new Map<string, string[]>();

    const paths = spec?.paths ?? {};
    for (const path of Object.keys(paths)) {
        const pathItem = paths[path] as RawPathItem;

        for (const method of HTTP_METHODS) {
            const operation = pathItem[method] as RawOperation | undefined;
            if (!operation || typeof operation !== "object") continue;

            const id = operationId(method, path);
            summaries[id] = {
                id,
                method: method.toUpperCase(),
                path,
                title: operationTitle(operation, method, path),
                deprecated: Boolean(operation.deprecated),
            };

            const tags = operation.tags && operation.tags.length > 0 ? operation.tags : [DEFAULT_GROUP_TITLE];
            for (const tag of tags) {
                const ids = operationIdsByTag.get(tag) ?? [];
                ids.push(id);
                operationIdsByTag.set(tag, ids);
            }
        }
    }

    const groups: NavGroup[] = Array.from(operationIdsByTag.entries()).map(([tag, operationIds]) => ({
        id: slugifyTag(tag),
        title: tag,
        operationIds,
    }));

    return { info, groups, summaries };
}

function slugifyTag(tag: string): string {
    return tag
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");
}

function buildParam(parameter: RawParameter): Param {
    return {
        name: parameter.name,
        location: parameter.in,
        required: Boolean(parameter.required),
        deprecated: Boolean(parameter.deprecated),
        description: parameter.description ?? null,
        schema: parameter.schema ?? {},
    };
}

function buildParamGroups(spec: any, sharedParameters: RawParameter[], operationParameters: RawParameter[]): ParamGroup[] {
    const merged = new Map<string, RawParameter>();

    for (const parameters of [sharedParameters, operationParameters]) {
        for (const parameter of parameters) {
            const resolved = parameter.$ref
                ? (resolveRef<RawParameter>(spec, parameter.$ref, "parameters") ?? parameter)
                : parameter;
            merged.set(`${resolved.in}::${resolved.name}`, resolved);
        }
    }

    const buckets = new Map<string, Param[]>();
    for (const parameter of merged.values()) {
        const bucket = buckets.get(parameter.in) ?? [];
        bucket.push(buildParam(parameter));
        buckets.set(parameter.in, bucket);
    }

    const groups: ParamGroup[] = [];
    for (const location of PARAM_LOCATION_ORDER) {
        const params = buckets.get(location);
        if (params && params.length > 0) {
            groups.push({ location, params });
        }
    }

    return groups;
}

function buildRequests(spec: any, requestBody: RawOperation["requestBody"]): Contract[] {
    if (!requestBody) return [];

    const resolved = requestBody.$ref
        ? (resolveRef<NonNullable<RawOperation["requestBody"]>>(spec, requestBody.$ref, "requestBodies") ?? requestBody)
        : requestBody;

    const content = resolved.content ?? {};
    const title = resolved.description ?? null;

    return Object.entries(content).map(([mediaType, mediaTypeObject]) => ({
        role: "request" as const,
        status: null,
        mediaType,
        schema: mediaTypeObject?.schema ?? null,
        title,
    }));
}

function buildResponses(spec: any, responses: RawOperation["responses"]): Contract[] {
    if (!responses) return [];

    const contracts: Contract[] = [];

    for (const [status, response] of Object.entries(responses)) {
        const resolved = response.$ref
            ? (resolveRef<NonNullable<typeof response>>(spec, response.$ref, "responses") ?? response)
            : response;

        const title = resolved.description ?? null;
        const content = resolved.content ?? {};
        const mediaTypes = Object.entries(content);

        if (mediaTypes.length === 0) {
            contracts.push({ role: "response", status, mediaType: null, schema: null, title });
            continue;
        }

        for (const [mediaType, mediaTypeObject] of mediaTypes) {
            contracts.push({
                role: "response",
                status,
                mediaType,
                schema: mediaTypeObject?.schema ?? null,
                title,
            });
        }
    }

    return contracts;
}

export function parseOperation(spec: any, opId: string): Operation | null {
    const found = findOperation(spec, opId);
    if (!found) return null;

    const { path, method, pathItem, operation } = found;

    const summary: OperationSummary = {
        id: opId,
        method: method.toUpperCase(),
        path,
        title: operationTitle(operation, method, path),
        deprecated: Boolean(operation.deprecated),
    };

    return {
        summary,
        description: operation.description ?? null,
        tags: operation.tags ?? [],
        paramGroups: buildParamGroups(spec, pathItem.parameters ?? [], operation.parameters ?? []),
        requests: buildRequests(spec, operation.requestBody),
        responses: buildResponses(spec, operation.responses),
    };
}
