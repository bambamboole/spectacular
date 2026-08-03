import { FormFieldFrame } from "@lattice-php/lattice/form";
import { Button, Input, NativeSelect } from "@lattice-php/lattice/ui";
import {
    defaultRequestBodyValue,
    type RequestBodyScalar,
    type RequestBodySchema,
} from "./request-body-schema";

type RequestBodyEditorProps = {
    idPrefix: string;
    schema: RequestBodySchema;
    value: unknown;
    required: boolean;
    error: string | null;
    onChange: (value: unknown) => void;
};

export function RequestBodyEditor({
    idPrefix,
    schema,
    value,
    required,
    error,
    onChange,
}: RequestBodyEditorProps): React.ReactNode {
    return (
        <fieldset className="flex min-w-0 flex-col gap-3">
            <legend className="text-xs font-semibold uppercase tracking-wide text-lt-muted-fg">
                Request body{required ? <span className="ml-1 text-lt-danger">*</span> : null}
            </legend>
            <RequestBodyField
                idPrefix={idPrefix}
                label="body"
                schema={schema}
                value={value}
                required={required}
                onChange={onChange}
            />
            {error !== null ? <p className="text-sm text-lt-danger">{error}</p> : null}
        </fieldset>
    );
}

function RequestBodyField({
    idPrefix,
    label,
    schema,
    value,
    required,
    onChange,
}: {
    idPrefix: string;
    label: string;
    schema: RequestBodySchema;
    value: unknown;
    required: boolean;
    onChange: (value: unknown) => void;
}): React.ReactNode {
    if (schema.nullable && value === null) {
        return (
            <div className="flex items-center justify-between gap-3 rounded-lt-sm border border-lt-border p-3">
                <span className="font-mono text-sm text-lt-muted-fg">{label}: null</span>
                <Button type="button" emphasis="outline" onClick={() => onChange(nonNullDefaultValue(schema))}>
                    Set {label} value
                </Button>
            </div>
        );
    }

    if (schema.kind === "object") {
        const objectValue = isRecord(value) ? value : {};

        return (
            <fieldset className="flex min-w-0 flex-col gap-3 rounded-lt-sm border border-lt-border p-3">
                {label === "body" ? null : <legend className="px-1 font-mono text-sm text-lt-fg">{label}</legend>}
                {schema.description ? <p className="text-xs text-lt-muted-fg">{schema.description}</p> : null}
                {schema.properties.map((property) => {
                    const propertyLabel = label === "body" ? property.name : `${label}.${property.name}`;
                    const isIncluded = Object.hasOwn(objectValue, property.name);

                    if (!isIncluded) {
                        return (
                            <div
                                key={property.name}
                                className="flex items-center justify-between gap-3 rounded-lt-sm border border-dashed border-lt-border p-3"
                            >
                                <span className="font-mono text-sm text-lt-muted-fg">{propertyLabel}</span>
                                <Button
                                    type="button"
                                    emphasis="outline"
                                    onClick={() =>
                                        onChange({
                                            ...objectValue,
                                            [property.name]: defaultRequestBodyValue(property.schema),
                                        })
                                    }
                                >
                                    Add {propertyLabel}
                                </Button>
                            </div>
                        );
                    }

                    return (
                        <div key={property.name} className="flex min-w-0 flex-col gap-2">
                            <RequestBodyField
                                idPrefix={idPrefix}
                                label={propertyLabel}
                                schema={property.schema}
                                value={objectValue[property.name]}
                                required={property.required}
                                onChange={(propertyValue) =>
                                    onChange({ ...objectValue, [property.name]: propertyValue })
                                }
                            />
                            {!property.required ? (
                                <Button
                                    type="button"
                                    emphasis="outline"
                                    className="self-start"
                                    onClick={() => onChange(withoutProperty(objectValue, property.name))}
                                >
                                    Remove {propertyLabel}
                                </Button>
                            ) : null}
                        </div>
                    );
                })}
            </fieldset>
        );
    }

    if (schema.kind === "array") {
        const items = Array.isArray(value) ? value : [];

        return (
            <fieldset className="flex min-w-0 flex-col gap-3 rounded-lt-sm border border-lt-border p-3">
                <legend className="px-1 font-mono text-sm text-lt-fg">{label}</legend>
                {schema.description ? <p className="text-xs text-lt-muted-fg">{schema.description}</p> : null}
                {items.map((item, index) => (
                    <div key={index} className="flex min-w-0 flex-col gap-2 rounded-lt-sm bg-lt-muted/50 p-3">
                        <RequestBodyField
                            idPrefix={idPrefix}
                            label={`${label}[${index}]`}
                            schema={schema.items}
                            value={item}
                            required
                            onChange={(itemValue) => onChange(replaceArrayItem(items, index, itemValue))}
                        />
                        <Button
                            type="button"
                            emphasis="outline"
                            className="self-start"
                            onClick={() => onChange(items.filter((_, itemIndex) => itemIndex !== index))}
                        >
                            Remove {label}[{index}]
                        </Button>
                    </div>
                ))}
                <Button
                    type="button"
                    emphasis="outline"
                    className="self-start"
                    onClick={() => onChange([...items, defaultRequestBodyValue(schema.items)])}
                >
                    Add {label} item
                </Button>
            </fieldset>
        );
    }

    const id = `${idPrefix}-${fieldId(label)}`;

    return (
        <div className="flex min-w-0 flex-col gap-2">
            <FormFieldFrame
                id={id}
                label={label}
                required={required}
                helperText={schema.description}
            >
                {(controlProps) =>
                    schema.enum !== undefined ? (
                        <NativeSelect
                            {...controlProps}
                            value={scalarOptionValue(value)}
                            required={required}
                            data-field-key="body"
                            onChange={(event) => onChange(scalarOption(schema.enum ?? [], event.target.value))}
                        >
                            {schema.enum.map((option) => (
                                <option key={scalarOptionValue(option)} value={scalarOptionValue(option)}>
                                    {String(option)}
                                </option>
                            ))}
                        </NativeSelect>
                    ) : schema.kind === "boolean" ? (
                        <NativeSelect
                            {...controlProps}
                            value={String(value)}
                            required={required}
                            data-field-key="body"
                            onChange={(event) => onChange(event.target.value === "true")}
                        >
                            <option value="true">true</option>
                            <option value="false">false</option>
                        </NativeSelect>
                    ) : (
                        <Input
                            {...controlProps}
                            type={inputType(schema)}
                            value={inputValue(value)}
                            required={required}
                            min={schema.kind === "number" || schema.kind === "integer" ? schema.minimum : undefined}
                            max={schema.kind === "number" || schema.kind === "integer" ? schema.maximum : undefined}
                            step={inputStep(schema)}
                            minLength={schema.kind === "string" ? schema.minLength : undefined}
                            maxLength={schema.kind === "string" ? schema.maxLength : undefined}
                            pattern={schema.kind === "string" ? schema.pattern : undefined}
                            data-field-key="body"
                            onChange={(event) =>
                                onChange(
                                    schema.kind === "number" || schema.kind === "integer"
                                        ? numericInputValue(event.target.value)
                                        : event.target.value,
                                )
                            }
                        />
                    )
                }
            </FormFieldFrame>
            {schema.nullable ? (
                <Button type="button" emphasis="outline" className="self-start" onClick={() => onChange(null)}>
                    Set {label} to null
                </Button>
            ) : null}
        </div>
    );
}

function inputType(schema: Extract<RequestBodySchema, { kind: "string" | "number" | "integer" }>): React.HTMLInputTypeAttribute {
    if (schema.kind === "number" || schema.kind === "integer") {
        return "number";
    }

    const format = "format" in schema ? schema.format : undefined;

    switch (format) {
        case "date":
            return "date";
        case "date-time":
            return "datetime-local";
        case "email":
            return "email";
        case "uri":
        case "url":
            return "url";
        case "password":
            return "password";
        default:
            return "text";
    }
}

function inputStep(schema: Extract<RequestBodySchema, { kind: "number" | "integer" | "string" }>): number | "any" | undefined {
    if (schema.kind === "string") {
        return undefined;
    }

    return schema.multipleOf ?? (schema.kind === "integer" ? 1 : "any");
}

function nonNullDefaultValue(schema: RequestBodySchema): unknown {
    return defaultRequestBodyValue({ ...schema, nullable: false } as RequestBodySchema);
}

function scalarOption(options: RequestBodyScalar[], selected: string): RequestBodyScalar {
    return options.find((option) => scalarOptionValue(option) === selected) ?? null;
}

function scalarOptionValue(value: unknown): string {
    return value === null ? "null" : String(value);
}

function numericInputValue(value: string): number | string {
    if (value === "") {
        return "";
    }

    const number = Number(value);

    return Number.isFinite(number) ? number : value;
}

function inputValue(value: unknown): string | number {
    return typeof value === "string" || typeof value === "number" ? value : "";
}

function replaceArrayItem(items: unknown[], index: number, value: unknown): unknown[] {
    return items.map((item, itemIndex) => (itemIndex === index ? value : item));
}

function withoutProperty(value: Record<string, unknown>, property: string): Record<string, unknown> {
    return Object.fromEntries(Object.entries(value).filter(([name]) => name !== property));
}

function fieldId(value: string): string {
    return value.replaceAll(/[^a-zA-Z0-9_-]/g, "-");
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === "object" && value !== null && !Array.isArray(value);
}
