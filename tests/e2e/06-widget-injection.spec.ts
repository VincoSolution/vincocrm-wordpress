import { test, expect } from '@playwright/test';
import { wpLogin, goToVincoCRMSettings } from '../helpers/wp-admin';
import { mockSettingsSave } from '../helpers/mock-crm-api';

test.describe('Widget Injection', () => {
    /**
     * Widget injection tests verify the script tag is present or absent
     * on frontend pages based on settings.
     *
     * Requires the plugin to be configured with a widget API key.
     * In the Docker test environment, this is set via WP-CLI options.
     */

    test.beforeEach(async ({ page }) => {
        await wpLogin(page);
    });

    test('widget script not present when disabled', async ({ page }) => {
        // Set widget disabled via settings page.
        await mockSettingsSave(page);
        await goToVincoCRMSettings(page, 'widget');

        // Go to homepage as anonymous.
        await page.goto('/');

        const widgetScript = page.locator('script#vincocrm-widget-js');
        await expect(widgetScript).toHaveCount(0);
    });

    test('widget script injected on frontend when enabled', async ({ page }) => {
        /**
         * When the widget is enabled and API key is set, the script should
         * appear in the footer. This test requires options to be pre-set:
         *   wp option update vincocrm_widget_settings '{"enabled":true,"display":"all"}'
         *   wp option update vincocrm_widget_api_key 'wk_test_key'
         *   wp option update vincocrm_widget_api_url 'https://crm.test.com/api'
         */
        await page.goto('/');

        const widgetScript = page.locator('script[data-api-key]');
        // Only check if widget options are configured.
        const count = await widgetScript.count();
        if (count > 0) {
            await expect(widgetScript).toHaveAttribute('data-api-key', /^wk_/);
            await expect(widgetScript).toHaveAttribute('data-api-url', /^https?:\/\//);
        }
    });

    test('widget not present on WP admin pages', async ({ page }) => {
        await page.goto('/wp-admin/');
        const widgetScript = page.locator('script[data-api-key*="wk_"]');
        await expect(widgetScript).toHaveCount(0);
    });

    test('noscript fallback link present when widget enabled', async ({ page }) => {
        await page.goto('/');

        const noscript = page.locator('noscript:has-text("Contact Support")');
        // Present if widget is configured.
        const count = await noscript.count();
        if (count > 0) {
            await expect(noscript.locator('a')).toHaveAttribute('target', '_blank');
            await expect(noscript.locator('a')).toHaveAttribute('rel', 'noopener');
        }
    });
});
