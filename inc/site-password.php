<?php
/**
 * Site password protection.
 */

function ca_site_password_protection(): void
{
    $is_cron_request = (
        (function_exists('wp_doing_cron') && wp_doing_cron())
        || (defined('DOING_CRON') && DOING_CRON)
    );

    $is_ajax_request = (
        (function_exists('wp_doing_ajax') && wp_doing_ajax())
        || (defined('DOING_AJAX') && DOING_AJAX)
    );

    $is_rest_request = defined('REST_REQUEST') && REST_REQUEST;

    if (! $is_rest_request && function_exists('wp_is_serving_rest_request')) {
        $is_rest_request = wp_is_serving_rest_request();
    }

    if (
        PHP_SAPI === 'cli'
        || (defined('WP_INSTALLING') && WP_INSTALLING)
        || $is_cron_request
        || $is_ajax_request
        || $is_rest_request
    ) {
        return;
    }

    if (is_user_logged_in()) {
        return;
    }

    if ('1' !== get_option('ca_site_password_enabled', '1')) {
        return;
    }

    $field         = 'ca_site_password';
    $password      = getenv('CA_SITE_PASSWORD') ?: (string) get_option('ca_site_password', 'citizen');
    $attempt_field = 'ca_site_password_attempts';
    $max_attempts  = 10;
    $attempts      = isset($_COOKIE[$attempt_field]) ? (int) $_COOKIE[$attempt_field] : 0;
    $error_message = '';

    if (
        isset($_COOKIE[$field])
        && strcasecmp($_COOKIE[$field], $password) === 0
    ) {
        return;
    }

    if (
        isset($_POST[$field])
        && is_string($_POST[$field])
    ) {
        if (strcasecmp($_POST[$field], $password) === 0) {
            if (!headers_sent()) {
                setcookie($field, $password, time() + DAY_IN_SECONDS, '/', COOKIE_DOMAIN, is_ssl(), true);
                setcookie($attempt_field, '', time() - DAY_IN_SECONDS, '/', COOKIE_DOMAIN, is_ssl(), true);
            }

            return;
        }

        $attempts++;
        if (!headers_sent()) {
            setcookie($attempt_field, (string) $attempts, time() + DAY_IN_SECONDS, '/', COOKIE_DOMAIN, is_ssl(), true);
        }
        $error_message = esc_html__('Incorrect password.', 'chassesautresor-com');
    }

    if ($attempts >= $max_attempts) {
        if (!headers_sent()) {
            status_header(403);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo '<p>' . esc_html__('Too many attempts. Please try again later.', 'chassesautresor-com') . '</p>';
        exit;
    }

    if (!headers_sent()) {
        status_header(401);
        header('Content-Type: text/html; charset=utf-8');
    }

    $svg_url   = esc_url(get_stylesheet_directory_uri() . '/assets/svg/pirate-skull.svg');
    $style_url = esc_url(get_stylesheet_directory_uri() . '/dist/style.css');

    $styles = '.ca-site-password-wrapper{'
        . 'display:flex;'
        . 'min-height:100vh;'
        . 'align-items:center;'
        . 'justify-content:center;'
        . 'padding:var(--space-xl) var(--space-lg);'
        . 'background:var(--color-background);'
        . 'color:var(--color-text-primary);'
        . 'font-family:var(--font-main);'
        . 'box-sizing:border-box;'
        . '}'
        . '.ca-site-password-form{'
        . 'width:100%;'
        . 'max-width:26rem;'
        . 'margin:0 auto;'
        . 'text-align:center;'
        . 'display:flex;'
        . 'flex-direction:column;'
        . 'align-items:stretch;'
        . 'gap:var(--space-md);'
        . 'font-size:1rem;'
        . '}'
        . '.ca-site-password-form label{'
        . 'display:flex;'
        . 'flex-direction:column;'
        . 'gap:var(--space-xs);'
        . 'text-align:left;'
        . '}'
        . '.ca-site-password-form input[type="password"]{'
        . 'width:100%;'
        . 'padding:var(--space-sm) var(--space-md);'
        . 'border:1px solid var(--color-grey-dark);'
        . 'border-radius:4px;'
        . 'background:var(--color-background);'
        . 'color:var(--color-text-primary);'
        . '}'
        . '.ca-site-password-form button{'
        . 'width:100%;'
        . 'background:var(--color-background-button);'
        . 'color:var(--color-white);'
        . 'padding:var(--space-sm) var(--space-md);'
        . 'border:none;'
        . 'border-radius:4px;'
        . 'cursor:pointer;'
        . 'font-size:1rem;'
        . 'font-weight:600;'
        . '}'
        . '.ca-site-password-form button:hover{background:var(--color-background-button-hover);}'
        . '.ca-site-password-error{color:var(--color-error);}'
        . '.ca-site-password-logo{width:min(195px,45vw);display:block;margin:0 auto var(--space-md);}'
        . '@media (min-width:768px){'
        . '.ca-site-password-wrapper{padding:calc(var(--space-xl) + var(--space-lg)) var(--space-xl);}'
        . '.ca-site-password-form{font-size:var(--space-lg);}'
        . '.ca-site-password-logo{width:195px;}'
        . '}';

    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
        . esc_html__('Protected Site', 'chassesautresor-com')
        . '</title><link rel="stylesheet" href="'
        . $style_url
        . '"><style>'
        . $styles
        . '</style></head><body><div class="ca-site-password-wrapper">'
        . '<form class="ca-site-password-form" method="post">'
        . '<img class="ca-site-password-logo" src="'
        . $svg_url
        . '" alt="'
        . esc_attr__('Pirate skull', 'chassesautresor-com')
        . '"><label>'
        . esc_html__('Password:', 'chassesautresor-com')
        . ' <input type="password" name="'
        . esc_attr($field)
        . '"></label><button type="submit">'
        . esc_html__('Submit', 'chassesautresor-com')
        . '</button>'
        . ($error_message ? '<p class="ca-site-password-error">' . $error_message . '</p>' : '')
        . '</form></div></body></html>';

    exit;
}

add_action('init', 'ca_site_password_protection');
