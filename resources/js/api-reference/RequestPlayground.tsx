import { useEffect, useId, useMemo, useRef, useState, type FormEvent } from "react";
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Input,
    Label,
    Spinner,
    Textarea,
} from "@lattice-php/lattice/ui";
import { NativeSelect } from "@lattice-php/lattice/ui/native-select";
import { executeRequest, type ExecutedResponse, type ExecutionError } from "./execute-request";
import { LiveResponsePanel } from "./LiveResponsePanel";
import { buildRequest, redactAuthorization, type RequestErrors } from "./request-builder";
import {
    initialRequestValues,
    jsonRequestContracts,
    parameterKey,
    type RequestValues,
} from "./request-state";
import { SnippetPanel, type SnippetLanguage } from "./SnippetPanel";
import { curlSnippet } from "./snippets/curl";
import { javascriptSnippet } from "./snippets/javascript";
import type { Operation, Param } from "./types";

export type RequestPlaygroundProps = {
    operation: Operation;
    baseUrl: string | null;
    token: string | null;
    components: unknown;
};

export function RequestPlayground({
    operation,
    baseUrl,
    token,
    components,
}: RequestPlaygroundProps): React.ReactNode {
    const idPrefix = `${operation.summary.id}-${useId().replaceAll(/[^a-zA-Z0-9_-]/g, "")}`;
    const playgroundRef = useRef<HTMLElement>(null);
    const activeControllerRef = useRef<AbortController | null>(null);
    const [values, setValues] = useState<RequestValues>(() => initialRequestValues(operation, components));
    const [snippetLanguage, setSnippetLanguage] = useState<SnippetLanguage>("curl");
    const [isLoading, setIsLoading] = useState(false);
    const [liveResult, setLiveResult] = useState<ExecutedResponse | ExecutionError | null>(null);
    const jsonContracts = jsonRequestContracts(operation);
    const buildResult = useMemo(
        () => buildRequest({ operation, baseUrl, values, token }),
        [operation, baseUrl, values, token],
    );
    const nonInteractiveParameterErrors = parameterErrorsWithoutControls(operation, buildResult.errors);
    const hasUnsupportedRequestBody = operation.requests.length > 0 && jsonContracts.length === 0;
    const snippet = useMemo(() => {
        if (buildResult.request === null) {
            return "";
        }

        const request = redactAuthorization(buildResult.request);

        return snippetLanguage === "curl"
            ? curlSnippet.generate(request)
            : javascriptSnippet.generate(request);
    }, [buildResult, snippetLanguage]);

    useEffect(() => {
        return () => {
            const controller = activeControllerRef.current;
            activeControllerRef.current = null;
            controller?.abort();
        };
    }, []);

    function updateParameter(param: Param, value: string): void {
        const key = parameterKey(param);

        setValues((current) => ({
            ...current,
            parameters: { ...current.parameters, [key]: value },
        }));
    }

    function updateBody(body: string): void {
        setValues((current) => ({ ...current, body }));
    }

    function updateMediaType(mediaType: string): void {
        setValues((current) => ({ ...current, mediaType }));
    }

    async function tryRequest(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();

        if (hasUnsupportedRequestBody) {
            return;
        }

        const result = buildRequest({ operation, baseUrl, values, token });

        if (result.errors !== null) {
            const fieldKey = firstErrorFieldKey(operation, result.errors);
            const fields = playgroundRef.current?.querySelectorAll<HTMLElement>("[data-field-key]") ?? [];

            Array.from(fields).find((field) => field.dataset.fieldKey === fieldKey)?.focus();

            return;
        }

        activeControllerRef.current?.abort();
        const controller = new AbortController();
        activeControllerRef.current = controller;
        setIsLoading(true);

        try {
            const nextResult = await executeRequest(result.request, controller.signal);

            if (activeControllerRef.current === controller) {
                setLiveResult(nextResult);
            }
        } catch (error: unknown) {
            if (!isAbortError(error)) {
                throw error;
            }
        } finally {
            if (activeControllerRef.current === controller) {
                activeControllerRef.current = null;
                setIsLoading(false);
            }
        }
    }

    return (
        <Card ref={playgroundRef} className="mb-6">
            <CardHeader>
                <CardTitle>Try it out</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-6">
                {operation.paramGroups.map((group) => {
                    const supportedParams = group.params.filter((param) => isRenderableParameter(group.location, param));

                    if (supportedParams.length === 0) {
                        return null;
                    }

                    return (
                        <section key={group.location} className="flex flex-col gap-3">
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-lt-muted-fg">
                                {group.location} parameters
                            </h3>
                            <div className="flex flex-wrap gap-4">
                                {supportedParams.map((param) => (
                                    <ParameterField
                                        key={parameterKey(param)}
                                        idPrefix={idPrefix}
                                        param={param}
                                        value={values.parameters[parameterKey(param)] ?? ""}
                                        error={buildResult.errors?.parameters[parameterKey(param)] ?? null}
                                        onChange={(value) => updateParameter(param, value)}
                                    />
                                ))}
                            </div>
                        </section>
                    );
                })}

                {nonInteractiveParameterErrors.length > 0 || hasUnsupportedRequestBody ? (
                    <section aria-live="polite" className="flex flex-col gap-2">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-lt-muted-fg">
                            Request limitations
                        </h3>
                        <ul className="flex flex-col gap-1 text-xs text-lt-danger">
                            {nonInteractiveParameterErrors.map(({ key, name, message }) => (
                                <li key={key}>
                                    {name}: {message}
                                </li>
                            ))}
                            {hasUnsupportedRequestBody ? (
                                <li>Only JSON request bodies can be sent from the playground.</li>
                            ) : null}
                        </ul>
                    </section>
                ) : null}

                {jsonContracts.length > 0 ? (
                    <section className="flex flex-col gap-3">
                        {jsonContracts.length > 1 ? (
                            <div className="flex min-w-0 basis-full flex-1 flex-col gap-2 sm:basis-48">
                                <Label htmlFor={`${idPrefix}-request-media-type`}>Content type</Label>
                                <NativeSelect
                                    id={`${idPrefix}-request-media-type`}
                                    value={values.mediaType ?? ""}
                                    onChange={(event) => updateMediaType(event.target.value)}
                                >
                                    {jsonContracts.map((contract) => (
                                        <option key={contract.mediaType} value={contract.mediaType ?? ""}>
                                            {contract.mediaType}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                        ) : null}
                        <div className="flex flex-col gap-2">
                            <Label htmlFor={`${idPrefix}-request-body`}>JSON body</Label>
                            <Textarea
                                id={`${idPrefix}-request-body`}
                                value={values.body}
                                required={jsonContracts.find((contract) => contract.mediaType === values.mediaType)?.required}
                                aria-invalid={Boolean(buildResult.errors?.body)}
                                aria-describedby={
                                    buildResult.errors?.body ? `${idPrefix}-body-error` : undefined
                                }
                                data-field-key="body"
                                onChange={(event) => updateBody(event.target.value)}
                                className="min-h-40 font-mono text-sm"
                            />
                            {buildResult.errors?.body ? (
                                <p id={`${idPrefix}-body-error`} className="text-xs text-lt-danger">
                                    {buildResult.errors.body}
                                </p>
                            ) : null}
                        </div>
                    </section>
                ) : null}

                <SnippetPanel
                    idPrefix={idPrefix}
                    language={snippetLanguage}
                    snippet={snippet}
                    onLanguageChange={setSnippetLanguage}
                />

                {buildResult.errors?.request ? (
                    <p className="text-sm text-lt-danger">{buildResult.errors.request}</p>
                ) : null}

                <form onSubmit={tryRequest} className="flex flex-wrap items-center gap-3">
                    <Button type="submit" disabled={isLoading || hasUnsupportedRequestBody}>
                        {isLoading ? <Spinner className="size-lt-icon-sm" /> : null}
                        Try it out
                    </Button>
                </form>

                <LiveResponsePanel result={liveResult} />
            </CardContent>
        </Card>
    );
}

function ParameterField({
    idPrefix,
    param,
    value,
    error,
    onChange,
}: {
    idPrefix: string;
    param: Param;
    value: string;
    error: string | null;
    onChange: (value: string) => void;
}): React.ReactNode {
    const key = parameterKey(param);
    const id = `${idPrefix}-${fieldId(key)}`;
    const schema = parameterSchema(param);

    return (
        <div className="flex min-w-0 basis-full flex-1 flex-col gap-2 sm:basis-48">
            <Label htmlFor={id}>{param.name}</Label>
            {Array.isArray(schema.enum) ? (
                <NativeSelect
                    id={id}
                    value={value}
                    required={param.required}
                    aria-invalid={error !== null}
                    aria-describedby={error ? `${idPrefix}-${fieldId(key)}-error` : undefined}
                    data-field-key={key}
                    onChange={(event) => onChange(event.target.value)}
                >
                    {!param.required ? <option value="">Not set</option> : null}
                    {schema.enum.map((option) => (
                        <option key={String(option)} value={String(option)}>
                            {String(option)}
                        </option>
                    ))}
                </NativeSelect>
            ) : schema.type === "boolean" ? (
                <NativeSelect
                    id={id}
                    value={value}
                    required={param.required}
                    aria-invalid={error !== null}
                    aria-describedby={error ? `${idPrefix}-${fieldId(key)}-error` : undefined}
                    data-field-key={key}
                    onChange={(event) => onChange(event.target.value)}
                >
                    {!param.required ? <option value="">Not set</option> : null}
                    <option value="true">true</option>
                    <option value="false">false</option>
                </NativeSelect>
            ) : (
                <Input
                    id={id}
                    type={schema.type === "integer" || schema.type === "number" ? "number" : "text"}
                    value={value}
                    required={param.required}
                    aria-invalid={error !== null}
                    aria-describedby={error ? `${idPrefix}-${fieldId(key)}-error` : undefined}
                    data-field-key={key}
                    onChange={(event) => onChange(event.target.value)}
                />
            )}
            {error ? (
                <p id={`${idPrefix}-${fieldId(key)}-error`} className="text-xs text-lt-danger">
                    {error}
                </p>
            ) : null}
        </div>
    );
}

function firstErrorFieldKey(operation: Operation, errors: RequestErrors): string | null {
    for (const group of operation.paramGroups) {
        for (const param of group.params) {
            const key = parameterKey(param);

            if (isRenderableParameter(group.location, param) && errors.parameters[key] !== undefined) {
                return key;
            }
        }
    }

    return errors.body === null ? null : "body";
}

function parameterErrorsWithoutControls(
    operation: Operation,
    errors: RequestErrors | null,
): Array<{ key: string; name: string; message: string }> {
    if (errors === null) {
        return [];
    }

    return operation.paramGroups.flatMap((group) =>
        group.params.flatMap((param) => {
            const key = parameterKey(param);
            const message = errors.parameters[key];

            return message !== undefined && !isRenderableParameter(group.location, param)
                ? [{ key, name: param.name, message }]
                : [];
        }),
    );
}

function isRenderableParameter(location: string, param: Param): boolean {
    return ["path", "query", "header"].includes(location) && isPrimitiveParameter(param);
}

function isPrimitiveParameter(param: Param): boolean {
    const schema = parameterSchema(param);

    return !(
        "$ref" in schema ||
        "oneOf" in schema ||
        "allOf" in schema ||
        "anyOf" in schema
    ) && typeof schema.type === "string" && ["string", "number", "integer", "boolean"].includes(schema.type);
}

function parameterSchema(param: Param): Record<string, unknown> {
    return isRecord(param.schema) ? param.schema : {};
}

function fieldId(key: string): string {
    return key.replaceAll(/[^a-zA-Z0-9_-]/g, "-");
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === "object" && value !== null && !Array.isArray(value);
}

function isAbortError(error: unknown): error is { name: string } {
    return typeof error === "object" && error !== null && "name" in error && error.name === "AbortError";
}
