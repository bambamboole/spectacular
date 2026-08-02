import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";
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
});
