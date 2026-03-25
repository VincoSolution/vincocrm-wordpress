#!/bin/bash
##
# WordPress test environment setup script.
# Runs inside the WP-CLI container after WordPress is healthy.
##

set -e

echo "==> Setting up WordPress test environment..."

# Wait for WordPress to be fully ready.
sleep 5

# Install WordPress if not already installed.
if ! wp core is-installed 2>/dev/null; then
    wp core install \
        --url="http://localhost:8080" \
        --title="VincoCRM Test Site" \
        --admin_user="admin" \
        --admin_password="password" \
        --admin_email="admin@test.local" \
        --skip-email
fi

# Install and activate WooCommerce.
if ! wp plugin is-installed woocommerce 2>/dev/null; then
    wp plugin install woocommerce --activate
else
    wp plugin activate woocommerce 2>/dev/null || true
fi

# Run WooCommerce setup.
wp option update woocommerce_store_address "123 Test St"
wp option update woocommerce_store_city "Test City"
wp option update woocommerce_store_postcode "12345"
wp option update woocommerce_default_country "US:CA"
wp option update woocommerce_currency "USD"

# Activate VincoCRM plugin.
wp plugin activate vincocrm 2>/dev/null || true

# Set permalink structure (needed for REST API).
wp rewrite structure '/%postname%/' --hard 2>/dev/null || true

# Create a test page with the VincoCRM form shortcode.
if ! wp post list --post_type=page --post_name=vincocrm-test-form --format=ids | grep -q .; then
    wp post create \
        --post_type=page \
        --post_title="VincoCRM Test Form" \
        --post_name="vincocrm-test-form" \
        --post_status=publish \
        --post_content='[vincocrm_form id="form_test1"]'
fi

# Pre-create a test form in options (so the shortcode renders).
wp option update vincocrm_form_form_test1 '{
    "id": "form_test1",
    "title": "Test Contact Form",
    "fields": [
        {"type":"text","label":"Name","placeholder":"Your name","required":true,"crm_field":"name","options":[],"width":"full"},
        {"type":"email","label":"Email","placeholder":"your@email.com","required":true,"crm_field":"email","options":[],"width":"full"},
        {"type":"textarea","label":"Message","placeholder":"Tell us more...","required":true,"crm_field":"message","options":[],"width":"full"}
    ],
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-01-01 00:00:00"
}' --format=json 2>/dev/null || true

# Add to form index.
wp option update vincocrm_form_index '["form_test1"]' --format=json 2>/dev/null || true

# Pre-configure widget for injection tests.
wp option update vincocrm_widget_settings '{"enabled":true,"display":"all","include_pages":[],"exclude_pages":[]}' --format=json 2>/dev/null || true
wp option update vincocrm_widget_api_key "wk_test_e2e_key_000000000000000000000000" 2>/dev/null || true
wp option update vincocrm_widget_api_url "https://crm.test.local/api" 2>/dev/null || true
wp option update vincocrm_setup_complete "1" 2>/dev/null || true

echo "==> WordPress test environment ready!"
echo "    URL: http://localhost:8080"
echo "    Admin: admin / password"
echo "    Test form: http://localhost:8080/vincocrm-test-form/"
