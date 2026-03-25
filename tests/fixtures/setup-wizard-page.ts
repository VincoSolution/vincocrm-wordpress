import { Page, Locator, expect } from '@playwright/test';

/**
 * Page Object for VincoCRM Setup Wizard.
 */
export class SetupWizardPage {
    readonly page: Page;

    // Progress steps
    readonly stepIndicators: Locator;

    // Step 1: Login
    readonly apiUrlInput: Locator;
    readonly emailInput: Locator;
    readonly passwordInput: Locator;
    readonly mfaCodeInput: Locator;
    readonly mfaField: Locator;
    readonly loginBtn: Locator;
    readonly loginMessage: Locator;

    // Step 2: Workspace
    readonly workspaceList: Locator;
    readonly selectWorkspaceBtn: Locator;
    readonly workspaceMessage: Locator;

    // Step 3: Widget
    readonly connectWidgetBtn: Locator;
    readonly widgetMessage: Locator;

    // Step 4: WooCommerce
    readonly connectWooBtn: Locator;
    readonly skipWooBtn: Locator;
    readonly wooMessage: Locator;

    // Complete
    readonly completePanel: Locator;
    readonly goToSettingsBtn: Locator;

    // Back buttons
    readonly backTo1Btn: Locator;
    readonly backTo2Btn: Locator;
    readonly backTo3Btn: Locator;

    constructor(page: Page) {
        this.page = page;

        this.stepIndicators = page.locator('.vincocrm-wizard__step');

        // Step 1
        this.apiUrlInput = page.locator('#vincocrm-api-url');
        this.emailInput = page.locator('#vincocrm-email');
        this.passwordInput = page.locator('#vincocrm-password');
        this.mfaCodeInput = page.locator('#vincocrm-mfa-code');
        this.mfaField = page.locator('.vincocrm-mfa-field');
        this.loginBtn = page.locator('#vincocrm-login-btn');
        this.loginMessage = page.locator('#vincocrm-login-message');

        // Step 2
        this.workspaceList = page.locator('#vincocrm-workspaces-list');
        this.selectWorkspaceBtn = page.locator('#vincocrm-select-workspace-btn');
        this.workspaceMessage = page.locator('#vincocrm-workspace-message');

        // Step 3
        this.connectWidgetBtn = page.locator('#vincocrm-connect-widget-btn');
        this.widgetMessage = page.locator('#vincocrm-widget-message');

        // Step 4
        this.connectWooBtn = page.locator('#vincocrm-connect-woo-btn');
        this.skipWooBtn = page.locator('#vincocrm-skip-woo-btn');
        this.wooMessage = page.locator('#vincocrm-woo-message');

        // Complete
        this.completePanel = page.locator('#vincocrm-step-complete');
        this.goToSettingsBtn = this.completePanel.locator('a:has-text("Go to Settings")');

        // Back
        this.backTo1Btn = page.locator('#vincocrm-back-to-1');
        this.backTo2Btn = page.locator('#vincocrm-back-to-2');
        this.backTo3Btn = page.locator('#vincocrm-back-to-3');
    }

    async goto(): Promise<void> {
        await this.page.goto('/wp-admin/admin.php?page=vincocrm-setup');
    }

    async fillLoginStep(apiUrl: string, email: string, password: string): Promise<void> {
        await this.apiUrlInput.fill(apiUrl);
        await this.emailInput.fill(email);
        await this.passwordInput.fill(password);
    }

    async clickLogin(): Promise<void> {
        await this.loginBtn.click();
    }

    async selectWorkspace(name: string): Promise<void> {
        const wsItem = this.workspaceList.locator('.vincocrm-workspace-item', { hasText: name });
        await wsItem.click();
    }

    async clickSelectWorkspace(): Promise<void> {
        await this.selectWorkspaceBtn.click();
    }

    async clickConnectWidget(): Promise<void> {
        await this.connectWidgetBtn.click();
    }

    async clickSkipWoo(): Promise<void> {
        await this.skipWooBtn.click();
    }

    async clickConnectWoo(): Promise<void> {
        await this.connectWooBtn.click();
    }

    async isStepVisible(step: number | string): Promise<boolean> {
        const panel = this.page.locator(`#vincocrm-step-${step}`);
        return await panel.isVisible();
    }

    async expectStepActive(step: number): Promise<void> {
        const stepEl = this.page.locator(`.vincocrm-wizard__step[data-step="${step}"]`);
        await expect(stepEl).toHaveClass(/active/);
    }

    async expectCompleteVisible(): Promise<void> {
        await expect(this.completePanel).toBeVisible();
    }
}
