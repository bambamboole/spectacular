import { describe, expect, it } from "vitest";
import { httpMethodColor } from "./http-method-color";

describe("httpMethodColor", () => {
    it("maps GET to info", () => {
        expect(httpMethodColor("GET")).toBe("info");
    });

    it("maps POST to success", () => {
        expect(httpMethodColor("POST")).toBe("success");
    });

    it("maps PUT and PATCH to warning", () => {
        expect(httpMethodColor("PUT")).toBe("warning");
        expect(httpMethodColor("PATCH")).toBe("warning");
    });

    it("maps DELETE to danger", () => {
        expect(httpMethodColor("DELETE")).toBe("danger");
    });

    it("maps an unrecognized method to default", () => {
        expect(httpMethodColor("OPTIONS")).toBe("default");
    });
});
