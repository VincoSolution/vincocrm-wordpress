=== VincoCRM for WordPress and WooCommerce ===
Contributors:      vincosolution
Tags:              crm, woocommerce, integration, vincocrm, sales
Requires at least: 5.8
Tested up to:      6.5
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Connect your WooCommerce store to VincoCRM (https://www.vincocrm.ai/) to automatically sync orders and customers.

== Description ==

VincoCRM for WordPress and WooCommerce bridges your online store with your VincoCRM account so that sales data flows into your CRM automatically — no manual exports required.

**Key features:**

* **Automatic order sync** – every new or updated WooCommerce order is pushed to VincoCRM as a deal or opportunity. Choose which order statuses should trigger a sync.
* **Customer / contact sync** – when a shopper registers or updates their details in WooCommerce, the corresponding contact record in VincoCRM is created or updated.
* **Configurable** – a dedicated settings page lets you enter your API key, choose the base API URL, and control exactly which data is synced.
* **Test connection** – verify your credentials are working with a single click from the settings page.
* **Developer-friendly** – exposes filters (`vincocrm_sync_order_payload`, `vincocrm_sync_customer_payload`) and actions (`vincocrm_order_synced`, `vincocrm_order_sync_failed`, `vincocrm_customer_synced`, `vincocrm_customer_sync_failed`) so you can customise payloads or react to sync events.
* **HPOS compatible** – fully compatible with WooCommerce High-Performance Order Storage.

== Installation ==

1. Upload the `vincocrm` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Settings → VincoCRM** and enter your API key from your VincoCRM account at https://www.vincocrm.ai/.
4. Choose which data you want to sync and click **Save Settings**.
5. Optionally, click **Test Connection** to confirm that the credentials are working.

== Frequently Asked Questions ==

= Do I need a VincoCRM account? =

Yes. You need an active VincoCRM account at https://www.vincocrm.ai/ and a valid API key to use this plugin.

= Does the plugin require WooCommerce? =

WooCommerce is required for the order and customer synchronisation features. Without WooCommerce the plugin will still activate, but no data will be synced.

= Which WooCommerce order statuses trigger a sync? =

By default, orders that move to **Processing** or **Completed** status are synced. You can change this on the **Settings → VincoCRM** page.

= Can I customise the data sent to VincoCRM? =

Yes. Use the `vincocrm_sync_order_payload` and `vincocrm_sync_customer_payload` filters to add, remove, or modify fields before they are sent to the API.

== Changelog ==

= 1.0.0 =
* Initial release.
* Order sync on status change.
* Customer / contact sync on registration and profile update.
* Settings page with API key, base URL, sync toggles, and order-status multi-select.
* Test Connection button.
* HPOS compatibility declaration.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
