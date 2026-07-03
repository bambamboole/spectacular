import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ["workbench/resources/css/app.css", "workbench/resources/js/app.tsx"],
            publicDirectory: "workbench/public",
            buildDirectory: "build",
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        dedupe: ["react", "react-dom", "@inertiajs/react", "@lattice-php/lattice"],
    },
});
