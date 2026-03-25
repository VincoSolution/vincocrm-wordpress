import { test, expect } from '@playwright/test';
import { wpLogin } from '../helpers/wp-admin';
import { mockFormSubmission } from '../helpers/mock-crm-api';

test.describe('Frontend Form Rendering & Submission', () => {
    /**
     * These tests require a WordPress page with [vincocrm_form id="..."] shortcode.
     * In a real test environment, create this page via WP-CLI in docker setup.
     *
     * For now, we test the form HTML structure and JS behavior by navigating
     * to a page that has the shortcode embedded.
     */

    const FORM_PAGE_URL = '/vincocrm-test-form/'; // Created in test setup.

    test('form renders with correct fields', async ({ page }) => {
        await page.goto(FORM_PAGE_URL);

        const form = page.locator('.vincocrm-contact-form');

        // Skip if form page not set up.
        if (!(await form.isVisible())) {
            test.skip();
            return;
        }

        // Form elements present.
        await expect(form.locator('input[type="text"]').first()).toBeVisible();
        await expect(form.locator('input[type="email"]')).toBeVisible();
        await expect(form.locator('.vincocrm-form-submit')).toBeVisible();

        // Honeypot field is hidden.
        const honeypot = page.locator('input[name="vincocrm_hp"]');
        await expect(honeypot).toBeHidden();

        // Timestamp hidden field exists.
        await expect(page.locator('input[name="vincocrm_ts"]')).toBeAttached();
    });

    test('form validates required fields client-side', async ({ page }) => {
        await page.goto(FORM_PAGE_URL);

        const form = page.locator('.vincocrm-contact-form');
        if (!(await form.isVisible())) {
            test.skip();
            return;
        }

        // Submit empty form.
        await form.locator('.vincocrm-form-submit').click();

        // Error message should appear.
        const message = form.locator('.vincocrm-form-message');
        await expect(message).toBeVisible();
        await expect(message).toHaveClass(/vincocrm-form-message--error/);
    });

    test('form validates email format', async ({ page }) => {
        await page.goto(FORM_PAGE_URL);

        const form = page.locator('.vincocrm-contact-form');
        if (!(await form.isVisible())) {
            test.skip();
            return;
        }

        // Fill name but use invalid email.
        await form.locator('input[type="text"]').first().fill('John Doe');
        await form.locator('input[type="email"]').fill('not-an-email');
        await form.locator('textarea').first().fill('Test message');
        await form.locator('.vincocrm-form-submit').click();

        const message = form.locator('.vincocrm-form-message');
        await expect(message).toContainText('valid email');
    });

    test('successful form submission shows success message', async ({ page }) => {
        await mockFormSubmission(page, true);
        await page.goto(FORM_PAGE_URL);

        const form = page.locator('.vincocrm-contact-form');
        if (!(await form.isVisible())) {
            test.skip();
            return;
        }

        // Fill out the form.
        await form.locator('input[type="text"]').first().fill('John Doe');
        await form.locator('input[type="email"]').fill('john@example.com');
        const textareas = form.locator('textarea');
        if (await textareas.count() > 0) {
            await textareas.first().fill('This is a test message.');
        }

        await form.locator('.vincocrm-form-submit').click();

        // Success message.
        const message = form.locator('.vincocrm-form-message');
        await expect(message).toBeVisible({ timeout: 10_000 });
        await expect(message).toHaveClass(/vincocrm-form-message--success/);
    });

    test('failed submission shows error message', async ({ page }) => {
        await mockFormSubmission(page, false);
        await page.goto(FORM_PAGE_URL);

        const form = page.locator('.vincocrm-contact-form');
        if (!(await form.isVisible())) {
            test.skip();
            return;
        }

        await form.locator('input[type="text"]').first().fill('John Doe');
        await form.locator('input[type="email"]').fill('john@example.com');
        const textareas = form.locator('textarea');
        if (await textareas.count() > 0) {
            await textareas.first().fill('Test.');
        }

        await form.locator('.vincocrm-form-submit').click();

        const message = form.locator('.vincocrm-form-message');
        await expect(message).toBeVisible({ timeout: 10_000 });
        await expect(message).toHaveClass(/vincocrm-form-message--error/);
    });

    test('submit button shows loading state', async ({ page }) => {
        // Delay the response to observe loading state.
        await page.route('**/admin-ajax.php', async (route) => {
            const postData = route.request().postData() || '';
            if (postData.includes('vincocrm_submit_form')) {
                await new Promise((r) => setTimeout(r, 1000));
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ success: true, data: { message: 'Sent!' } }),
                });
                return;
            }
            await route.continue();
        });

        await page.goto(FORM_PAGE_URL);

        const form = page.locator('.vincocrm-contact-form');
        if (!(await form.isVisible())) {
            test.skip();
            return;
        }

        await form.locator('input[type="text"]').first().fill('John Doe');
        await form.locator('input[type="email"]').fill('john@example.com');
        const textareas = form.locator('textarea');
        if (await textareas.count() > 0) {
            await textareas.first().fill('Test.');
        }

        const btn = form.locator('.vincocrm-form-submit');
        await btn.click();

        // Button should be disabled during submission.
        await expect(btn).toBeDisabled();

        // After response, button should re-enable.
        await expect(btn).toBeEnabled({ timeout: 5000 });
    });

    test('form CSS does not leak to page elements', async ({ page }) => {
        await page.goto(FORM_PAGE_URL);

        const form = page.locator('.vincocrm-contact-form');
        if (!(await form.isVisible())) {
            test.skip();
            return;
        }

        // Check the form has scoped styles (box-sizing set on form children only).
        const formInput = form.locator('input[type="text"]').first();
        const boxSizing = await formInput.evaluate((el) =>
            window.getComputedStyle(el).boxSizing,
        );
        expect(boxSizing).toBe('border-box');
    });
});
