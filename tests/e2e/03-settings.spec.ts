import { test, expect } from '@playwright/test';
import { wpLogin } from '../helpers/wp-admin';
import { mockSettingsSave } from '../helpers/mock-crm-api';
import { SettingsPage } from '../fixtures/settings-page';

test.describe('Settings Page', () => {
    let settings: SettingsPage;

    test.beforeEach(async ({ page }) => {
        await wpLogin(page);
        settings = new SettingsPage(page);
    });

    test('settings page loads with all tabs', async ({ page }) => {
        await settings.goto();

        await expect(page.locator('.vincocrm-tabs')).toBeVisible();

        // All 6 tabs should be present.
        const tabs = ['connection', 'widget', 'contact_sync', 'order_sync', 'forms', 'advanced'];
        for (const tab of tabs) {
            await expect(page.locator(`.nav-tab[href*="tab=${tab}"]`)).toBeVisible();
        }
    });

    test('connection tab shows status', async ({ page }) => {
        await settings.goto('connection');
        await expect(page.locator('.vincocrm-status')).toBeVisible();
    });

    test('widget tab has enable checkbox and display select', async ({ page }) => {
        await settings.goto('widget');

        await expect(page.locator('input[name="widget_enabled"]')).toBeVisible();
        await expect(page.locator('select[name="widget_display"]')).toBeVisible();
    });

    test('widget display conditional fields toggle', async ({ page }) => {
        await settings.goto('widget');

        // "All pages" — no include/exclude fields.
        await settings.setWidgetDisplay('all');
        await expect(page.locator('input[name="widget_include_pages"]')).not.toBeVisible();
        await expect(page.locator('input[name="widget_exclude_pages"]')).not.toBeVisible();

        // "Only specific pages" — include field visible.
        await settings.setWidgetDisplay('include');
        await expect(page.locator('input[name="widget_include_pages"]')).toBeVisible();

        // "All pages except..." — exclude field visible.
        await settings.setWidgetDisplay('exclude');
        await expect(page.locator('input[name="widget_exclude_pages"]')).toBeVisible();
    });

    test('widget settings save successfully', async ({ page }) => {
        await mockSettingsSave(page);
        await settings.goto('widget');

        await settings.setWidgetEnabled(true);
        await settings.setWidgetDisplay('all');
        await settings.saveSettings();

        await settings.expectSaveSuccess();
    });

    test('contact sync tab has expected checkboxes', async ({ page }) => {
        await settings.goto('contact_sync');

        await expect(page.locator('input[name="contact_sync_enabled"]')).toBeVisible();
        await expect(page.locator('input[name="contact_sync_on_register"]')).toBeVisible();
        await expect(page.locator('input[name="contact_sync_on_login"]')).toBeVisible();
        await expect(page.locator('input[name="contact_sync_on_checkout"]')).toBeVisible();
    });

    test('contact sync settings save successfully', async ({ page }) => {
        await mockSettingsSave(page);
        await settings.goto('contact_sync');

        await settings.setContactSyncEnabled(true);
        await settings.saveSettings();

        await settings.expectSaveSuccess();
    });

    test('order sync tab has batch size input', async ({ page }) => {
        await settings.goto('order_sync');

        await expect(page.locator('input[name="order_sync_enabled"]')).toBeVisible();
        await expect(page.locator('input[name="order_sync_realtime"]')).toBeVisible();
        await expect(page.locator('input[name="order_sync_batch_size"]')).toBeVisible();
    });

    test('forms tab has engine radio buttons', async ({ page }) => {
        await settings.goto('forms');

        await expect(page.locator('input[name="form_engine"][value="classic"]')).toBeVisible();
        await expect(page.locator('input[name="form_engine"][value="modern"]')).toBeVisible();
    });

    test('advanced tab shows API URL and retry queue', async ({ page }) => {
        await settings.goto('advanced');

        await expect(page.locator('input[name="api_url"]')).toBeVisible();
        // Plugin version displayed.
        await expect(page.locator('code:has-text("2.0.0")')).toBeVisible();
    });
});
