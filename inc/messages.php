<?php

defined('ABSPATH') || exit;

/**
 * Create the table storing user and site messages.
 *
 * @return void
 */
function cat_install_user_messages_table(): void
{
    global $wpdb;

    $table          = $wpdb->prefix . 'user_messages';
    $charsetCollate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        message LONGTEXT NOT NULL,
        status VARCHAR(20) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NULL,
        locale VARCHAR(10) NULL,
        KEY user_id (user_id),
        KEY status (status),
        KEY expires_at (expires_at)
    ) {$charsetCollate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
add_action('after_switch_theme', 'cat_install_user_messages_table');

/**
 * Store a site-wide message.
 *
 * @param string      $type        Message type used as CSS class.
 * @param string      $content     Message content.
 * @param bool        $persistent  Whether the message should persist across sessions.
 * @param string|null $message_key Optional translation key.
 * @param string|null $locale      Optional locale for the message.
 * @param int|null    $expires     Expiration as timestamp or duration in seconds.
 * @param bool        $dismissible Whether the message can be dismissed.
 *
 * @return void
 */
function add_site_message(
    string $type,
    string $content,
    bool $persistent = false,
    ?string $message_key = null,
    ?string $locale = null,
    ?int $expires = null,
    bool $dismissible = false
): void
{
    $message = [
        'type'        => $type,
        'content'     => $content,
        'dismissible' => $dismissible,
    ];

    if ($message_key !== null) {
        $message['message_key'] = $message_key;
    }

    if ($locale !== null) {
        $message['locale'] = $locale;
    }

    if ($persistent) {
        $expiresAt = null;
        if ($expires !== null) {
            $now = (int) current_time('timestamp');
            if ($expires > $now) {
                $expiresAt = gmdate('c', $expires);
            } else {
                $expiresAt = gmdate('c', $now + $expires);
            }
        }

        global $wpdb;
        $repo = new UserMessageRepository($wpdb);
        $inserted = $repo->insert(0, wp_json_encode($message), 'site', $expiresAt, $locale);

        if ($inserted !== 0) {
            return;
        }

        error_log('Failed to insert site message, storing in session instead.');
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (headers_sent($file, $line)) {
            $fileInfo = is_string($file) && $file !== '' ? $file : 'unknown';
            $lineInfo = is_numeric($line) ? (string) $line : 'unknown';
            error_log(sprintf('[add_site_message] headers already sent (%s:%s), skipping session storage.', $fileInfo, $lineInfo));

            return;
        }

        session_start();
    }

    $messages   = $_SESSION['cat_site_messages'] ?? [];
    $messages[] = $message;

    $_SESSION['cat_site_messages'] = $messages;
}

/**
 * Remove a persistent site-wide message.
 *
 * @param string $key Message translation key.
 *
 * @return void
 */
function remove_site_message(string $key): void
{
    global $wpdb;
    $repo = new UserMessageRepository($wpdb);
    $rows = $repo->get(0, 'site', null);
    foreach ($rows as $row) {
        $data = json_decode($row['message'], true);
        if (is_array($data) && ($data['message_key'] ?? '') === $key) {
            $repo->delete((int) $row['id']);
        }
    }
}

/**
 * Retrieve site-wide messages.
 *
 * @return string HTML content for the messages.
 */
function get_site_messages(): string
{
    $messages = [];

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (str_starts_with($uri, '/voir-image-enigme')) {
        error_log('[get_site_messages] bypass pour requête image');
        return '';
    }
    if (headers_sent($file, $line)) {
        error_log("[get_site_messages] headers déjà envoyés ($file:$line)");
        return '';
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
        error_log('[get_site_messages] session démarrée');
    }

    if (!empty($_SESSION['cat_site_messages'])) {
        $messages = array_merge($messages, $_SESSION['cat_site_messages']);
        unset($_SESSION['cat_site_messages']);
    }

    global $wpdb;
    $repo = new UserMessageRepository($wpdb);
    $repo->purgeExpired();
    $rows = $repo->get(0, 'site', false);
    foreach ($rows as $row) {
        $data = json_decode($row['message'], true);
        if (is_array($data)) {
            if (!empty($row['locale'])) {
                $data['locale'] = $row['locale'];
            }
            $messages[] = $data;
        }
    }

    if (!empty($messages)) {
        $unique   = [];
        $seenKeys = [];
        foreach ($messages as $msg) {
            $key = $msg['message_key'] ?? null;
            if ($key !== null) {
                if (isset($seenKeys[$key])) {
                    continue;
                }
                $seenKeys[$key] = true;
            }
            $unique[] = $msg;
        }
        $messages = $unique;
    }

    if (empty($messages)) {
        return '';
    }

    $output = array_map(
        function (array $msg): string {
            $content = $msg['content'] ?? '';
            if (!empty($msg['message_key'])) {
                if (!empty($msg['locale']) && function_exists('switch_to_locale')) {
                    switch_to_locale($msg['locale']);
                    $content = __($msg['message_key'], 'chassesautresor-com');
                    restore_previous_locale();
                } else {
                    $content = __($msg['message_key'], 'chassesautresor-com');
                }
            }

            $button = '';
            if (!empty($msg['dismissible']) && !empty($msg['message_key'])) {
                $button = ' <button type="button" class="message-close" data-key="'
                    . esc_attr($msg['message_key'])
                    . '" aria-label="'
                    . esc_attr__('Supprimer ce message', 'chassesautresor-com')
                    . '">×</button>';
            }

            $type = $msg['type'] ?? 'info';
            switch ($type) {
                case 'success':
                    $class = 'message-succes';
                    $aria  = 'role="status" aria-live="polite"';
                    break;
                case 'error':
                    $class = 'message-erreur';
                    $aria  = 'role="alert" aria-live="assertive"';
                    break;
                default:
                    $class = 'message-info';
                    $aria  = 'role="status" aria-live="polite"';
                    break;
            }

            return '<p class="' . esc_attr($class) . '" ' . $aria . '>' . esc_html($content) . $button . '</p>';
        },
        $messages
    );

    return implode('', $output);
}

/**
 * Print all site-wide and account-specific messages.
 *
 * @return void
 */
function print_site_messages(): void
{
    $messages = get_site_messages();

    if (function_exists('myaccount_get_important_messages')) {
        $messages .= myaccount_get_important_messages();
    }

    echo $messages; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Remove legacy test messages left in the database.
 *
 * @return void
 */
function cat_remove_legacy_test_messages(): void
{
    if (get_option('cat_removed_test_messages')) {
        return;
    }

    global $wpdb;
    $repo = new UserMessageRepository($wpdb);
    $rows = $repo->get(0, 'site', null);

    foreach ($rows as $row) {
        $data = json_decode($row['message'], true);
        if (is_array($data) && ($data['content'] ?? '') === 'prout') {
            $repo->delete((int) $row['id']);
        }
    }

    update_option('cat_removed_test_messages', 1);
}
add_action('init', 'cat_remove_legacy_test_messages');

