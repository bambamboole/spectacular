export type ApiInfo = { title: string; version: string | null; description: string | null };
export type OperationSummary = { id: string; method: string; path: string; title: string; deprecated: boolean };
export type NavGroup = { id: string; title: string; operationIds: string[] };
export type Navigation = { info: ApiInfo; groups: NavGroup[]; summaries: Record<string, OperationSummary> };
export type ParamGroup = { location: string; params: Param[] };
export type Param = {
    name: string;
    location: string;
    required: boolean;
    deprecated: boolean;
    description: string | null;
    schema: unknown;
};
export type Contract = {
    role: "request" | "response";
    status: string | null;
    mediaType: string | null;
    schema: unknown;
    title: string | null;
};
export type Operation = {
    summary: OperationSummary;
    description: string | null;
    tags: string[];
    paramGroups: ParamGroup[];
    requests: Contract[];
    responses: Contract[];
};
