import "../css/app.css";
import { createInertiaApp } from "@inertiajs/react";
import {
    createLayoutResolver,
    createPageResolver,
    Provider,
    registry,
} from "@lattice-php/lattice";
import { createRoot } from "react-dom/client";

createInertiaApp({
    resolve: createPageResolver({}),
    layout: createLayoutResolver(),
    setup({ el, App, props }) {
        if (!el) {
            return;
        }
        createRoot(el).render(
            <Provider registry={registry}>
                <App {...props} />
            </Provider>,
        );
    },
});
