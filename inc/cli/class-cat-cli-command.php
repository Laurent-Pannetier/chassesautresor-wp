<?php

declare(strict_types=1);

/**
 * WP-CLI commands for chassesautresor.
 */
class Cat_CLI_Command
{
    /**
     * Migrate user and site messages to the wp_user_messages table.
     *
     * ## EXAMPLES
     *
     *     wp cat migrate-messages
     *
     * @return void
     */
    public function migrate_messages(): void
    {
        global $wpdb;

        $repo    = new UserMessageRepository($wpdb);
        $userIds = get_users(['fields' => 'ids']);

        foreach ($userIds as $userId) {
            $migrated = 0;

            $persistent = get_user_meta($userId, '_myaccount_messages', true);
            if (is_array($persistent)) {
                foreach ($persistent as $key => $msg) {
                    if (!is_array($msg)) {
                        continue;
                    }
                    $status    = isset($msg['status']) ? (string) $msg['status'] : 'persistent';
                    $expiresAt = isset($msg['expires_at']) ? (string) $msg['expires_at'] : null;

                    $payload       = $msg;
                    $payload['key'] = (string) $key;
                    unset($payload['status'], $payload['expires_at']);

                    $repo->insert($userId, wp_json_encode($payload), $status, $expiresAt);
                    $migrated++;
                }
                delete_user_meta($userId, '_myaccount_messages');
            }

            $flash = get_user_meta($userId, '_myaccount_flash_messages', true);
            if (is_array($flash)) {
                foreach ($flash as $msg) {
                    if (!is_array($msg)) {
                        continue;
                    }
                    $status    = isset($msg['status']) ? (string) $msg['status'] : 'flash';
                    $expiresAt = isset($msg['expires_at']) ? (string) $msg['expires_at'] : null;

                    $payload = $msg;
                    unset($payload['status'], $payload['expires_at']);

                    $repo->insert($userId, wp_json_encode($payload), $status, $expiresAt);
                    $migrated++;
                }
                delete_user_meta($userId, '_myaccount_flash_messages');
            }

            if ($migrated > 0) {
                \WP_CLI::log(
                    sprintf(
                        /* translators: 1: number of messages, 2: user ID. */
                        __('%1$d messages migrés pour l\'utilisateur %2$d', 'chassesautresor-com'),
                        $migrated,
                        $userId
                    )
                );
            }
        }

        \WP_CLI::success(__('Migration des messages terminée.', 'chassesautresor-com'));
    }
}

if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('cat', Cat_CLI_Command::class);
}
