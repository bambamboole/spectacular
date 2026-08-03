import type { CodeBlockLanguageLoader } from "@lattice-php/lattice/ui";

export const javascriptWithLineNumbers: CodeBlockLanguageLoader = async () => {
    const [{ javascript }, { lineNumbers }] = await Promise.all([
        import("@codemirror/lang-javascript"),
        import("@codemirror/view"),
    ]);

    return [javascript(), lineNumbers()];
};

export const jsonWithLineNumbers: CodeBlockLanguageLoader = async () => {
    const [{ json }, { lineNumbers }] = await Promise.all([
        import("@codemirror/lang-json"),
        import("@codemirror/view"),
    ]);

    return [json(), lineNumbers()];
};

export const shellWithLineNumbers: CodeBlockLanguageLoader = async () => {
    const [{ StreamLanguage }, { shell }, { lineNumbers }] = await Promise.all([
        import("@codemirror/language"),
        import("@codemirror/legacy-modes/mode/shell"),
        import("@codemirror/view"),
    ]);

    return [StreamLanguage.define(shell), lineNumbers()];
};
