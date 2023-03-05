/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// Bootstrap from node_modules
import 'bootstrap';

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.scss';

import * as Sentry from "@sentry/browser";
import { BrowserTracing } from "@sentry/tracing";

if (APP_CONFIG.sentry_dsn !== null) {
    Sentry.init({
        dsn: APP_CONFIG.sentry_dsn,
        environment: APP_CONFIG.environment,
        // release: APP_CONFIG.sentry_release,
        tunnel: APP_CONFIG.sentry_tunnel,

        integrations: [new BrowserTracing()],

        // Set tracesSampleRate to 1.0 to capture 100%
        // of transactions for performance monitoring.
        // We recommend adjusting this value in production
        tracesSampleRate: 1.0,
    });

    Sentry.setUser(APP_CONFIG.sentry_user);
}

// start the Stimulus application
import './bootstrap';
