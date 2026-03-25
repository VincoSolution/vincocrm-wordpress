import { Page, expect } from '@playwright/test';

/**
 * WordPress admin helper utilities.
 */

export const WP_ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
export const WP_ADMIN_PASS = process.env.WP_ADMIN_PASS || 'password';

/**
 * Log in to WordPress admin.
 */
export async function wpLogin(page: Page, user = WP_ADMIN_USER, pass = WP_ADMIN_PASS): Promise<void> {
    await page.goto('/wp-login.php');
    await page.locator('#user_login').fill(user);
    await page.locator('#user_pass').fill(pass);
    await page.locator('#wp-submit').click();
    await page.waitForURL(/wp-admin/);
}

/**
 * Navigate to a WP admin page.
 */
export async function wpAdminPage(page: Page, path: string): Promise<void> {
    await page.goto(`/wp-admin/${path}`);
}

/**
 * Navigate to VincoCRM settings page.
 */
export async function goToVincoCRMSettings(page: Page, tab = ''): Promise<void> {
    const url = tab
        ? `/wp-admin/admin.php?page=vincocrm&tab=${tab}`
        : '/wp-admin/admin.php?page=vincocrm';
    await page.goto(url);
}

/**
 * Navigate to VincoCRM setup wizard.
 */
export async function goToSetupWizard(page: Page): Promise<void> {
    await page.goto('/wp-admin/admin.php?page=vincocrm-setup');
}

/**
 * Navigate to VincoCRM forms page.
 */
export async function goToFormsPage(page: Page): Promise<void> {
    await page.goto('/wp-admin/admin.php?page=vincocrm-forms');
}

/**
 * Check if a WordPress admin notice exists with specific text.
 */
export async function expectAdminNotice(page: Page, text: string, type: 'success' | 'error' | 'warning' | 'info' = 'success'): Promise<void> {
    const notice = page.locator(`.notice-${type}`);
    await expect(notice).toBeVisible();
    await expect(notice).toContainText(text);
}

/**
 * Activate the VincoCRM plugin via WP admin.
 */
export async function activatePlugin(page: Page): Promise<void> {
    await page.goto('/wp-admin/plugins.php');
    const pluginRow = page.locator('tr[data-plugin*="vincocrm"]');
    const activateLink = pluginRow.locator('a:has-text("Activate")');
    if (await activateLink.isVisible()) {
        await activateLink.click();
        await page.waitForLoadState('networkidle');
    }
}

/**
 * Deactivate the VincoCRM plugin via WP admin.
 */
export async function deactivatePlugin(page: Page): Promise<void> {
    await page.goto('/wp-admin/plugins.php');
    const pluginRow = page.locator('tr[data-plugin*="vincocrm"]');
    const deactivateLink = pluginRow.locator('a:has-text("Deactivate")');
    if (await deactivateLink.isVisible()) {
        await deactivateLink.click();
        await page.waitForLoadState('networkidle');
    }
}

/**
 * Wait for WP AJAX response.
 */
export async function waitForAjax(page: Page): Promise<void> {
    await page.waitForResponse(
        (response) => response.url().includes('admin-ajax.php') && response.status() === 200,
    );
}
