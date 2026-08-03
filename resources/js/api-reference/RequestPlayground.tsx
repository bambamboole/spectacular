import {
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
    type Dispatch,
    type FormEvent,
    type SetStateAction,
} from "react";
import { FormFieldFrame } from "@lattice-php/lattice/form";
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Combobox,
    Input,
    NativeSelect,
    Spinner,
    Textarea,
} from "@lattice-php/lattice/ui";
import { executeRequest, type ExecutedResponse, type ExecutionError } from "./execute-request";
import { LiveResponsePanel } from "./LiveResponsePanel";
import {
    buildRequest,
    parameterLimitation,
    redactAuthorization,
    type RequestErrors,
} from "./request-builder";
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
    values?: RequestValues;
    onValuesChange?: Dispatch<SetStateAction<RequestValues>>;
    enabled?: boolean;
    onEnabledChange?: Dispatch<SetStateAction<boolean>>;
    hideInlineParameters?: boolean;
    onValidationError?: (fieldKey: string | null) => void;
};

export function RequestPlayground({
    operation,
    baseUrl,
    token,
    components,
    values: controlledValues,
    onValuesChange,
    enabled: controlledEnabled,
    onEnabledChange,
    hideInlineParameters = false,
    onValidationError,
}: RequestPlaygroundProps): React.ReactNode {
    const idPrefix = `${operation.summary.id}-${useId().replaceAll(/[^a-zA-Z0-9_-]/g, "")}`;
    const playgroundRef = useRef<HTMLElement>(null);
    const activeControllerRef = useRef<AbortController | null>(null);
    const [internalValues, setInternalValues] = useState<RequestValues>(() =>
        initialPlaygroundValues(operation, components),
    );
    const [snippetLanguage, setSnippetLanguage] = useState<SnippetLanguage>("curl");
    const [internalEnabled, setInternalEnabled] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [liveResult, setLiveResult] = useState<ExecutedResponse | ExecutionError | null>(null);
    const values = controlledValues ?? internalValues;
    const setValues = onValuesChange ?? setInternalValues;
    const isEnabled = controlledEnabled ?? internalEnabled;
    const setIsEnabled = onEnabledChange ?? setInternalEnabled;
    const jsonContracts = jsonRequestContracts(operation);
    const buildResult = useMemo(
        () => buildRequest({ operation, baseUrl, values, token }),
        [operation, baseUrl, values, token],
    );
    const nonInteractiveParameterLimitations = parameterLimitationsWithoutControls(operation);
    const hasUnsupportedRequestBody = operation.requests.length > 0 && jsonContracts.length === 0;
    const requestBodyRequired =
        jsonContracts.find((contract) => contract.mediaType === values.mediaType)?.required ?? false;
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

    function cancelTryOut(): void {
        activeControllerRef.current?.abort();
        activeControllerRef.current = null;
        setValues(initialPlaygroundValues(operation, components));
        setSnippetLanguage("curl");
        setIsLoading(false);
        setLiveResult(null);
        setIsEnabled(false);
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

            onValidationError?.(fieldKey);
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
            <CardHeader className="flex-row items-center justify-between gap-3">
                <CardTitle>Try it out</CardTitle>
                <Button
                    type="button"
                    emphasis={isEnabled ? "outline" : "solid"}
                    onClick={() => (isEnabled ? cancelTryOut() : setIsEnabled(true))}
                >
                    {isEnabled ? "Cancel" : "Try it out"}
                </Button>
            </CardHeader>
            {isEnabled ? <CardContent className="flex flex-col gap-6">
                {operation.paramGroups
                    .filter((group) => !hideInlineParameters || !isInlineParameterGroup(group.location))
                    .map((group) => {
                        const supportedParams = group.params.filter((param) =>
                            isRenderableParameter(group.location, param),
                        );

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
                                        <RequestParameterField
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

                {nonInteractiveParameterLimitations.length > 0 || hasUnsupportedRequestBody ? (
                    <section aria-live="polite" className="flex flex-col gap-2">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-lt-muted-fg">
                            Request limitations
                        </h3>
                        <ul className="flex flex-col gap-1 text-xs text-lt-danger">
                            {nonInteractiveParameterLimitations.map(({ key, name, message }) => (
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
                            <FormFieldFrame
                                id={`${idPrefix}-request-media-type`}
                                label="Content type"
                                className="min-w-0 basis-full flex-1 sm:basis-48"
                            >
                                {(controlProps) => (
                                    <NativeSelect
                                        {...controlProps}
                                        value={values.mediaType ?? ""}
                                        onChange={(event) => updateMediaType(event.target.value)}
                                    >
                                        {jsonContracts.map((contract) => (
                                            <option key={contract.mediaType} value={contract.mediaType ?? ""}>
                                                {contract.mediaType}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                )}
                            </FormFieldFrame>
                        ) : null}
                        <FormFieldFrame
                            id={`${idPrefix}-request-body`}
                            label="JSON body"
                            required={requestBodyRequired}
                            error={buildResult.errors?.body ?? undefined}
                        >
                            {(controlProps) => (
                                <Textarea
                                    {...controlProps}
                                    value={values.body}
                                    required={requestBodyRequired}
                                    data-field-key="body"
                                    onChange={(event) => updateBody(event.target.value)}
                                    className="min-h-40 font-mono text-sm"
                                />
                            )}
                        </FormFieldFrame>
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
                        Execute
                    </Button>
                </form>

                <LiveResponsePanel result={liveResult} />
            </CardContent> : null}
        </Card>
    );
}

export function RequestParameterField({
    idPrefix,
    param,
    value,
    error,
    onChange,
    inline = false,
}: {
    idPrefix: string;
    param: Param;
    value: string;
    error: string | null;
    onChange: (value: string) => void;
    inline?: boolean;
}): React.ReactNode {
    const key = parameterKey(param);
    const id = `${idPrefix}-${fieldId(key)}`;
    const schema = parameterSchema(param);
    const arrayOptions = parameterArrayOptions(schema);
    const selectedArrayOptions = value === "" ? [] : value.split(",");
    const [isArrayOptionsOpen, setIsArrayOptionsOpen] = useState(false);

    function toggleArrayOption(option: string): void {
        onChange(
            selectedArrayOptions.includes(option)
                ? selectedArrayOptions.filter((selected) => selected !== option).join(",")
                : [...selectedArrayOptions, option].join(","),
        );
    }

    return (
        <FormFieldFrame
            id={id}
            label={param.name}
            required={param.required}
            helperText={inline ? undefined : (param.description ?? undefined)}
            error={error ?? undefined}
            className={
                inline ? "min-w-0 [&>div:first-child]:sr-only" : "min-w-0 basis-full flex-1 sm:basis-48"
            }
        >
            {(controlProps) =>
                arrayOptions.length > 0 ? (
                    <Combobox
                        multiple
                        open={isArrayOptionsOpen}
                        onOpenChange={setIsArrayOptionsOpen}
                        options={arrayOptions.map((option) => ({ label: option, value: option, data: null }))}
                        selected={selectedArrayOptions}
                        onSelect={toggleArrayOption}
                        emptyLabel="No values found."
                        searchPlaceholder="Search values..."
                        trigger={
                            <span className={selectedArrayOptions.length === 0 ? "text-lt-muted-fg" : undefined}>
                                {selectedArrayOptions.length === 0 ? "Not set" : selectedArrayOptions.join(", ")}
                            </span>
                        }
                        triggerClassName="flex h-lt-control-md w-full items-center rounded-lt-sm border border-lt-input bg-transparent px-3 py-1 text-left text-sm outline-none focus-visible:border-lt-ring focus-visible:ring-[length:var(--lt-ring-width)] focus-visible:ring-lt-ring/50"
                        triggerProps={
                            {
                                ...controlProps,
                                "data-field-key": key,
                            } as React.ComponentProps<"button"> & { "data-field-key": string }
                        }
                    />
                ) : Array.isArray(schema.enum) ? (
                    <NativeSelect
                        {...controlProps}
                        value={value}
                        required={param.required}
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
                        {...controlProps}
                        value={value}
                        required={param.required}
                        data-field-key={key}
                        onChange={(event) => onChange(event.target.value)}
                    >
                        {!param.required ? <option value="">Not set</option> : null}
                        <option value="true">true</option>
                        <option value="false">false</option>
                    </NativeSelect>
                ) : (
                    <Input
                        {...controlProps}
                        type={parameterInputType(schema)}
                        value={value}
                        required={param.required}
                        min={parameterMinimum(schema)}
                        max={parameterMaximum(schema)}
                        step={parameterStep(schema)}
                        minLength={numberValue(schema.minLength)}
                        maxLength={numberValue(schema.maxLength)}
                        pattern={typeof schema.pattern === "string" ? schema.pattern : undefined}
                        data-field-key={key}
                        onChange={(event) => onChange(event.target.value)}
                    />
                )
            }
        </FormFieldFrame>
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

function parameterLimitationsWithoutControls(
    operation: Operation,
): Array<{ key: string; name: string; message: string }> {
    return operation.paramGroups.flatMap((group) =>
        group.params.flatMap((param) => {
            const key = parameterKey(param);
            const message = parameterLimitation(param);

            return message === null ? [] : [{ key, name: param.name, message }];
        }),
    );
}

export function initialPlaygroundValues(operation: Operation, components: unknown): RequestValues {
    const values = initialRequestValues(operation, components);
    const parameters = { ...values.parameters };

    for (const param of operation.paramGroups.flatMap((group) => group.params)) {
        if (!param.required && parameterLimitation(param) !== null) {
            parameters[parameterKey(param)] = "";
        }
    }

    return { ...values, parameters };
}

export function isRenderableParameter(location: string, param: Param): boolean {
    return ["path", "query", "header"].includes(location) && parameterLimitation(param) === null;
}

function isInlineParameterGroup(location: string): boolean {
    return location === "path" || location === "query";
}

function parameterSchema(param: Param): Record<string, unknown> {
    return isRecord(param.schema) ? param.schema : {};
}

function parameterArrayOptions(schema: Record<string, unknown>): string[] {
    if (schema.type !== "array" || !isRecord(schema.items) || !Array.isArray(schema.items.enum)) {
        return [];
    }

    return schema.items.enum.filter((option): option is string => typeof option === "string");
}

function parameterInputType(schema: Record<string, unknown>): React.HTMLInputTypeAttribute {
    if (schema.type === "number" || schema.type === "integer") {
        return "number";
    }

    switch (schema.format) {
        case "email":
            return "email";
        case "uri":
        case "url":
            return "url";
        case "date":
            return "date";
        case "password":
            return "password";
        default:
            return "text";
    }
}

function parameterMinimum(schema: Record<string, unknown>): number | undefined {
    return numberValue(schema.minimum) ?? numberValue(schema.exclusiveMinimum);
}

function parameterMaximum(schema: Record<string, unknown>): number | undefined {
    return numberValue(schema.maximum) ?? numberValue(schema.exclusiveMaximum);
}

function parameterStep(schema: Record<string, unknown>): number | "any" | undefined {
    const multipleOf = numberValue(schema.multipleOf);

    if (multipleOf !== undefined) {
        return multipleOf;
    }

    if (schema.type === "integer") {
        return 1;
    }

    return schema.type === "number" ? "any" : undefined;
}

function numberValue(value: unknown): number | undefined {
    return typeof value === "number" && Number.isFinite(value) ? value : undefined;
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
