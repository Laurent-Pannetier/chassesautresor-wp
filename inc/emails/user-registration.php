<?php
/**
 * Customizes the new user registration email.
 *
 * @package chassesautresor-com
 */

defined('ABSPATH') || exit();

/**
 * Filters the new user notification email to use the HTML template.
 *
 * @param array   $email    Default email arguments.
 * @param WP_User $user     Registered user object.
 * @param string  $blogname Site name.
 *
 * @return array
 */
function cta_new_user_notification_email(array $email, $user, string $blogname): array
{
    $subject = esc_html__('Bienvenue chez chassesautresor.com', 'chassesautresor-com');

    $key = function_exists('get_password_reset_key') ? get_password_reset_key($user) : '';
    $reset_url = function_exists('network_site_url')
        ? network_site_url('wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode($user->user_login), 'login')
        : '';

    $content  = '<p>' . sprintf(
        /* translators: %s: User display name */
        esc_html__('Bonjour %s,', 'chassesautresor-com'),
        $user->display_name
    ) . '</p>';
    $content .= '<p>' . esc_html__(
        'Merci de votre inscription. Cliquez sur le bouton ci-dessous pour définir votre mot de passe.',
        'chassesautresor-com'
    ) . '</p>';
    $content .= '<p style="margin-top:20px;"><a href="' . esc_url($reset_url) . '" style="display:inline-block;padding:10px 20px;background:#0B132B;color:#ffffff;text-decoration:none;">' . esc_html__('Configurer mon mot de passe', 'chassesautresor-com') . '</a></p>';

    $email['to']      = $user->user_email;
    $email['subject'] = $subject;
    $email['message'] = cta_render_email_template($subject, $content);

    $headers = $email['headers'] ?? [];
    if (!is_array($headers)) {
        $headers = $headers ? preg_split("/(\r\n|\r|\n)/", (string) $headers) : [];
    }
    $has_type  = false;
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
add_filter('wp_new_user_notification_email', 'cta_new_user_notification_email', 10, 3);
