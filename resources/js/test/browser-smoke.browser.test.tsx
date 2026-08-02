import { render } from "vitest-browser-react";
import { describe, expect, it } from "vitest";

describe("browser test setup", () => {
    it("renders React in Chromium", async () => {
        const screen = await render(<button type="button">Ready</button>);

        await expect.element(screen.getByRole("button", { name: "Ready" })).toBeVisible();
    });
});
