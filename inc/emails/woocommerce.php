<?php
/**
 * WooCommerce email customizations.
 *
 * @package chassesautresor-com
 */

defined('ABSPATH') || exit();

/**
 * Removes default WooCommerce email header and footer callbacks.
 *
 * @return void
 */
function cta_wc_override_email_templates(): void
{
    if (!function_exists('WC')) {
        return;
    }

    $mailer = WC()->mailer();
    remove_action('woocommerce_email_header', [ $mailer, 'email_header' ]);
    remove_action('woocommerce_email_footer', [ $mailer, 'email_footer' ]);
}
add_action('init', 'cta_wc_override_email_templates');

/**
 * Stores the WooCommerce email heading for later reuse.
 *
 * @param string $email_heading Email heading.
 *
 * @return void
 */
function cta_wc_store_email_heading(string $email_heading): void
{
    $GLOBALS['cta_wc_email_heading'] = $email_heading;
}
add_action('woocommerce_email_header', 'cta_wc_store_email_heading');

/**
 * Injects the HTML template around WooCommerce email content.
 *
 * @param string $message Email body content.
 *
 * @return string
 */
function cta_wc_render_email_content(string $message): string
{
    $heading = $GLOBALS['cta_wc_email_heading'] ?? '';

    return cta_render_email_template($heading, $message);
}
add_filter('woocommerce_mail_content', 'cta_wc_render_email_content');
