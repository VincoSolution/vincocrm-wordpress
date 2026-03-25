import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright config for VincoCRM WordPress plugin E2E tests.
 *
 * Expects a WordPress + WooCommerce instance running via Docker.
 * Set WP_BASE_URL env var or defaults to http://localhost:8080.
 */
export default defineConfig({
    testDir: './e2e',
    timeout: 60_000,
    expect: {
        timeout: 10_000,
    },
    fullyParallel: false, // WP admin state is shared; run sequentially.
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1, // Single worker — WP plugin state is global.
    reporter: [
        ['html', { open: 'never' }],
        ['list'],
    ],
    use: {
        baseURL: process.env.WP_BASE_URL || 'http://localhost:8080',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        actionTimeout: 15_000,
        navigationTimeout: 30_000,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
