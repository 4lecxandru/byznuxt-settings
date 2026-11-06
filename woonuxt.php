<?php
/*
Plugin Name: Settings for ReelSalesLetter
Description: Configure a ReelSalesLetter storefront and expose its settings through WPGraphQL.
Author: Alex Toteanu & Scott Kennedy
Author URI: https://reelsalesletter.com
Plugin URI: https://github.com/4lecxandru/byzuxt-settings
Version: 2.5.20
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: settings-for-reelsalesletter
*/

if (!defined('ABSPATH')) {
    exit();
}

if (defined('WOONUXT_SETTINGS_BOOTSTRAPPED')) {
    return;
}

define('WOONUXT_SETTINGS_BOOTSTRAPPED', __FILE__);

require_once 'includes/constants.php';
require_once 'includes/assets.php';
require_once 'includes/graphql.php';

// Define Globals
global $woonuxt_plugin_list;

// Add filter to add the settings link to the plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'woonuxt_plugin_action_links');

/**
 * Add settings link to plugin action links
 *
 * @since 2.0.0
 * @param array $links Array of plugin action links
 * @return array Modified array of plugin action links
 */
function woonuxt_plugin_action_links($links)
{
    $admin_url = admin_url('options-general.php?page=reelsalesletter');
    if (is_array($links)) {
        $links[] = '<a href="' . esc_url($admin_url) . '">Settings</a>';
    }

    return $links;
}

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once 'includes/connection-health.php';

$woonuxt_plugin_list = [
    WOONUXT_WOOCOMMERCE_SLUG => [
        'name'        => 'WooCommerce',
        'description' => 'An eCommerce toolkit that helps you sell anything.',
        'url'         => WOONUXT_WP_PLUGIN_URL . 'woocommerce.' . WOONUXT_WOOCOMMERCE_VERSION . '.zip',
        'installable' => true,
        'file'        => WOONUXT_WOOCOMMERCE_FILE,
        'icon'        => plugins_url('assets/WooCommerce.png', __FILE__),
        'slug'        => WOONUXT_WOOCOMMERCE_SLUG,
    ],
    WOONUXT_WPGRAPHQL_SLUG => [
        'name'        => 'WPGraphQL',
        'description' => 'A GraphQL API for WordPress.',
        'url'         => WOONUXT_WP_PLUGIN_URL . 'wp-graphql.' . WOONUXT_WPGRAPHQL_VERSION . '.zip',
        'installable' => true,
        'file'        => WOONUXT_WPGRAPHQL_FILE,
        'icon'        => plugins_url('assets/colored-logo.svg', __FILE__),
        'slug'        => WOONUXT_WPGRAPHQL_SLUG,
    ],
    WOONUXT_WOOGRAPHQL_SLUG => [
        'name'        => 'WooGraphQL',
        'description' => 'Enables GraphQL to work with WooCommerce.',
        'installable' => false,
        'manual_install_url' => 'https://github.com/wp-graphql/wp-graphql-woocommerce/releases',
        'file'        => WOONUXT_WOOGRAPHQL_FILE,
        'icon'        => plugins_url('assets/colored-logo.svg', __FILE__),
        'slug'        => WOONUXT_WOOGRAPHQL_SLUG,
    ],
    WOONUXT_HEADLESS_LOGIN_SLUG => [
        'name'        => 'WPGraphQL Headless Login',
        'description' => 'Headless Login for WPGraphQL.',
        'installable' => false,
        'manual_install_url' => 'https://github.com/AxeWP/wp-graphql-headless-login/releases',
        'file'        => WOONUXT_HEADLESS_LOGIN_FILE,
        'icon'        => plugins_url('assets/colored-logo.svg', __FILE__),
        'slug'        => WOONUXT_HEADLESS_LOGIN_SLUG,
    ],
];

/**
 * Get required plugins list.
 *
 * @since 2.5.14
 * @return array Array of required plugins with their configuration.
 */
if (!function_exists('woonuxt_get_required_plugins')) {
    function woonuxt_get_required_plugins()
    {
        global $woonuxt_plugin_list;

        return is_array($woonuxt_plugin_list) ? $woonuxt_plugin_list : [];
    }
}

/**
 * Get default plugin options.
 *
 * @since 2.5.14
 * @return array Default options array.
 */
if (!function_exists('woonuxt_get_default_options')) {
    function woonuxt_get_default_options()
    {
        return [
            'primary_color'                         => '#7F54B2',
            'productsPerPage'                       => 24,
            'logo'                                  => '',
            'frontEndUrl'                           => '',
            'build_hook'                            => '',
            'stripe_apple_pay_merchant_identifier' => '',
            'global_attributes'                     => [],
            'wooNuxtSEO'                            => [],
        ];
    }
}

/**
 * Add the options page to admin menu
 *
 * @since 2.0.0
 * @return void
 */
add_action('admin_menu', 'woonuxt_add_admin_menu');
function woonuxt_add_admin_menu()
{
    add_options_page(__('ReelSalesLetter Options', 'settings-for-byznuxt'), __('ReelSalesLetter', 'settings-for-byznuxt'), 'manage_options', 'reelsalesletter', 'woonuxt_options_page_html');
}

add_action('admin_init', 'woonuxt_handle_required_plugin_install');

/**
 * Install or activate a required plugin before the settings page renders.
 *
 * @since 2.5.14
 * @return void
 */
function woonuxt_handle_required_plugin_install()
{
    if (!isset($_GET['page'], $_GET['install_plugin'], $_GET['_wpnonce']) || sanitize_key(wp_unslash($_GET['page'])) !== 'reelsalesletter') {
        return;
    }

    if (!current_user_can('manage_options') || !current_user_can('install_plugins') || !current_user_can('activate_plugins')) {
        wp_die(esc_html__('Insufficient permissions', 'settings-for-byznuxt'), '', ['response' => 403]);
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'woonuxt_install_plugin_nonce')) {
        wp_die(esc_html__('Security check failed', 'settings-for-byznuxt'));
    }

    $plugins     = woonuxt_get_required_plugins();
    $plugin_slug = sanitize_key(wp_unslash($_GET['install_plugin']));

    if (!isset($plugins[$plugin_slug])) {
        wp_die(esc_html__('Invalid plugin', 'settings-for-byznuxt'));
    }

    $plugin   = $plugins[$plugin_slug];
    $redirect = admin_url('options-general.php?page=reelsalesletter');

    if (empty($plugin['installable'])) {
        wp_die(esc_html__('This dependency must be installed manually from its official release page.', 'settings-for-byznuxt'));
    }

    if (is_plugin_active($plugin['file'])) {
        wp_safe_redirect($redirect);
        exit;
    }

    if (file_exists(WP_PLUGIN_DIR . '/' . $plugin['file'])) {
        $activation_result = activate_plugin($plugin['file'], $redirect);

        if (is_wp_error($activation_result)) {
            /* translators: %s: error returned by the plugin installer or activator. */
            wp_die(esc_html(sprintf(__('Plugin activation failed: %s', 'settings-for-byznuxt'), $activation_result->get_error_message())));
        }

        wp_safe_redirect($redirect);
        exit;
    }

    $upgrader = new Plugin_Upgrader();
    $result   = $upgrader->install($plugin['url']);

    if (is_wp_error($result)) {
        /* translators: %s: error returned by the plugin installer or activator. */
        wp_die(esc_html(sprintf(__('Plugin installation failed: %s', 'settings-for-byznuxt'), $result->get_error_message())));
    }

    if (!$result) {
        wp_die(esc_html__('Plugin installation failed: Unknown error', 'settings-for-byznuxt'));
    }

    $activation_result = activate_plugin($plugin['file']);
    if (is_wp_error($activation_result)) {
        /* translators: %s: error returned by the plugin installer or activator. */
        wp_die(esc_html(sprintf(__('Plugin activation failed: %s', 'settings-for-byznuxt'), $activation_result->get_error_message())));
    }

    wp_safe_redirect($redirect);
    exit;
}

/**
 * Render the options page HTML
 *
 * @since 2.0.0
 * @return void
 */
function woonuxt_options_page_html()
{
    $options = wp_parse_args(get_option('woonuxt_options'), woonuxt_get_default_options()); ?>
    <div class="woonuxt-settings-wrap">
        <div class="woonuxt-header">
            <div class="woonuxt-header-content">
                <div class="woonuxt-brand">
                    <a href="https://reelsalesletter.com" target="_blank" rel="noopener noreferrer" class="woonuxt-logo">
                        <img src="<?php echo esc_url(plugins_url('assets/colored-logo.svg', __FILE__)); ?>" alt="ReelSalesLetter">
                    </a>
                    <div>
                        <h1>ReelSalesLetter Settings</h1>
                        <p class="woonuxt-version">Version <?php echo esc_html(WOONUXT_SETTINGS_VERSION); ?></p>
                    </div>
                </div>
                <div class="woonuxt-header-actions">
                    <?php if (!empty(trim((string) ($options['frontEndUrl'] ?? '')))): ?>
                        <a href="<?php echo esc_url($options['frontEndUrl']); ?>" target="_blank" rel="noopener noreferrer" class="woonuxt-visit-btn" title="Open your site in a new tab">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                            Visit Site
                        </a>
                    <?php endif; ?>
                    <?php if (!empty(trim((string) ($options['build_hook'] ?? '')))): ?>
                        <button id="deploy-button" class="woonuxt-deploy-btn" title="Trigger a rebuild to push your latest changes">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"></path>
                            </svg>
                            Trigger Rebuild
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="wrap woonuxt-content">
            <form action="options.php" method="post">
                <?php settings_fields('woonuxt_options');
    do_settings_sections('woonuxt');
    submit_button('Save Changes', 'primary', 'submit', true, ['id' => 'woonuxt-save-btn']); ?>
            </form>
        </div>
    </div>
<?php
}

// Register AJAX handlers
add_action('wp_ajax_woonuxt_check_plugin_status', 'woonuxt_handle_check_plugin_status');

/**
 * AJAX handler to check plugin status
 *
 * @since 2.0.0
 * @return void Outputs plugin status and dies
 */
function woonuxt_handle_check_plugin_status()
{
    check_ajax_referer('woonuxt_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_die('forbidden', '', ['response' => 403]);
    }

    $plugin_slug = isset($_POST['plugin']) ? sanitize_text_field(wp_unslash($_POST['plugin'])) : '';
    $plugin_file = isset($_POST['file']) ? sanitize_text_field(wp_unslash($_POST['file'])) : '';

    if ($plugin_slug === '' || $plugin_file === '') {
        wp_die('invalid_request', '', ['response' => 400]);
    }

    $plugins = woonuxt_get_required_plugins();
    if (!isset($plugins[$plugin_slug]) || $plugins[$plugin_slug]['file'] !== $plugin_file) {
        wp_die('invalid_request', '', ['response' => 400]);
    }

    if (is_plugin_active($plugin_file)) {
        wp_die('installed');
    } else {
        wp_die('not_installed');
    }
}

// Register settings
add_action('admin_init', 'woonuxt_register_settings');

/**
 * Register WooNuxt settings and sections
 *
 * @since 2.0.0
 * @return void
 */
function woonuxt_register_settings()
{
    global $woonuxt_plugin_list;

    register_setting('woonuxt_options', 'woonuxt_options', [
        'sanitize_callback' => 'woonuxt_legacy_sanitize_options',
    ]);

    // General settings first
    if (class_exists('WooCommerce')) {
        add_settings_section('woonuxt_global_setting', '', 'woonuxt_global_setting_callback', 'woonuxt');
    }

    // Read-only diagnostics for WordPress and GraphQL prerequisites.
    add_settings_section('woonuxt_connection_health', '', 'woonuxt_connection_health_callback', 'woonuxt');

    // Always show plugins section
    add_settings_section('woonuxt_required_plugins', '', 'woonuxt_required_plugins_callback', 'woonuxt');

    // GraphQL schema reference
    add_settings_section('woonuxt_graphql_schema', '', 'woonuxt_graphql_schema_callback', 'woonuxt');

    // Always show deploy section
    add_settings_section('woonuxt_deploy_button', '', 'woonuxt_deploy_button_callback', 'woonuxt');
}

/**
 * Sanitize plugin options for legacy settings registration.
 *
 * @since 2.5.9
 * @param array $options Raw options.
 * @return array
 */
function woonuxt_legacy_sanitize_options($options)
{
    if (!is_array($options)) {
        return [];
    }

    $sanitized = [];

    if (isset($options['logo'])) {
        $sanitized['logo'] = esc_url_raw(trim((string) $options['logo']));
    }

    if (isset($options['frontEndUrl'])) {
        $sanitized['frontEndUrl'] = esc_url_raw(trim((string) $options['frontEndUrl']));
    }

    if (isset($options['build_hook'])) {
        $sanitized['build_hook'] = esc_url_raw(trim((string) $options['build_hook']));
    }

    if (isset($options['stripe_apple_pay_merchant_identifier'])) {
        $sanitized['stripe_apple_pay_merchant_identifier'] = sanitize_text_field($options['stripe_apple_pay_merchant_identifier']);
    }

    if (isset($options['primary_color'])) {
        $sanitized['primary_color'] = sanitize_hex_color($options['primary_color']);
    }

    if (isset($options['productsPerPage'])) {
        $sanitized['productsPerPage'] = max(1, absint($options['productsPerPage']));
    }

    if (isset($options['global_attributes']) && is_array($options['global_attributes'])) {
        $sanitized['global_attributes'] = array_map(function ($attr) {
            return [
                'label'         => isset($attr['label']) ? sanitize_text_field($attr['label']) : '',
                'slug'          => isset($attr['slug']) ? sanitize_text_field($attr['slug']) : '',
                'showCount'     => isset($attr['showCount']) ? (bool) $attr['showCount'] : false,
                'hideEmpty'     => isset($attr['hideEmpty']) ? (bool) $attr['hideEmpty'] : false,
                'openByDefault' => isset($attr['openByDefault']) ? (bool) $attr['openByDefault'] : false,
            ];
        }, $options['global_attributes']);
    }

    if (isset($options['wooNuxtSEO']) && is_array($options['wooNuxtSEO'])) {
        $sanitized['wooNuxtSEO'] = array_map(function ($seo) {
            return [
                'provider' => isset($seo['provider']) ? sanitize_text_field($seo['provider']) : '',
                'handle'   => isset($seo['handle']) ? sanitize_text_field($seo['handle']) : '',
                'url'      => isset($seo['url']) ? esc_url_raw(trim((string) $seo['url'])) : '',
            ];
        }, $options['wooNuxtSEO']);
    }

    return $sanitized;
}

/**
 * Safely retrieve WooCommerce product attributes.
 *
 * @since 2.5.7
 * @return array
 */
function woonuxt_get_product_attributes()
{
    if (!function_exists('wc_get_attribute_taxonomies')) {
        return [];
    }

    $product_attributes = wc_get_attribute_taxonomies();

    return is_array($product_attributes) ? $product_attributes : [];
}

/**
 * Callback function to display required plugins section
 *
 * @since 2.0.0
 * @return void
 */
function woonuxt_required_plugins_callback()
{
    global $woonuxt_plugin_list; ?>
    <div class="woonuxt-section">
        <h3 class="section-title">Required Plugins</h3>
        <ul class="required-plugins-list">
            <?php foreach ($woonuxt_plugin_list as $plugin): ?>
                <li class="required-plugin">
                    <div>
                        <div class="flex items-center gap-4 mb-2">
                            <img src="<?php echo esc_url($plugin['icon']); ?>" width="24" height="24" alt="<?php echo esc_attr($plugin['name']); ?>">
                            <h4 class="plugin-name"><?php echo esc_html($plugin['name']); ?></h4>
                        </div>
                        <p class="plugin-description"><?php echo esc_html($plugin['description']); ?></p>
                        <div class="plugin-state" data-plugin="<?php echo esc_attr($plugin['slug']); ?>" data-file="<?php echo esc_attr($plugin['file']); ?>">
                            <!-- Loading -->
                            <div class="plugin-state_loading">
                                <img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" alt="Loading" width="20" height="20" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 5px;" />
                                Checking
                            </div>

                            <!-- Installed -->
                            <div class="plugin-state_installed" style="display:none;">
                                <span style="color: #41b782">Installed</span>
                            </div>

                            <!-- Not Installed -->
                            <?php if (!empty($plugin['installable'])): ?>
                                <a class="plugin-state_install" style="display:none;" href="<?php echo esc_url(wp_nonce_url(admin_url('options-general.php?page=reelsalesletter&install_plugin=' . rawurlencode($plugin['slug'])), 'woonuxt_install_plugin_nonce')); ?>">Install Now</a>
                            <?php else: ?>
                                <a class="plugin-state_install" style="display:none;" href="<?php echo esc_url($plugin['manual_install_url']); ?>" target="_blank" rel="noopener noreferrer">Install Manually</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

/**
 * Callback function to display GraphQL schema reference
 *
 * @since 2.3.0
 * @return void
 */
function woonuxt_graphql_schema_callback()
{
    ?>
    <div class="woonuxt-section">
        <h3 class="section-title">GraphQL Schema Reference</h3>
        <p class="description" style="margin: 16px 20px; line-height: 1.6;">
            <?php esc_html_e('This query shows all the fields exposed by the ReelSalesLetter Settings plugin. Use this in your headless frontend to fetch configuration data.', 'settings-for-byznuxt'); ?>
        </p>

        <div style="padding: 0 20px 20px;">
            <details style="background: none; border: none; margin: 0; padding: 0;">
                <summary style="cursor: pointer; padding: 10px 0; margin: 0 0 16px 0; background: transparent; border: none; font-weight: 500; color: #2271b1; user-select: none; list-style: none; display: flex; align-items: center; gap: 8px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="transition: transform 0.2s;">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                    <?php esc_html_e('View Query', 'settings-for-byznuxt'); ?>
                </summary>
                <pre style="margin: 0 0 16px 0; padding: 20px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; overflow-x: auto; font-family: 'Courier New', Consolas, monospace; font-size: 13px; line-height: 1.8; color: #2c3338;"><code>query {
  woonuxtSettings {
    # Plugin version
    wooCommerceSettingsVersion

    # GraphQL settings
    publicIntrospectionEnabled

    # General settings
    productsPerPage
    primary_color
    maxPrice
    logo
    frontEndUrl
    domain

    # Currency
    currencySymbol
    currencyCode

    # SEO and social media
    wooNuxtSEO {
      provider
      url
      handle
    }

    # Product filtering attributes
    global_attributes {
      label
      slug
      showCount
      hideEmpty
      openByDefault
    }

    # Stripe payment settings
    stripeSettings {
      enabled
      testmode
      test_publishable_key
      publishable_key
      active_publishable_key
      account_id
      apple_pay_merchant_identifier
    }

    # PayPal payment settings
    paypalSettings {
      enabled
      sandbox
      email
    }
  }
}</code></pre>
            </details>

            <div style="padding: 16px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
                <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #2c3338;">
                    <strong><?php esc_html_e('Tip:', 'settings-for-byznuxt'); ?></strong>
                    <?php esc_html_e('Copy this query and use it in your GraphQL client or headless frontend to fetch all ReelSalesLetter configuration data.', 'settings-for-byznuxt'); ?>
                </p>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Callback function to display deploy button section
 *
 * @since 2.0.0
 * @return void
 */
function woonuxt_deploy_button_callback()
{
    $site_name    = get_bloginfo('name');
    $gql_settings = get_option('graphql_general_settings');
    $gql_endpoint = isset($gql_settings['graphql_endpoint']) ? $gql_settings['graphql_endpoint'] : 'graphql';
    $endpoint     = get_site_url() . '/' . $gql_endpoint;
    $image_domain = wp_parse_url(home_url(), PHP_URL_HOST);

    $netlify_environment = [
        'GQL_HOST'             => $endpoint,
        'NUXT_IMAGE_DOMAINS'   => $image_domain,
        'NUXT_IMAGE_PROVIDER'  => 'netlify',
    ];
    $netlify_deploy_url = sprintf(
        'https://app.netlify.com/start/deploy?repository=https://github.com/scottyzen/woonuxt#%s',
        http_build_query($netlify_environment, '', '&', PHP_QUERY_RFC3986)
    );

    $vercel_environment = [
        'GQL_HOST',
        'NUXT_IMAGE_DOMAINS',
        'NUXT_IMAGE_PROVIDER',
    ];
    $vercel_deploy_url = add_query_arg(
        [
            'repository-url'  => 'https://github.com/scottyzen/woonuxt',
            'repository-name' => $site_name,
            'env'             => implode(',', $vercel_environment),
        ],
        'https://vercel.com/new/clone'
    );

    $isWooCommerceActive  = class_exists('WooCommerce');
    $product_attributes   = $isWooCommerceActive ? woonuxt_get_product_attributes() : [];
    $hasProductAttributes = count($product_attributes) > 0;

    $allSettingHaveBeenMet = $hasProductAttributes;
    ?>

    <div class="woonuxt-section">
        <h3 class="section-title">Deploy Your Site</h3>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px; color: #646970;">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            Deploy Options
                        </label>
                    </th>
                    <td>
                        <div class="deploy-buttons-container">
                            <a id="netlify-button" href="<?php echo esc_url($netlify_deploy_url); ?>" target="_blank" rel="noopener noreferrer">
                                <img src="<?php echo esc_url(plugins_url('assets/netlify.svg', __FILE__)); ?>" alt="Deploy to Netlify" width="146" height="32">
                            </a>
                            <a href="<?php echo esc_url($vercel_deploy_url); ?>" target="_blank" rel="noopener noreferrer" class="vercel-button" data-metrics-url="https://vercel.com/p/button">
                                <svg data-testid="geist-icon" fill="none" height="15" width="15" shape-rendering="geometricPrecision" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2L2 19.7778H22L12 2Z" fill="#fff" stroke="#fff" stroke-width="1.5"></path>
                                </svg>
                                <span>Deploy to Vercel</span>
                            </a>
                    </div>
                    <div class="required-settings-notice">
                        <h4 class="notice-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <?php echo esc_html__('Required Settings', 'settings-for-byznuxt'); ?>
                        </h4>
                        <p class="notice-description"><?php echo esc_html__('These settings are required for ReelSalesLetter to work properly:', 'settings-for-byznuxt'); ?></p>
                        <ul class="requirements-list">
                            <li>
                                <a href="/wp-admin/admin.php?page=graphql-settings"><?php echo esc_html__('WPGraphQL Settings', 'settings-for-byznuxt'); ?></a>
                            </li>
                            <li>
                                <?php if ($isWooCommerceActive): ?>
                                    <a href="/wp-admin/edit.php?post_type=product&page=product_attributes"><?php echo esc_html__('Product Attributes', 'settings-for-byznuxt'); ?></a>
                                    <span style="color: <?php echo $hasProductAttributes ? '#00a32a' : '#d63638'; ?>; margin-left: 8px;"><?php echo $hasProductAttributes ? '✅' : '❌'; ?></span>
                                    <span style="color: #646970; font-size: 12px; margin-left: 4px;"><?php echo esc_html__('At least one product attribute', 'settings-for-byznuxt'); ?></span>
                                <?php else: ?>
                                    <span><?php echo esc_html__('Product Attributes', 'settings-for-byznuxt'); ?></span>
                                    <span style="color: #646970; font-size: 12px; margin-left: 8px;"><?php echo esc_html__('Install and activate WooCommerce to enable this check', 'settings-for-byznuxt'); ?></span>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
    </div>
<?php
}

/**
 * Callback function to display global settings section
 *
 * @since 2.0.0
 * @return void
 */
function woonuxt_global_setting_callback()
{
    $product_attributes = woonuxt_get_product_attributes();
    $options            = wp_parse_args(get_option('woonuxt_options'), woonuxt_get_default_options());
    $primary_color = isset($options['primary_color']) ? $options['primary_color'] : '#7F54B2';
    ?>
    <div class="global_setting woonuxt-section">
        <h3 class="section-title">General Settings</h3>
        <table class="form-table" role="presentation">
            <tbody>

                <!-- LOGO -->
                <tr>
                    <th scope="row">
                        <label for="woonuxt_options[logo]">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px; color: #646970;">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                            <?php echo esc_html__('Logo', 'settings-for-byznuxt'); ?>
                        </label>
                    </th>
                    <td>
                        <div id="woonuxt-logo-preview" style="margin-bottom: 12px; <?php echo !isset($options['logo']) || empty($options['logo']) ? 'display:none;' : ''; ?>">
                            <img src="<?php echo isset($options['logo']) ? esc_url($options['logo']) : ''; ?>" style="max-width: 300px; height: auto; display: block; border: 1px solid #ddd; padding: 5px; background: #f9f9f9;" alt="Logo Preview" />
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <button type="button" class="button woonuxt-upload-logo-btn" id="woonuxt-upload-logo-btn"><?php echo esc_html__('Choose Image', 'settings-for-byznuxt'); ?></button>
                            <button type="button" class="button woonuxt-remove-logo-btn" id="woonuxt-remove-logo-btn" style="<?php echo !isset($options['logo']) || empty($options['logo']) ? 'display:none;' : ''; ?>"><?php echo esc_html__('Remove', 'settings-for-byznuxt'); ?></button>
                        </div>
                        <input type="hidden" id="woonuxt_logo_url" name="woonuxt_options[logo]" value="<?php echo isset($options['logo']) ? esc_attr($options['logo']) : ''; ?>" />
                        <p class="description"><?php echo esc_html__('Upload or select an image from the Media Library for your logo.', 'settings-for-byznuxt'); ?></p>
                    </td>
                </tr>

                <!-- FRONT END URL -->
                <tr>
                    <th scope="row">
                        <label for="woonuxt_options[frontEndUrl]">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px; color: #646970;">
                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                            </svg>
                            <?php echo esc_html__('Front End URL', 'settings-for-byznuxt'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" class="widefat" name="woonuxt_options[frontEndUrl]" value="<?php echo isset($options['frontEndUrl']) ? esc_url($options['frontEndUrl']) : ''; ?>" placeholder="e.g. https://mysite.netlify.app" />
                        <p class="description"><?php echo esc_html__('This is the URL of your Nuxt site not the WordPress site.', 'settings-for-byznuxt'); ?></p>
                    </td>
                </tr>

                <!-- PRODUCTS PER PAGE -->
                <tr>
                    <th scope="row">
                        <label for="woonuxt_options[productsPerPage]">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px; color: #646970;">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                            </svg>
                            <?php echo esc_html__('Products Per Page', 'settings-for-byznuxt'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" name="woonuxt_options[productsPerPage]" value="<?php echo isset($options['productsPerPage']) ? absint($options['productsPerPage']) : 24; ?>" placeholder="e.g. 12" min="1" />
           
