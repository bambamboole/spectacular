import { isJsonMediaType, parameterKey, type RequestValues } from "./request-state";
import type { Contract, Operation, Param } from "./types";

export type RequestErrors = {
    parameters: Record<string, string>;
    body: string | null;
    request: string | null;
};

export type BuiltRequest = {
    method: string;
    url: string;
    headers: Record<string, string>;
    body: string | null;
};

export type BuildRequestResult =
    | { request: BuiltRequest; errors: null }
    | { request: null; errors: RequestErrors };

const FORBIDDEN_HEADER_NAMES = new Set([
    "accept-charset",
    "accept-encoding",
    "access-control-request-headers",
    "access-control-request-method",
    "connection",
    "content-length",
    "cookie",
    "date",
    "dnt",
    "expect",
    "host",
    "keep-alive",
    "origin",
    "permissions-policy",
    "referer",
    "te",
    "trailer",
    "transfer-encoding",
    "upgrade",
    "via",
]);

export function buildRequest(input: {
    operation: Operation;
    baseUrl: string | null;
    values: RequestValues;
    token: string | null;
}): BuildRequestResult {
    const errors: RequestErrors = {
        parameters: {},
        body: null,
        request: input.baseUrl === null ? "Select a server URL before sending the request." : null,
    };
    const parameters = input.operation.paramGroups.flatMap((group) => group.params);

    validateParameters(parameters, input.values, errors);
    const selectedContract = validateBody(input.operation, input.values, errors);

    if (hasErrors(errors) || input.baseUrl === null) {
        return { request: null, errors };
    }

    const headers = buildHeaders(parameters, input.values);
    const body = input.values.body.trim() === "" ? null : input.values.body;

    if (body !== null && selectedContract !== null && selectedContract.mediaType !== null) {
        headers["Content-Type"] = selectedContract.mediaType;
    }

    if (input.token !== null && input.token !== "") {
        headers.Authorization = `Bearer ${input.token}`;
    }

    return {
        request: {
            method: input.operation.summary.method,
            url: buildUrl(input.baseUrl, input.operation.summary.path, parameters, input.values),
            headers,
            body,
        },
        errors: null,
    };
}

export function redactAuthorization(request: BuiltRequest): BuiltRequest {
    const headers = Object.fromEntries(
        Object.entries(request.headers).map(([name, value]) => [
            name,
            name.toLowerCase() === "authorization" && /^Bearer(?:\s|$)/i.test(value)
                ? "Bearer <YOUR_TOKEN>"
                : value,
        ]),
    );

    return { ...request, headers };
}

function validateParameters(parameters: Param[], values: RequestValues, errors: RequestErrors): void {
    for (const param of parameters) {
        const key = parameterKey(param);
        const value = values.parameters[key] ?? "";

        if (isComplexParameter(param)) {
            errors.parameters[key] = "Array and object parameters cannot be executed.";
            continue;
        }

        if (param.location === "cookie") {
            errors.parameters[key] = "Cookie parameters cannot be sent from a browser.";
            continue;
        }

        if (param.location === "header" && isForbiddenHeader(param.name)) {
            errors.parameters[key] = "This header cannot be sent from a browser.";
            continue;
        }

        if (param.required && value === "") {
            errors.parameters[key] = `This ${parameterLocationLabel(param.location)} parameter is required.`;
        }
    }
}

function validateBody(operation: Operation, values: RequestValues, errors: RequestErrors): Contract | null {
    if (values.mediaType === null) {
        const requiredContract = operation.requests.find(
            (contract) => contract.required && isJsonMediaType(contract.mediaType),
        );

        if (requiredContract !== undefined) {
            errors.body = "A JSON request body is required.";
        }

        return null;
    }

    const contract = operation.requests.find((candidate) => candidate.mediaType === values.mediaType);
    if (contract === undefined || !isJsonMediaType(contract.mediaType)) {
        errors.request = "The selected JSON media type is not available for this operation.";

        return null;
    }

    if (values.body.trim() === "") {
        if (contract.required) {
            errors.body = "A JSON request body is required.";
        }

        return contract;
    }

    try {
        JSON.parse(values.body);
    } catch (error: unknown) {
        errors.body = "Enter a valid JSON request body.";
    }

    return contract;
}

function buildHeaders(parameters: Param[], values: RequestValues): Record<string, string> {
    return Object.fromEntries(
        parameters
            .filter((param) => param.location === "header")
            .map((param) => [param.name, values.parameters[parameterKey(param)] ?? ""])
            .filter((entry) => entry[1] !== ""),
    );
}

function buildUrl(baseUrl: string, path: string, parameters: Param[], values: RequestValues): string {
    let resolvedPath = path;
    const query: string[] = [];

    for (const param of parameters) {
        const value = values.parameters[parameterKey(param)] ?? "";

        if (param.location === "path") {
            resolvedPath = resolvedPath.split(`{${param.name}}`).join(encodeURIComponent(value));
        }

        if (param.location === "query" && value !== "") {
            query.push(`${encodeURIComponent(param.name)}=${encodeURIComponent(value)}`);
        }
    }

    const url = `${baseUrl.replace(/\/+$/, "")}/${resolvedPath.replace(/^\/+/, "")}`;

    return query.length === 0 ? url : `${url}?${query.join("&")}`;
}

function isForbiddenHeader(name: string): boolean {
    const normalized = name.toLowerCase();

    return FORBIDDEN_HEADER_NAMES.has(normalized) || normalized.startsWith("proxy-") || normalized.startsWith("sec-");
}

function isComplexParameter(param: Param): boolean {
    if (!isRecord(param.schema)) {
        return false;
    }

    return param.schema.type === "array" || param.schema.type === "object";
}

function parameterLocationLabel(location: string): string {
    return location === "header" ? "header" : location;
}

function hasErrors(errors: RequestErrors): boolean {
    return Object.keys(errors.parameters).length > 0 || errors.body !== null || errors.request !== null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === "object" && value !== null && !Array.isArray(value);
}
