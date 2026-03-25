import { test, expect } from '@playwright/test';
import { wpLogin } from '../helpers/wp-admin';
import { mockFormBuilder } from '../helpers/mock-crm-api';
import { FormBuilderPage } from '../fixtures/form-builder-page';

test.describe('Form Builder', () => {
    let builder: FormBuilderPage;

    test.beforeEach(async ({ page }) => {
        await wpLogin(page);
        await mockFormBuilder(page);
        builder = new FormBuilderPage(page);
    });

    test('form builder page loads with empty state', async ({ page }) => {
        await builder.goto();

        await expect(builder.newFormBtn).toBeVisible();
        await expect(builder.fieldPalette).toBeVisible();
        await expect(builder.formList).toBeVisible();
    });

    test('creating a new form shows editor with default fields', async ({ page }) => {
        await builder.goto();

        await builder.createNewForm('Contact Form');
        await builder.expectEditorVisible();

        // Default fields: Name, Email, Subject, Message.
        const fieldCount = await builder.getCanvasFieldCount();
        expect(fieldCount).toBe(4);
    });

    test('saving a form shows shortcode', async ({ page }) => {
        await builder.goto();

        await builder.createNewForm('My Test Form');
        await builder.saveForm();

        // Wait for save response.
        await page.waitForTimeout(500);

        await builder.expectShortcodeVisible();
    });

    test('adding a field from palette increases canvas count', async ({ page }) => {
        await builder.goto();

        await builder.createNewForm('Test');

        const initialCount = await builder.getCanvasFieldCount();

        // Add a phone field.
        await builder.addFieldFromPalette('phone');

        const newCount = await builder.getCanvasFieldCount();
        expect(newCount).toBe(initialCount + 1);
    });

    test('removing a field decreases canvas count', async ({ page }) => {
        await builder.goto();

        await builder.createNewForm('Test');

        const initialCount = await builder.getCanvasFieldCount();
        expect(initialCount).toBeGreaterThan(0);

        await builder.removeCanvasField(0);

        const newCount = await builder.getCanvasFieldCount();
        expect(newCount).toBe(initialCount - 1);
    });

    test('editing a field label updates preview', async ({ page }) => {
        await builder.goto();

        await builder.createNewForm('Test');

        await builder.setFieldLabel(0, 'Full Name');

        // Wait for debounced preview update.
        await page.waitForTimeout(300);

        const preview = builder.formPreview;
        await expect(preview).toContainText('Full Name');
    });

    test('preview shows correct number of fields', async ({ page }) => {
        await builder.goto();

        await builder.createNewForm('Test');

        const canvasCount = await builder.getCanvasFieldCount();
        const previewCount = await builder.getPreviewFieldCount();

        expect(previewCount).toBe(canvasCount);
    });

    test('field palette shows all 6 field types', async ({ page }) => {
        await builder.goto();

        const paletteItems = builder.fieldPalette.locator('.vincocrm-palette-item');
        await expect(paletteItems).toHaveCount(6);
    });

    test('deleting a form returns to empty state', async ({ page }) => {
        await builder.goto();

        // Create and save a form first.
        await builder.createNewForm('Temporary Form');
        await builder.saveForm();
        await page.waitForTimeout(500);

        // Delete it.
        await builder.deleteForm();
        await page.waitForTimeout(500);

        await builder.expectEmptyState();
    });
});
