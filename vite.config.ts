import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import { playwright } from "@vitest/browser-playwright";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vitest/config";
import { svgSprite } from "@lattice-php/vite-svg-sprite";

export default defineConfig({
    plugins: [
        laravel({
            input: ["workbench/resources/css/app.css", "workbench/resources/js/app.tsx"],
            publicDirectory: "vendor/orchestra/testbench-core/laravel/public",
            buildDirectory: "build",
            refresh: true,
        }),
        react(),
        tailwindcss(),
        svgSprite({
            iconDirs: ["node_modules/@lattice-php/lattice/resources/icons"],
        }),
    ],
    resolve: {
        dedupe: ["react", "react-dom", "@inertiajs/react", "@lattice-php/lattice"],
    },
    test: {
        projects: [
            {
                extends: true,
                test: {
                    name: "node",
                    environment: "node",
                    include: ["resources/js/**/*.test.{ts,tsx}"],
                    exclude: ["resources/js/**/*.browser.test.{ts,tsx}"],
                },
            },
            {
                extends: true,
                test: {
                    name: "browser",
                    include: ["resources/js/**/*.browser.test.tsx"],
                    setupFiles: ["resources/js/test/browser-setup.ts"],
                    browser: {
                        enabled: true,
                        provider: playwright(),
                        headless: true,
                        locators: {
                            testIdAttribute: "data-test",
                        },
                        viewport: {
                            width: 1280,
                            height: 800,
                        },
                        instances: [{ browser: "chromium" }],
                    },
                },
            },
        ],
    },
});
