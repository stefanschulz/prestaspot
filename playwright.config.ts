import { defineConfig } from '@playwright/test';

/**
 * Runs against the live Docker dev stack (via the Traefik dev-proxy set up
 * for this project), not a server Playwright starts itself - there's no
 * clean "build and serve" step for a WordPress plugin, and the stack is
 * already how every fix in this project has been manually verified.
 */
export default defineConfig({
    testDir: 'tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    fullyParallel: false,
    // A single, shared WordPress+PrestaShop dev stack backs every spec -
    // running multiple spec files concurrently (Playwright's default across
    // files even with fullyParallel: false) contends for it and caused
    // intermittent login timeouts. One worker trades speed for reliability.
    workers: 1,
    retries: 0,
    reporter: 'list',
    use: {
        baseURL: 'http://prestaspot.localhost',
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { browserName: 'chromium' },
        },
    ],
});
