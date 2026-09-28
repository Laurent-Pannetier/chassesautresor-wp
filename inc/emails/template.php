<?php
/**
 * Email template helpers.
 *
 * @package chassesautresor-com
 */

defined('ABSPATH') || exit();

/**
 * Renders the HTML email template without Twig.
 *
 * @param string $title   Email title.
 * @param string $content Email body content.
 *
 * @return string
 */
function cta_render_email_template(string $title, string $content): string
{
    $content      = function_exists('wp_kses_post') ? wp_kses_post($content) : $content;
    $mentions_url = function_exists('home_url') ? home_url('/mentions-legales/') : '';
    $home_url     = function_exists('home_url') ? home_url('/') : '';
    $logo_header  = function_exists('get_theme_file_uri') ? get_theme_file_uri('assets/images/logo-cat_icone-s.png') : '';
    $logo_footer  = function_exists('get_theme_file_uri') ? get_theme_file_uri('assets/images/logo-cat_hz-txt.png') : '';

    $title_html = function_exists('esc_html') ? esc_html($title) : $title;

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
        <head>
            <meta charset="UTF-8" />
        </head>
        <body>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                <tr>
                    <td>
                        <header style="background:#0B132B;padding:20px;text-align:center;">
                            <img src="<?php echo esc_url($logo_header); ?>"
                                alt="<?php echo esc_attr__('Chasses au Trésor', 'chassesautresor-com'); ?>"
                                style="max-width:150px;height:auto;display:block;margin:0 auto 10px;" />
                            <h1 style="color:#ffffff;font-family:Arial,sans-serif;font-size:24px;margin:0;">
                                <?php echo $title_html; ?>
                            </h1>
                        </header>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px;font-family:Arial,sans-serif;">
                        <?php echo $content; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <footer style="background:#0B132B;padding:20px;text-align:center;font-family:Arial,sans-serif;color:#ffffff;font-size:12px;">
                            <p style="margin:0;">
                                <a href="<?php echo esc_url($mentions_url); ?>" style="color:#ffffff;text-decoration:none;">
                                    <?php echo esc_html__('Mentions légales', 'chassesautresor-com'); ?>
                                </a>
                            </p>
                            <p style="margin:10px 0 0;">
                                <a href="<?php echo esc_url($home_url); ?>" style="display:inline-block;">
                                    <img src="<?php echo esc_url($logo_footer); ?>"
                                        alt="<?php echo esc_attr__('Chasses au Trésor', 'chassesautresor-com'); ?>"
                                        style="max-width:150px;height:auto;" />
                                </a>
                            </p>
                        </footer>
                    </td>
                </tr>
            </table>
        </body>
    </html>
    <?php

    return (string) ob_get_clean();
}

/**
 * Sends an HTML email using the template.
 *
 * @param array|string $to          Recipient or list of recipients.
 * @param string       $subject_raw Email subject.
 * @param string       $body        Email body content.
 * @param array        $headers     Additional headers.
 *
 * @return bool
 */
function cta_send_email($to, string $subject_raw, string $body, array $headers = []): bool
{
    $has_content_type = false;
    foreach ($headers as $header) {
        if (0 === stripos($header, 'Content-Type:')) {
            $has_content_type = true;
            break;
        }
    }

    if (!$has_content_type) {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
    }

    $from = function_exists('apply_filters') ? apply_filters('cta_send_email_from', '') : '';
    if ($from) {
        foreach ($headers as $index => $header) {
            if (0 === stripos($header, 'From:')) {
                unset($headers[$index]);
            }
        }
        $headers[] = 'From: ' . $from;
    }

    $title = (string) preg_replace('/^\[[^\]]+\]\s*/', '', $subject_raw);
    $html  = cta_render_email_template($title, $body);

    $subject = function_exists('wp_encode_mime_header')
        ? wp_encode_mime_header($subject_raw)
        : mb_encode_mimeheader($subject_raw, 'UTF-8', 'B', "\r\n");

    return function_exists('wp_mail') ? wp_mail($to, $subject, $html, $headers) : false;
}

