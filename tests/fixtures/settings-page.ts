import { Page, Locator, expect } from '@playwright/test';

/**
 * Page Object for VincoCRM Settings page.
 */
export class SettingsPage {
    readonly page: Page;

    // Status bar
    readonly statusBar: Locator;
    readonly testConnectionBtn: Locator;
    readonly disconnectBtn: Locator;

    // Tabs
    readonly tabNav: Locator;

    constructor(page: Page) {
        this.page = page;
        this.statusBar = page.locator('.vincocrm-status');
        this.testConnectionBtn = page.locator('.vincocrm-test-connection');
        this.disconnectBtn = page.locator('.vincocrm-disconnect');
        this.tabNav = page.locator('.vincocrm-tabs');
    }

    async goto(tab = ''): Promise<void> {
        const url = tab
            ? `/wp-admin/admin.php?page=vincocrm&tab=${tab}`
            : '/wp-admin/admin.php?page=vincocrm';
        await this.page.goto(url);
    }

    async clickTab(tabKey: string): Promise<void> {
        await this.tabNav.locator(`a[href*="tab=${tabKey}"]`).click();
    }

    async isConnected(): Promise<boolean> {
        return await this.page.locator('.vincocrm-status--connected').isVisible();
    }

    async expectConnected(): Promise<void> {
        await expect(this.page.locator('.vincocrm-status--connected')).toBeVisible();
    }

    async expectDisconnected(): Promise<void> {
        await expect(this.page.locator('.vincocrm-status--disconnected')).toBeVisible();
    }

    // Widget tab
    async setWidgetEnabled(enabled: boolean): Promise<void> {
        const checkbox = this.page.locator('input[name="widget_enabled"]');
        if (enabled) {
            await checkbox.check();
        } else {
            await checkbox.uncheck();
        }
    }

    async setWidgetDisplay(value: string): Promise<void> {
        await this.page.locator('select[name="widget_display"]').selectOption(value);
    }

    // Contact sync tab
    async setContactSyncEnabled(enabled: boolean): Promise<void> {
        const checkbox = this.page.locator('input[name="contact_sync_enabled"]');
        if (enabled) {
            await checkbox.check();
        } else {
            await checkbox.uncheck();
        }
    }

    // Save
    async saveSettings(): Promise<void> {
        await this.page.locator('.vincocrm-save-settings').click();
    }

    async expectSaveSuccess(): Promise<void> {
        await expect(this.page.locator('.vincocrm-notice.notice-success')).toBeVisible({ timeout: 5000 });
    }
}
