import { Page, Route } from '@playwright/test';

/**
 * Mock CRM API responses for E2E tests.
 *
 * Intercepts AJAX calls to admin-ajax.php and returns simulated CRM responses.
 * This allows testing without a running VincoCRM instance.
 */

export const MOCK_WORKSPACE = {
    id: 'ws_test_12345',
    name: 'Test Store',
    role: 'OWNER',
};

export const MOCK_WIDGET_CONFIG = {
    apiKey: 'wk_test_mock_api_key_000000000000000000000000',
    isEnabled: true,
    primaryColor: '#6366f1',
    botName: 'Test Bot',
    greeting: 'Hello! How can I help?',
};

export const MOCK_PROFILE = {
    id: 'user_test_123',
    name: 'Test Admin',
    email: 'admin@test.com',
    workspaces: [MOCK_WORKSPACE],
};

/**
 * Mock the login AJAX action to simulate CRM login success.
 */
export async function mockCRMLogin(page: Page): Promise<void> {
    await page.route('**/admin-ajax.php', async (route: Route) => {
        const postData = route.request().postData() || '';

        if (postData.includes('vincocrm_wizard_login')) {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: {
                        message: 'Login successful!',
                        workspaces: [MOCK_WORKSPACE],
                    },
                }),
            });
            return;
        }

        // Pass through other requests.
        await route.continue();
    });
}

/**
 * Mock all wizard AJAX actions.
 */
export async function mockWizardFlow(page: Page): Promise<void> {
    await page.route('**/admin-ajax.php', async (route: Route) => {
        const postData = route.request().postData() || '';

        if (postData.includes('vincocrm_wizard_login')) {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: {
                        message: 'Login successful!',
                        workspaces: [MOCK_WORKSPACE],
                    },
                }),
            });
            return;
        }

        if (postData.includes('vincocrm_wizard_select_workspace')) {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: { message: 'Workspace selected.' },
                }),
            });
            return;
        }

        if (postData.includes('vincocrm_wizard_connect_widget')) {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: {
                        message: 'Widget connected!',
                        apiKey: MOCK_WIDGET_CONFIG.apiKey,
                    },
                }),
            });
            return;
        }

        if (postData.includes('vincocrm_wizard_skip_woocommerce')) {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: { message: 'Setup complete!' },
                }),
            });
            return;
        }

        if (postData.includes('vincocrm_wizard_connect_woocommerce')) {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: {
                        message: 'WooCommerce connected!',
                        storeId: 'store_test_123',
                    },
                }),
            });
            return;
        }

        // Pass through other requests.
        await route.continue();
    });
}

/**
 * Mock settings save AJAX actions.
 */
export async function mockSettingsSave(page: Page): Promise<void> {
    await page.route('**/admin-ajax.php', async (route: Route) => {
        const postData = route.request().postData() || '';

        if (postData.includes('vincocrm_save_settings')) {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: { message: 'Settings saved.' },
                }),
            });
            return;
        }

        if (postData.includes('vincocrm_test_connection')) {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: { message: 'Connection successful!', user: 'Test Admin' },
                }),
            });
            return;
        }

        await route.continue();
    });
}

/**
 * Mock form builder AJAX actions.
 */
export async function mockFormBuilder(page: Page): Promise<void> {
    let formCounter = 0;
    const savedForms: Record<string, any> = {};

    await page.route('**/admin-ajax.php', async (route: Route) => {
        const postData = route.request().postData() || '';

        if (postData.includes('vincocrm_list_forms')) {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: { forms: Object.values(savedForms) },
                }),
            });
            return;
        }

        if (postData.includes('vincocrm_save_form') && !postData.includes('vincocrm_save_form_settings')) {
            formCounter++;
            const formId = `form_test${formCounter}`;
            const params = new URLSearchParams(postData);
            const form = {
                id: params.get('form_id') || formId,
                title: params.get('title') || 'Untitled Form',
                fields: JSON.parse(params.get('fields') || '[]'),
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
            };
            savedForms[form.id] = form;

            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: {
                        message: 'Form saved!',
                        form,
                        shortcode: `[vincocrm_form id="${form.id}"]`,
                    },
                }),
            });
            return;
        }

        if (postData.includes('vincocrm_get_form')) {
            const params = new URLSearchParams(postData);
            const formId = params.get('form_id') || '';
            const form = savedForms[formId];

            if (form) {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ success: true, data: { form } }),
                });
            } else {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ success: false, data: { message: 'Form not found.' } }),
                });
            }
            return;
        }

        if (postData.includes('vincocrm_delete_form')) {
            const params = new URLSearchParams(postData);
            const formId = params.get('form_id') || '';
            delete savedForms[formId];

            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ success: true, data: { message: 'Form deleted.' } }),
            });
            return;
        }

        if (postData.includes('vincocrm_duplicate_form')) {
            const params = new URLSearchParams(postData);
            const formId = params.get('form_id') || '';
            const original = savedForms[formId];

            if (original) {
                formCounter++;
                const newId = `form_dup${formCounter}`;
                const dup = { ...original, id: newId, title: original.title + ' (Copy)' };
                savedForms[newId] = dup;

                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ success: true, data: { message: 'Form duplicated!', form: dup } }),
                });
            }
            return;
        }

        await route.continue();
    });
}

/**
 * Mock form submission AJAX for frontend tests.
 */
export async function mockFormSubmission(page: Page, shouldSucceed = true): Promise<void> {
    await page.route('**/admin-ajax.php', async (route: Route) => {
        const postData = route.request().postData() || '';

        if (postData.includes('vincocrm_submit_form')) {
            if (shouldSucceed) {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        success: true,
                        data: { message: "Message sent! We'll get back to you shortly." },
                    }),
                });
            } else {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        success: false,
                        data: { message: 'Something went wrong. Please try again.' },
                    }),
                });
            }
            return;
        }

        await route.continue();
    });
}
