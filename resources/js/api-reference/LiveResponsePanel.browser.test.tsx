import { describe, expect, it } from "vitest";
import { render } from "vitest-browser-react";
import { LiveResponsePanel } from "./LiveResponsePanel";

describe("LiveResponsePanel", () => {
    it("scrolls response bodies longer than 150 lines", async () => {
        await render(
            <LiveResponsePanel
                result={{
                    kind: "response",
                    status: 200,
                    statusText: "OK",
                    durationMs: 12,
                    headers: [],
                    body: Array.from({ length: 151 }, (_, index) => `Line ${index + 1}`).join("\n"),
                    contentType: "text/plain",
                }}
            />,
        );

        await expect
            .poll(() => {
                const scroller = document.querySelector<HTMLElement>(".cm-scroller");

                return scroller !== null && scroller.scrollHeight > scroller.clientHeight;
            })
            .toBe(true);
    });
});
