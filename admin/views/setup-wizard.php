<?php
/**
 * Setup wizard template — 4 steps: Login, Workspace, Widget, WooCommerce.
 *
 * @package VincoCRM
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vincocrm-wrap">
    <div class="vincocrm-wizard">
        <div class="vincocrm-wizard__header">
            <h1><?php esc_html_e( 'VincoCRM Setup', 'vincocrm' ); ?></h1>
            <p><?php esc_html_e( 'Connect your store to VincoCRM in just a few steps.', 'vincocrm' ); ?></p>
        </div>

        <!-- Progress bar -->
        <div class="vincocrm-wizard__progress">
            <div class="vincocrm-wizard__step active" data-step="1">
                <span class="vincocrm-wizard__step-num">1</span>
                <span class="vincocrm-wizard__step-label"><?php esc_html_e( 'Login', 'vincocrm' ); ?></span>
            </div>
            <div class="vincocrm-wizard__connector"></div>
            <div class="vincocrm-wizard__step" data-step="2">
                <span class="vincocrm-wizard__step-num">2</span>
                <span class="vincocrm-wizard__step-label"><?php esc_html_e( 'Workspace', 'vincocrm' ); ?></span>
            </div>
            <div class="vincocrm-wizard__connector"></div>
            <div class="vincocrm-wizard__step" data-step="3">
                <span class="vincocrm-wizard__step-num">3</span>
                <span class="vincocrm-wizard__step-label"><?php esc_html_e( 'Widget', 'vincocrm' ); ?></span>
            </div>
            <div class="vincocrm-wizard__connector"></div>
            <div class="vincocrm-wizard__step" data-step="4">
                <span class="vincocrm-wizard__step-num">4</span>
                <span class="vincocrm-wizard__step-label"><?php esc_html_e( 'WooCommerce', 'vincocrm' ); ?></span>
            </div>
        </div>

        <!-- Step 1: Login -->
        <div class="vincocrm-wizard__panel" id="vincocrm-step-1">
            <div class="vincocrm-card">
                <h2><?php esc_html_e( 'Connect to VincoCRM', 'vincocrm' ); ?></h2>
                <p><?php esc_html_e( 'Enter your VincoCRM credentials to connect your store.', 'vincocrm' ); ?></p>

                <div class="vincocrm-wizard__form">
                    <div class="vincocrm-field">
                        <label for="vincocrm-api-url"><?php esc_html_e( 'CRM URL', 'vincocrm' ); ?></label>
                        <input type="url" id="vincocrm-api-url" placeholder="https://crm.yourdomain.com"
                               value="<?php echo esc_attr( get_option( 'vincocrm_api_url', '' ) ); ?>" required>
                        <p class="description"><?php esc_html_e( 'The URL of your VincoCRM instance.', 'vincocrm' ); ?></p>
                    </div>
                    <div class="vincocrm-field">
                        <label for="vincocrm-email"><?php esc_html_e( 'Email', 'vincocrm' ); ?></label>
                        <input type="email" id="vincocrm-email" placeholder="you@example.com"
                               value="<?php echo esc_attr( get_option( 'vincocrm_user_email', '' ) ); ?>" required>
                    </div>
                    <div class="vincocrm-field">
                        <label for="vincocrm-password"><?php esc_html_e( 'Password', 'vincocrm' ); ?></label>
                        <input type="password" id="vincocrm-password" required>
                    </div>

                    <!-- MFA field (hidden by default) -->
                    <div class="vincocrm-field vincocrm-mfa-field" style="display:none;">
                        <label for="vincocrm-mfa-code"><?php esc_html_e( 'MFA Code', 'vincocrm' ); ?></label>
                        <input type="text" id="vincocrm-mfa-code" placeholder="123456" maxlength="6" pattern="[0-9]{6}">
                        <p class="description"><?php esc_html_e( 'Enter the 6-digit code from your authenticator app.', 'vincocrm' ); ?></p>
                    </div>

                    <div class="vincocrm-wizard__message" id="vincocrm-login-message"></div>

                    <button type="button" class="button button-primary button-hero" id="vincocrm-login-btn">
                        <?php esc_html_e( 'Connect', 'vincocrm' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 2: Select Workspace -->
        <div class="vincocrm-wizard__panel" id="vincocrm-step-2" style="display:none;">
            <div class="vincocrm-card">
                <h2><?php esc_html_e( 'Select Workspace', 'vincocrm' ); ?></h2>
                <p><?php esc_html_e( 'Choose the workspace to connect to this store.', 'vincocrm' ); ?></p>

                <div id="vincocrm-workspaces-list" class="vincocrm-workspace-list">
                    <!-- Populated by JS -->
                </div>

                <div class="vincocrm-wizard__message" id="vincocrm-workspace-message"></div>

                <div class="vincocrm-wizard__actions">
                    <button type="button" class="button" id="vincocrm-back-to-1"><?php esc_html_e( '← Back', 'vincocrm' ); ?></button>
                    <button type="button" class="button button-primary button-hero" id="vincocrm-select-workspace-btn" disabled>
                        <?php esc_html_e( 'Continue', 'vincocrm' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 3: Widget -->
        <div class="vincocrm-wizard__panel" id="vincocrm-step-3" style="display:none;">
            <div class="vincocrm-card">
                <h2><?php esc_html_e( 'Enable Chat Widget', 'vincocrm' ); ?></h2>
                <p><?php esc_html_e( 'The VincoCRM chat widget lets your visitors chat with your AI-powered support system.', 'vincocrm' ); ?></p>

                <div class="vincocrm-widget-preview">
                    <div class="vincocrm-widget-preview__mock">
                        <div class="vincocrm-widget-preview__bubble">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                    </div>
                </div>

                <div class="vincocrm-wizard__message" id="vincocrm-widget-message"></div>

                <div class="vincocrm-wizard__actions">
                    <button type="button" class="button" id="vincocrm-back-to-2"><?php esc_html_e( '← Back', 'vincocrm' ); ?></button>
                    <button type="button" class="button button-primary button-hero" id="vincocrm-connect-widget-btn">
                        <?php esc_html_e( 'Enable Widget', 'vincocrm' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 4: WooCommerce -->
        <div class="vincocrm-wizard__panel" id="vincocrm-step-4" style="display:none;">
            <div class="vincocrm-card">
                <h2><?php esc_html_e( 'Connect WooCommerce', 'vincocrm' ); ?></h2>

                <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                    <p><?php esc_html_e( 'Connect your WooCommerce store to sync orders and customer data to VincoCRM.', 'vincocrm' ); ?></p>

                    <div class="vincocrm-woo-info">
                        <table class="vincocrm-info-table">
                            <tr>
                                <td><?php esc_html_e( 'Store URL', 'vincocrm' ); ?></td>
                                <td><code><?php echo esc_html( site_url() ); ?></code></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'WooCommerce', 'vincocrm' ); ?></td>
                                <td><span class="vincocrm-badge vincocrm-badge--success"><?php echo esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : '—' ); ?></span></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'REST API', 'vincocrm' ); ?></td>
                                <td><code>v3</code> <span class="description">(<?php echo esc_html( rest_url( 'wc/v3' ) ); ?>)</span></td>
                            </tr>
                            <?php
                            $hpos_enabled = class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class )
                                && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
                            ?>
                            <tr>
                                <td><?php esc_html_e( 'HPOS', 'vincocrm' ); ?></td>
                                <td>
                                    <?php if ( $hpos_enabled ) : ?>
                                        <span class="vincocrm-badge vincocrm-badge--success"><?php esc_html_e( 'Enabled', 'vincocrm' ); ?></span>
                                    <?php else : ?>
                                        <span class="vincocrm-badge vincocrm-badge--muted"><?php esc_html_e( 'Legacy', 'vincocrm' ); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        <p class="description" style="margin-top:10px;">
                            <?php esc_html_e( 'VincoCRM connects via WC REST API v3 using OAuth. Your store will generate read-only API keys automatically.', 'vincocrm' ); ?>
                        </p>
                    </div>

                    <div class="vincocrm-wizard__message" id="vincocrm-woo-message"></div>

                    <div class="vincocrm-wizard__actions">
                        <button type="button" class="button" id="vincocrm-back-to-3"><?php esc_html_e( '← Back', 'vincocrm' ); ?></button>
                        <button type="button" class="button button-link" id="vincocrm-skip-woo-btn"><?php esc_html_e( 'Skip for now', 'vincocrm' ); ?></button>
                        <button type="button" class="button button-primary button-hero" id="vincocrm-connect-woo-btn">
                            <?php esc_html_e( 'Connect WooCommerce', 'vincocrm' ); ?>
                        </button>
                    </div>
                <?php else : ?>
                    <p><?php esc_html_e( 'WooCommerce is not installed. You can skip this step and connect later.', 'vincocrm' ); ?></p>

                    <div class="vincocrm-wizard__actions">
                        <button type="button" class="button" id="vincocrm-back-to-3"><?php esc_html_e( '← Back', 'vincocrm' ); ?></button>
                        <button type="button" class="button button-primary button-hero" id="vincocrm-skip-woo-btn">
                            <?php esc_html_e( 'Finish Setup', 'vincocrm' ); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Complete -->
        <div class="vincocrm-wizard__panel" id="vincocrm-step-complete" style="display:none;">
            <div class="vincocrm-card vincocrm-card--success">
                <div class="vincocrm-wizard__complete-icon">&#10003;</div>
                <h2><?php esc_html_e( 'Setup Complete!', 'vincocrm' ); ?></h2>
                <p><?php esc_html_e( 'Your store is now connected to VincoCRM. You can manage settings from the VincoCRM menu.', 'vincocrm' ); ?></p>

                <div class="vincocrm-wizard__actions">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=vincocrm' ) ); ?>" class="button button-primary button-hero">
                        <?php esc_html_e( 'Go to Settings', 'vincocrm' ); ?>
                    </a>
                    <a href="<?php echo esc_url( home_url() ); ?>" class="button button-hero" target="_blank">
                        <?php esc_html_e( 'View Your Site', 'vincocrm' ); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
