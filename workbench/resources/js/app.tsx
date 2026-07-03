import "../css/app.css";
import { createInertiaApp } from "@inertiajs/react";
import {
    createLayoutResolver,
    createPageResolver,
    extendRegistry,
    Provider,
    registry,
} from "@lattice-php/lattice";
import { createRoot } from "react-dom/client";
import { spectacularComponents } from "../../../resources/js/plugin";

const appRegistry = extendRegistry(registry, spectacularComponents);

createInertiaApp({
    resolve: createPageResolver({}),
    layout: createLayoutResolver(),
    setup({ el, App, props }) {
        if (!el) {
            return;
        }
        createRoot(el).render(
            <Provider registry={appRegistry}>
                <App {...props} />
            </Provider>,
        );
    },
});
