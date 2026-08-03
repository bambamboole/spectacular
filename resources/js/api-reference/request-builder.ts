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
    "cookie2",
    "date",
    "dnt",
    "expect",
    "host",
    "keep-alive",
    "origin",
    "permissions-policy",
    "referer",
    "set-cookie",
    "te",
    "trailer",
    "transfer-encoding",
    "upgrade",
    "via",
]);

const METHOD_OVERRIDE_HEADER_NAMES = new Set(["x-http-method", "x-http-method-override", "x-method-override"]);

const FORBIDDEN_METHOD_OVERRIDE_VALUES = new Set(["CONNECT", "TRACE", "TRACK"]);

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
        upsertHeader(headers, "Content-Type", selectedContract.mediaType);
    }

    if (input.token !== null && input.token !== "") {
        upsertHeader(headers, "Authorization", `Bearer ${input.token}`);
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
        const limitation = parameterLimitation(param, value);

        if (limitation !== null) {
            if (param.required || value !== "") {
                errors.parameters[key] = limitation;
            }

            continue;
        }

        if (param.required && value === "") {
            errors.parameters[key] = `This ${parameterLocationLabel(param.location)} parameter is required.`;
        }
    }
}

export function parameterLimitation(param: Param, value?: string): string | null {
    if (!hasPrimitiveSchema(param) && !hasFormArraySchema(param)) {
        return "Only primitive parameters can be executed.";
    }

    if (param.location === "cookie") {
        return "Cookie parameters cannot be sent from a browser.";
    }

    if (param.location === "header" && isForbiddenHeader(param.name)) {
        return "This header cannot be sent from a browser.";
    }

    if (param.location === "header" && isForbiddenMethodOverride(param.name, value)) {
        return "This header cannot be sent from a browser.";
    }

    return null;
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

    const baseWithoutFragment = baseUrl.split("#", 1)[0];
    const queryIndex = baseWithoutFragment.indexOf("?");
    const basePath = queryIndex === -1 ? baseWithoutFragment : baseWithoutFragment.slice(0, queryIndex);
    const existingQuery = queryIndex === -1 ? "" : baseWithoutFragment.slice(queryIndex + 1);
    const url = `${basePath.replace(/\/+$/, "")}/${resolvedPath.replace(/^\/+/, "")}`;
    const combinedQuery = [existingQuery, ...query].filter((value) => value !== "");

    return combinedQuery.length === 0 ? url : `${url}?${combinedQuery.join("&")}`;
}

function isForbiddenHeader(name: string): boolean {
    const normalized = name.toLowerCase();

    return FORBIDDEN_HEADER_NAMES.has(normalized) || normalized.startsWith("proxy-") || normalized.startsWith("sec-");
}

function isForbiddenMethodOverride(name: string, value: string | undefined): boolean {
    if (value === undefined) return false;

    const normalizedName = name.toLowerCase();

    return METHOD_OVERRIDE_HEADER_NAMES.has(normalizedName) && value
        .split(",")
        .some((method) => FORBIDDEN_METHOD_OVERRIDE_VALUES.has(method.trim().toUpperCase()));
}

function hasPrimitiveSchema(param: Param): boolean {
    if (!isRecord(param.schema)) {
        return false;
    }

    if ("$ref" in param.schema || "oneOf" in param.schema || "allOf" in param.schema || "anyOf" in param.schema) {
        return false;
    }

    return (
        typeof param.schema.type === "string" &&
        ["string", "number", "integer", "boolean"].includes(param.schema.type)
    );
}

function hasFormArraySchema(param: Param): boolean {
    if (param.location !== "query" || (param.style !== undefined && param.style !== null && param.style !== "form")) {
        return false;
    }

    if (param.explode !== false || !isRecord(param.schema) || param.schema.type !== "array") {
        return false;
    }

    const items = param.schema.items;

    return (
        isRecord(items) &&
        items.type === "string" &&
        Array.isArray(items.enum) &&
        items.enum.length > 0 &&
        items.enum.every((value) => typeof value === "string")
    );
}

function upsertHeader(headers: Record<string, string>, name: string, value: string): void {
    for (const existingName of Object.keys(headers)) {
        if (existingName.toLowerCase() === name.toLowerCase()) {
            delete headers[existingName];
        }
    }

    headers[name] = value;
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
