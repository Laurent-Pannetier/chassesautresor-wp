<?php
/**
 * Customizes the password reset email.
 *
 * @package chassesautresor-com
 */

defined('ABSPATH') || exit();

/**
 * Filters the password reset notification email to use the HTML template.
 *
 * @param array   $email      Default email arguments.
 * @param string  $key        The activation key.
 * @param string  $user_login The username for the user.
 * @param WP_User $user_data  WP_User object.
 *
 * @return array
 */
function cta_retrieve_password_notification_email(array $email, string $key, string $user_login, $user_data): array
{
    $subject = esc_html__(
        'Réinitialisation de votre mot de passe',
        'chassesautresor-com'
    );

    $reset_url = function_exists('network_site_url')
        ? network_site_url(
            'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode($user_login),
            'login'
        )
        : '';

    $content  = '<p>' . sprintf(
        /* translators: %s: User display name */
        esc_html__('Bonjour %s,', 'chassesautresor-com'),
        $user_data->display_name
    ) . '</p>';
    $content .= '<p>' . esc_html__(
        'Vous avez demandé la réinitialisation de votre mot de passe. '
        . 'Cliquez sur le bouton ci-dessous pour en définir un nouveau.',
        'chassesautresor-com'
    ) . '</p>';
    $content .= '<p style="margin-top:20px;"><a href="' . esc_url($reset_url) . '" '
        . 'style="display:inline-block;padding:10px 20px;background:#0B132B;color:#ffffff;text-decoration:none;">'
        . esc_html__('Réinitialiser mon mot de passe', 'chassesautresor-com')
        . '</a></p>';

    $email['to']      = $user_data->user_email ?? '';
    $email['subject'] = $subject;
    $email['message'] = cta_render_email_template($subject, $content);

    $headers = $email['headers'] ?? [];
    if (!is_array($headers)) {
        $headers = $headers ? preg_split("/(\r\n|\r|\n)/", (string) $headers) : [];
    }
    $has_type = false;
    foreach ($headers as $header) {
        if (stripos($header, 'Content-Type:') === 0) {
            $has_type = true;
            break;
        }
    }
    if (!$has_type) {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
    }
    $email['headers'] = $headers;

    return $email;
}
add_filter('retrieve_password_notification_email', 'cta_retrieve_password_notification_email', 10, 4);
