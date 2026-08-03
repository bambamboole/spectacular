import { CopyButton, SegmentedPills } from "@lattice-php/lattice/ui";

export type SnippetLanguage = "curl" | "javascript";

type SnippetPanelProps = {
    idPrefix: string;
    language: SnippetLanguage;
    snippet: string;
    onLanguageChange: (language: SnippetLanguage) => void;
};

const SNIPPET_LANGUAGES = [
    { label: "cURL", value: "curl", data: null },
    { label: "JavaScript", value: "javascript", data: null },
];

export function SnippetPanel({ idPrefix, language, snippet, onLanguageChange }: SnippetPanelProps): React.ReactNode {
    return (
        <section className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <SegmentedPills
                    name={`${idPrefix}-request-snippet-language`}
                    ariaLabel="Snippet language"
                    options={SNIPPET_LANGUAGES}
                    value={language}
                    onSelect={(value) => onLanguageChange(value as SnippetLanguage)}
                />
                <CopyButton
                    value={snippet}
                    label="request snippet"
                    testId={`request-snippet-copy-${idPrefix}`}
                />
            </div>
            <pre
                aria-label="Request snippet"
                className="max-w-full overflow-x-auto rounded-lt-sm bg-lt-muted p-3 text-xs text-lt-fg"
            >
                {snippet}
            </pre>
        </section>
    );
}
