import type { BuiltRequest } from "./request-builder";

export type ExecutedResponse = {
    kind: "response";
    status: number;
    statusText: string;
    durationMs: number;
    headers: Array<[string, string]>;
    body: string;
    contentType: string | null;
};

export type ExecutionError = {
    kind: "error";
    message: string;
};

export async function executeRequest(
    request: BuiltRequest,
    signal: AbortSignal,
    now: () => number = Date.now,
): Promise<ExecutedResponse | ExecutionError> {
    const startedAt = now();

    try {
        const response = await fetch(request.url, {
            method: request.method,
            headers: request.headers,
            body: request.body,
            signal,
        });
        const body = formatBody(await response.text());

        return {
            kind: "response",
            status: response.status,
            statusText: response.statusText,
            durationMs: Math.max(0, now() - startedAt),
            headers: Array.from(response.headers.entries()).sort(([left], [right]) => {
                if (left < right) {
                    return -1;
                }

                return left > right ? 1 : 0;
            }),
            body,
            contentType: response.headers.get("content-type"),
        };
    } catch (error: unknown) {
        if (isAbortError(error)) {
            throw error;
        }

        return {
            kind: "error",
            message: "Request failed. Check the browser console and CORS configuration.",
        };
    }
}

function formatBody(body: string): string {
    try {
        return JSON.stringify(JSON.parse(body), null, 2);
    } catch {
        return body;
    }
}

function isAbortError(error: unknown): error is { name: string } {
    return typeof error === "object" && error !== null && "name" in error && error.name === "AbortError";
}
