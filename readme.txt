=== VincoCRM ===
Contributors: vincocrm
Tags: crm, live chat, woocommerce, contact form, customer support
Requires at least: 6.7
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress/WooCommerce store to VincoCRM — AI-powered omnichannel support with live chat, contact sync, order sync, and drag-and-drop contact forms.

== Description ==

VincoCRM is the official WordPress plugin for [VincoCRM](https://vincocrm.ai), an AI-powered omnichannel customer support CRM built for ecommerce.

= Features =

* **Live Chat Widget** — Embed the VincoCRM AI chat widget on your store. Control exactly where it appears (all pages, specific pages, WooCommerce pages only, or exclude pages).
* **Contact Sync** — Automatically sync WordPress users to VincoCRM contacts on registration, login, or WooCommerce checkout with hash-based deduplication.
* **Order Sync** — Push WooCommerce orders to VincoCRM in real-time on new orders and status changes. Includes tracking data from WooCommerce Shipment Tracking and VillaTheme Order Tracking.
* **Drag-and-Drop Form Builder** — Create contact forms with a visual builder. Two engines: Classic (table-based) and Modern (drag-and-drop). Map fields to CRM contact properties.
* **Gutenberg Block** — Insert forms using the native WordPress block editor.
* **Shortcode Support** — Use `[vincocrm_form id="form_xxx"]` in any page, post, or widget.
* **4-Step Setup Wizard** — Login, select workspace, connect widget, connect WooCommerce — done in under 2 minutes.
* **Encrypted Token Storage** — JWT tokens stored using AES-256-CBC encryption with WordPress salts.
* **Retry Queue** — Failed API calls are automatically retried with exponential backoff.
* **HPOS Compatible** — Full support for WooCommerce High-Performance Order Storage.

= Requirements =

* WordPress 6.7 or later (tested up to 6.9.4)
* PHP 8.0 or later
* A VincoCRM account ([sign up](https://vincocrm.com))
* WooCommerce 8.0+ (optional, tested up to 10.6 — for order sync and checkout contact sync)

= How It Works =

1. Install and activate the plugin
2. Complete the 4-step setup wizard (Login → Workspace → Widget → WooCommerce)
3. Configure sync settings from the VincoCRM menu in your WordPress admin
4. Create contact forms and embed them using shortcodes or the Gutenberg block

= Privacy =

This plugin sends data to your VincoCRM instance (self-hosted or cloud). Data sent includes:
* User registration and login events (for contact sync)
* WooCommerce order data (for order sync)
* Contact form submissions
* Widget chat sessions

No data is sent to third parties. All communication is between your WordPress site and your VincoCRM instance.

== Installation ==

= From WordPress Plugin Directory =

1. Go to **Plugins → Add New** in your WordPress admin
2. Search for "VincoCRM"
3. Click **Install Now** then **Activate**
4. Go to **VincoCRM → Setup Wizard** to connect your CRM

= Manual Installation =

1. Download the plugin ZIP file
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Activate the plugin
5. Go to **VincoCRM → Setup Wizard** to connect your CRM

== Frequently Asked Questions ==

= Do I need a VincoCRM account? =

Yes. This plugin connects your WordPress site to VincoCRM. You need an active VincoCRM account with at least one workspace.

= Does this work without WooCommerce? =

Yes! The chat widget, contact forms, and contact sync on registration/login work without WooCommerce. Order sync and checkout contact sync require WooCommerce.

= Is my data secure? =

Authentication tokens are encrypted using AES-256-CBC with your WordPress security salts. All API communication uses HTTPS. No credentials are stored in plaintext.

= Can I switch between Classic and Modern form builders? =

Yes. Both engines save the same JSON schema, so you can switch freely without losing your forms.

= What happens when I uninstall the plugin? =

All plugin data (tokens, settings, forms, retry queue) is removed from your database. Your CRM data is not affected.

== Screenshots ==

1. Setup Wizard — Connect to VincoCRM in 4 easy steps
2. Settings — Control widget display, contact sync, and order sync
3. Form Builder — Drag-and-drop form creation with live preview
4. Live Chat Widget — AI-powered customer support on your store

== Changelog ==

= 2.0.0 =
* Complete rewrite with modern architecture
* 4-step setup wizard with WooCommerce OAuth
* Dual form builder engines (Classic and Modern)
* Gutenberg block for form embedding
* Encrypted token storage (AES-256-CBC)
* Retry queue with exponential backoff
* HPOS and Block Checkout compatibility for WooCommerce
* Real-time order sync with tracking support
* WC REST API v3 OAuth store connection
* Block-based checkout support (WC 8.3+ Store API)
* Refund update sync (WC 10.5+)
* Hash-based contact dedup
* Spam protection: honeypot, timestamp, rate limiting
* WordPress 6.9.4 and WooCommerce 10.6 compatibility
* WP script strategy support (defer/async, WP 6.7+)
* PHP 8.0+ required

== Upgrade Notice ==

= 2.0.0 =
Complete rewrite. Back up your site before upgrading. You will need to reconnect to VincoCRM via the setup wizard after upgrading.
