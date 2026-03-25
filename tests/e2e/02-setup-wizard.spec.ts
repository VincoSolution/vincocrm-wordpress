import { test, expect } from '@playwright/test';
import { wpLogin } from '../helpers/wp-admin';
import { mockWizardFlow, MOCK_WORKSPACE } from '../helpers/mock-crm-api';
import { SetupWizardPage } from '../fixtures/setup-wizard-page';

test.describe('Setup Wizard', () => {
    let wizard: SetupWizardPage;

    test.beforeEach(async ({ page }) => {
        await wpLogin(page);
        wizard = new SetupWizardPage(page);
    });

    test('wizard page loads with step 1 visible', async ({ page }) => {
        await wizard.goto();

        await expect(page.locator('.vincocrm-wizard')).toBeVisible();
        expect(await wizard.isStepVisible(1)).toBe(true);
        expect(await wizard.isStepVisible(2)).toBe(false);
        await wizard.expectStepActive(1);
    });

    test('step 1 validates required fields', async ({ page }) => {
        await wizard.goto();

        // Click login with empty fields.
        await wizard.clickLogin();

        await expect(wizard.loginMessage).toContainText('All fields are required');
    });

    test('step 1 → step 2: login shows workspaces', async ({ page }) => {
        await mockWizardFlow(page);
        await wizard.goto();

        await wizard.fillLoginStep('https://crm.test.com', 'admin@test.com', 'password123');
        await wizard.clickLogin();

        // Should move to step 2 with workspaces visible.
        expect(await wizard.isStepVisible(2)).toBe(true);
        await wizard.expectStepActive(2);

        // Workspace should be listed.
        await expect(wizard.workspaceList.locator('.vincocrm-workspace-item')).toHaveCount(1);
        await expect(wizard.workspaceList).toContainText(MOCK_WORKSPACE.name);
    });

    test('step 2: select workspace button disabled until selection', async ({ page }) => {
        await mockWizardFlow(page);
        await wizard.goto();

        await wizard.fillLoginStep('https://crm.test.com', 'admin@test.com', 'password123');
        await wizard.clickLogin();

        // Select workspace button should be disabled.
        await expect(wizard.selectWorkspaceBtn).toBeDisabled();

        // Click a workspace.
        await wizard.selectWorkspace(MOCK_WORKSPACE.name);

        // Button should be enabled now.
        await expect(wizard.selectWorkspaceBtn).toBeEnabled();
    });

    test('step 2 → step 3: workspace selection moves to widget', async ({ page }) => {
        await mockWizardFlow(page);
        await wizard.goto();

        await wizard.fillLoginStep('https://crm.test.com', 'admin@test.com', 'password123');
        await wizard.clickLogin();

        await wizard.selectWorkspace(MOCK_WORKSPACE.name);
        await wizard.clickSelectWorkspace();

        expect(await wizard.isStepVisible(3)).toBe(true);
        await wizard.expectStepActive(3);
    });

    test('step 3 → step 4: widget connect moves to WooCommerce', async ({ page }) => {
        await mockWizardFlow(page);
        await wizard.goto();

        // Complete steps 1-2.
        await wizard.fillLoginStep('https://crm.test.com', 'admin@test.com', 'password123');
        await wizard.clickLogin();
        await wizard.selectWorkspace(MOCK_WORKSPACE.name);
        await wizard.clickSelectWorkspace();

        // Step 3: connect widget.
        await wizard.clickConnectWidget();

        expect(await wizard.isStepVisible(4)).toBe(true);
        await wizard.expectStepActive(4);
    });

    test('full wizard flow ends with success screen', async ({ page }) => {
        await mockWizardFlow(page);
        await wizard.goto();

        // Step 1: Login.
        await wizard.fillLoginStep('https://crm.test.com', 'admin@test.com', 'password123');
        await wizard.clickLogin();

        // Step 2: Select workspace.
        await wizard.selectWorkspace(MOCK_WORKSPACE.name);
        await wizard.clickSelectWorkspace();

        // Step 3: Connect widget.
        await wizard.clickConnectWidget();

        // Step 4: Skip WooCommerce.
        await wizard.clickSkipWoo();

        // Complete screen should appear.
        await wizard.expectCompleteVisible();
        await expect(wizard.goToSettingsBtn).toBeVisible();
    });

    test('back buttons navigate to previous steps', async ({ page }) => {
        await mockWizardFlow(page);
        await wizard.goto();

        // Navigate to step 2.
        await wizard.fillLoginStep('https://crm.test.com', 'admin@test.com', 'password123');
        await wizard.clickLogin();
        expect(await wizard.isStepVisible(2)).toBe(true);

        // Go back to step 1.
        await wizard.backTo1Btn.click();
        expect(await wizard.isStepVisible(1)).toBe(true);
    });
});
