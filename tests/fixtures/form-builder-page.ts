import { Page, Locator, expect } from '@playwright/test';

/**
 * Page Object for VincoCRM Form Builder.
 */
export class FormBuilderPage {
    readonly page: Page;

    // Sidebar
    readonly newFormBtn: Locator;
    readonly formList: Locator;
    readonly fieldPalette: Locator;

    // Editor
    readonly formEditor: Locator;
    readonly formEmpty: Locator;
    readonly formTitle: Locator;
    readonly saveFormBtn: Locator;
    readonly deleteFormBtn: Locator;
    readonly duplicateFormBtn: Locator;
    readonly shortcodeDisplay: Locator;

    // Canvas
    readonly builderCanvas: Locator;

    // Preview
    readonly formPreview: Locator;

    constructor(page: Page) {
        this.page = page;

        this.newFormBtn = page.locator('#vincocrm-new-form');
        this.formList = page.locator('#vincocrm-form-list');
        this.fieldPalette = page.locator('#vincocrm-field-palette');

        this.formEditor = page.locator('#vincocrm-form-editor');
        this.formEmpty = page.locator('#vincocrm-form-empty');
        this.formTitle = page.locator('#vincocrm-form-title');
        this.saveFormBtn = page.locator('#vincocrm-save-form');
        this.deleteFormBtn = page.locator('#vincocrm-delete-form');
        this.duplicateFormBtn = page.locator('#vincocrm-duplicate-form');
        this.shortcodeDisplay = page.locator('#vincocrm-form-shortcode');

        this.builderCanvas = page.locator('#vincocrm-builder-canvas');
        this.formPreview = page.locator('#vincocrm-form-preview');
    }

    async goto(): Promise<void> {
        await this.page.goto('/wp-admin/admin.php?page=vincocrm-forms');
    }

    async createNewForm(title: string): Promise<void> {
        await this.newFormBtn.click();
        await expect(this.formEditor).toBeVisible();
        await this.formTitle.fill(title);
    }

    async saveForm(): Promise<void> {
        await this.saveFormBtn.click();
    }

    async deleteForm(): Promise<void> {
        this.page.once('dialog', (dialog) => dialog.accept());
        await this.deleteFormBtn.click();
    }

    async duplicateForm(): Promise<void> {
        await this.duplicateFormBtn.click();
    }

    async addFieldFromPalette(fieldType: string): Promise<void> {
        const paletteItem = this.fieldPalette.locator(`.vincocrm-palette-item[data-type="${fieldType}"]`);
        await paletteItem.click();
    }

    async getCanvasFieldCount(): Promise<number> {
        return await this.builderCanvas.locator('.vincocrm-canvas-field').count();
    }

    async getPreviewFieldCount(): Promise<number> {
        return await this.formPreview.locator('.vincocrm-form-field').count();
    }

    async removeCanvasField(index: number): Promise<void> {
        const field = this.builderCanvas.locator('.vincocrm-canvas-field').nth(index);
        await field.locator('[data-action="remove"]').click();
    }

    async setFieldLabel(index: number, label: string): Promise<void> {
        const field = this.builderCanvas.locator('.vincocrm-canvas-field').nth(index);
        const labelInput = field.locator('input[data-prop="label"]');
        await labelInput.clear();
        await labelInput.fill(label);
    }

    async expectEditorVisible(): Promise<void> {
        await expect(this.formEditor).toBeVisible();
    }

    async expectEmptyState(): Promise<void> {
        await expect(this.formEmpty).toBeVisible();
        await expect(this.formEditor).not.toBeVisible();
    }

    async expectShortcodeVisible(): Promise<void> {
        await expect(this.shortcodeDisplay).toBeVisible();
        const text = await this.shortcodeDisplay.textContent();
        expect(text).toMatch(/\[vincocrm_form id="[^"]+"\]/);
    }

    async selectFormFromList(title: string): Promise<void> {
        const item = this.formList.locator('.vincocrm-form-list-item', { hasText: title });
        await item.click();
    }
}
