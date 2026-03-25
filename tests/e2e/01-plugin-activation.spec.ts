import { test, expect } from '@playwright/test';
import { wpLogin, activatePlugin, deactivatePlugin } from '../helpers/wp-admin';

test.describe('Plugin Activation/Deactivation', () => {
    test.beforeEach(async ({ page }) => {
        await wpLogin(page);
    });

    test('plugin activates without PHP errors', async ({ page }) => {
        await activatePlugin(page);

        // No fatal error — page loads normally.
        await expect(page.locator('#wpbody')).toBeVisible();

        // Plugin row shows "Deactivate" link (meaning it's active).
        await page.goto('/wp-admin/plugins.php');
        const pluginRow = page.locator('tr[data-plugin*="vincocrm"]');
        await expect(pluginRow.locator('a:has-text("Deactivate")')).toBeVisible();
    });

    test('VincoCRM menu appears in admin sidebar', async ({ page }) => {
        await page.goto('/wp-admin/');
        const menu = page.locator('#adminmenu a:has-text("VincoCRM")');
        await expect(menu).toBeVisible();
    });

    test('setup notice appears for new installations', async ({ page }) => {
        await page.goto('/wp-admin/');
        const notice = page.locator('.notice:has-text("Welcome to VincoCRM")');
        await expect(notice).toBeVisible();
        await expect(notice.locator('a:has-text("Start Setup")')).toBeVisible();
    });

    test('plugin deactivates cleanly', async ({ page }) => {
        await deactivatePlugin(page);

        // No errors — page loads.
        await expect(page.locator('#wpbody')).toBeVisible();

        // Plugin row shows "Activate" link.
        const pluginRow = page.locator('tr[data-plugin*="vincocrm"]');
        await expect(pluginRow.locator('a:has-text("Activate")')).toBeVisible();

        // Re-activate for subsequent tests.
        await activatePlugin(page);
    });

    test('settings link appears on plugins page', async ({ page }) => {
        await page.goto('/wp-admin/plugins.php');
        const pluginRow = page.locator('tr[data-plugin*="vincocrm"]');
        const settingsLink = pluginRow.locator('a:has-text("Settings")');
        await expect(settingsLink).toBeVisible();

        await settingsLink.click();
        await expect(page).toHaveURL(/page=vincocrm/);
    });
});
