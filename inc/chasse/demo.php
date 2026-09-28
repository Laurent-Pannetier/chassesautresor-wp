<?php
defined('ABSPATH') || exit();

/**
 * Vérifie si une chasse est marquée comme démo.
 *
 * @param int $chasse_id Identifiant de la chasse.
 *
 * @return bool
 */
if (!function_exists('ca_demo_is_demo_hunt')) {
    function ca_demo_is_demo_hunt(int $chasse_id): bool
    {
        static $cache = [];
    
        if ($chasse_id <= 0) {
            return false;
        }
    
        $overrides = $GLOBALS['force_demo_overrides'] ?? [];
        if (isset($overrides[$chasse_id])) {
            return (bool) $overrides[$chasse_id];
        }

        $current_user_id = function_exists('get_current_user_id')
            ? (int) get_current_user_id()
            : 0;

        $default_context = [
            'user_id' => $current_user_id,
        ];

        $cache_context = $default_context;

        if (function_exists('apply_filters')) {
            $cache_context = apply_filters('ca_demo_cache_context', $default_context, $chasse_id);
        }

        if (!is_array($cache_context)) {
            $cache_context = [];
        }

        if ($cache_context === []) {
            $cache_context = $default_context;
        }

        $normalized_context = [];

        foreach ($cache_context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $normalized_context[$key] = $value;

                continue;
            }

            if (is_object($value) && method_exists($value, '__toString')) {
                $normalized_context[$key] = (string) $value;

                continue;
            }

            $normalized_context[$key] = serialize($value);
        }

        $is_assoc_context = array_keys($normalized_context) !== range(0, count($normalized_context) - 1);

        if ($is_assoc_context) {
            ksort($normalized_context);
        }

        $cache_key_payload = [
            'chasse_id' => $chasse_id,
            'context'   => $normalized_context,
        ];

        $encoded_payload = function_exists('wp_json_encode')
            ? wp_json_encode($cache_key_payload)
            : json_encode($cache_key_payload);

        if (!is_string($encoded_payload) || $encoded_payload === '') {
            $encoded_payload = serialize($cache_key_payload);
        }

        $cache_key = 'ca_demo_' . md5($encoded_payload);

        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }
    
        if (!function_exists('get_organisateur_from_chasse')) {
            $cache[$cache_key] = false;

            return $cache[$cache_key];
        }

        $organisateur_id = get_organisateur_from_chasse($chasse_id);
        if (!$organisateur_id) {
            $cache[$cache_key] = false;

            return $cache[$cache_key];
        }
    
        $configured_logins = CA_DEMO_ORGANISATEUR_LOGINS;
        if (!is_array($configured_logins)) {
            $configured_logins = [];
        }
    
        $configured_logins = array_filter(array_map(
            static function ($login) {
                $login = is_string($login) ? trim($login) : '';
    
                return $login !== '' ? strtolower($login) : null;
            },
            $configured_logins
        ));
    
        /** @var string[] $allowed_logins */
        $allowed_logins = apply_filters('ca_demo_organisateur_logins', array_values($configured_logins));
        $allowed_logins = array_filter(array_map(
            static function ($login) {
                return is_string($login) ? strtolower(trim($login)) : null;
            },
            $allowed_logins
        ));
    
        if (empty($allowed_logins)) {
            $cache[$cache_key] = (bool) apply_filters(
                'ca_demo_is_demo_hunt',
                false,
                $chasse_id,
                [
                    'organisateur_id' => $organisateur_id,
                    'allowed_logins'  => [],
                ]
            );

            return $cache[$cache_key];
        }

        $associated_users = function_exists('get_field')
            ? get_field('utilisateurs_associes', $organisateur_id)
            : [];
        if (!is_array($associated_users) || empty($associated_users)) {
            $cache[$cache_key] = (bool) apply_filters(
                'ca_demo_is_demo_hunt',
                false,
                $chasse_id,
                [
                    'organisateur_id' => $organisateur_id,
                    'allowed_logins'  => $allowed_logins,
                ]
            );

            return $cache[$cache_key];
        }
    
        $is_demo = false;
    
        foreach ($associated_users as $user_entry) {
            if ($user_entry instanceof WP_User) {
                $user = $user_entry;
            } elseif (is_array($user_entry) && isset($user_entry['ID'])) {
                $user = get_user_by('id', (int) $user_entry['ID']);
            } elseif (is_numeric($user_entry)) {
                $user = get_user_by('id', (int) $user_entry);
            } else {
                $user = null;
            }
    
            if (!$user instanceof WP_User) {
                continue;
            }
    
            $login = strtolower($user->user_login);
            if (in_array($login, $allowed_logins, true)) {
                $is_demo = true;
                break;
            }
        }
    
        $filtered = (bool) apply_filters(
            'ca_demo_is_demo_hunt',
            $is_demo,
            $chasse_id,
            [
                'organisateur_id' => $organisateur_id,
                'allowed_logins'  => $allowed_logins,
            ]
        );
    
        $cache[$cache_key] = $filtered;

        return $cache[$cache_key];
    }
}

if (!function_exists('ca_demo_reset_user_progress')) {
    /**
     * @return bool|WP_Error
     */
    function ca_demo_reset_user_progress(int $chasse_id, int $user_id)
    {
        $log_error = static function (string $message): void {
            if (function_exists('cat_debug')) {
                cat_debug($message);
            } else {
                error_log($message);
            }
        };

        if ($chasse_id <= 0 || $user_id <= 0) {
            $log_error(sprintf('❌ [DEMO] Invalid identifiers provided for reset (hunt %d, user %d).', $chasse_id, $user_id));

            return new WP_Error(
                'invalid_ids',
                __('Identifiants de chasse ou d’utilisateur invalides.', 'chassesautresor-com')
            );
        }

        if (!ca_demo_is_demo_hunt($chasse_id)) {
            $log_error(sprintf('⚠️ [DEMO] Hunt %d is not marked as demo. Reset aborted for user %d.', $chasse_id, $user_id));

            return new WP_Error(
                'not_demo',
                __('Cette chasse ne peut pas être réinitialisée.', 'chassesautresor-com')
            );
        }

        global $wpdb;

        if (!isset($wpdb)) {
            $log_error(sprintf('❌ [DEMO] $wpdb is unavailable. Unable to reset hunt %d for user %d.', $chasse_id, $user_id));

            return new WP_Error(
                'wpdb_missing',
                __('Le service de base de données est indisponible.', 'chassesautresor-com')
            );
        }
    
        $chasse_id = (int) $chasse_id;
        $user_id   = (int) $user_id;
    
        $engagements_table = $wpdb->prefix . 'engagements';
        $statuts_table     = $wpdb->prefix . 'enigme_statuts_utilisateur';
        $tentatives_table  = $wpdb->prefix . 'enigme_tentatives';
        $indices_table     = $wpdb->prefix . 'indices_deblocages';
        $winners_table     = $wpdb->prefix . 'chasse_winners';
    
        $enigme_ids = [];
        if (function_exists('recuperer_enigmes_associees')) {
            $enigme_ids = array_map('intval', recuperer_enigmes_associees($chasse_id));
        }
    
        $db_enigmes = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT enigme_id FROM {$engagements_table} WHERE user_id = %d AND chasse_id = %d AND enigme_id IS NOT NULL",
                $user_id,
                $chasse_id
            )
        );
    
        if (!empty($db_enigmes)) {
            $enigme_ids = array_merge($enigme_ids, array_map('intval', $db_enigmes));
        }
    
        $enigme_ids = array_values(array_filter(array_unique($enigme_ids)));
    
        $indice_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT indice_id FROM {$indices_table} WHERE user_id = %d AND chasse_id = %d",
                $user_id,
                $chasse_id
            )
        );
    
        if (!empty($enigme_ids)) {
            $placeholders = implode(',', array_fill(0, count($enigme_ids), '%d'));
            $indices_from_enigmes = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT indice_id FROM {$indices_table} WHERE user_id = %d AND enigme_id IN ({$placeholders})",
                    array_merge([$user_id], $enigme_ids)
                )
            );
    
            if (!empty($indices_from_enigmes)) {
                $indice_ids = array_merge($indice_ids, array_map('intval', $indices_from_enigmes));
            }
        }
    
        $indice_ids = array_values(array_filter(array_unique(array_map('intval', $indice_ids))));
    
        $wpdb->delete($winners_table, ['user_id' => $user_id, 'chasse_id' => $chasse_id], ['%d', '%d']);
    
        $remaining_winners = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, date_win FROM {$winners_table} WHERE chasse_id = %d ORDER BY date_win ASC",
                $chasse_id
            )
        );
    
        $winner_names = [];
        $win_date     = null;
    
        foreach ($remaining_winners as $row) {
            $winner_id = isset($row->user_id) ? (int) $row->user_id : 0;
            $user      = $winner_id > 0 ? get_userdata($winner_id) : null;
    
            if ($user) {
                $display_name = $user->display_name ?: $user->user_login;
                if ($display_name !== '') {
                    $winner_names[] = $display_name;
                }
            }
    
            if ($win_date === null && !empty($row->date_win)) {
                $win_date = (string) $row->date_win;
            }
        }
    
        if (function_exists('update_field')) {
            $list = implode(', ', $winner_names);
            update_field('chasse_cache_gagnants', $list, $chasse_id);
    
            if ($win_date) {
                update_field('chasse_cache_date_decouverte', $win_date, $chasse_id);
            } elseif (function_exists('delete_field')) {
                delete_field('chasse_cache_date_decouverte', $chasse_id);
            } else {
                update_field('chasse_cache_date_decouverte', '', $chasse_id);
            }
    
            update_field('chasse_cache_complet', empty($winner_names) ? 0 : 1, $chasse_id);
        }
    
        $wpdb->delete($engagements_table, ['user_id' => $user_id, 'chasse_id' => $chasse_id], ['%d', '%d']);
    
        if (!empty($enigme_ids)) {
            $placeholders = implode(',', array_fill(0, count($enigme_ids), '%d'));
    
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$engagements_table} WHERE user_id = %d AND enigme_id IN ({$placeholders})",
                    array_merge([$user_id], $enigme_ids)
                )
            );
    
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$statuts_table} WHERE user_id = %d AND enigme_id IN ({$placeholders})",
                    array_merge([$user_id], $enigme_ids)
                )
            );
    
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$tentatives_table} WHERE user_id = %d AND enigme_id IN ({$placeholders})",
                    array_merge([$user_id], $enigme_ids)
                )
            );
    
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$indices_table} WHERE user_id = %d AND enigme_id IN ({$placeholders})",
                    array_merge([$user_id], $enigme_ids)
                )
            );
        }
    
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$indices_table} WHERE user_id = %d AND chasse_id = %d",
                $user_id,
                $chasse_id
            )
        );
    
        if (function_exists('delete_user_meta')) {
            delete_user_meta($user_id, "souscription_chasse_{$chasse_id}");
    
            foreach ($enigme_ids as $enigme_id) {
                delete_user_meta($user_id, "statut_enigme_{$enigme_id}");
                delete_user_meta($user_id, "enigme_{$enigme_id}_resolution_date");
            }
    
            foreach ($indice_ids as $indice_id) {
                delete_user_meta($user_id, "indice_debloque_{$indice_id}");
            }
        }
    
        if (function_exists('clean_user_cache')) {
            clean_user_cache($user_id);
        }
    
        if (function_exists('mettre_a_jour_statuts_chasse')) {
            mettre_a_jour_statuts_chasse($chasse_id);
        }
    
        if (function_exists('chasse_clear_infos_affichage_cache')) {
            chasse_clear_infos_affichage_cache($chasse_id);
        }
    
        if (function_exists('enigme_clear_sidebar_cache')) {
            enigme_clear_sidebar_cache($chasse_id, $user_id);
        }
    
        return true;
    }
}

if (!function_exists('ca_demo_schedule_reset')) {
    function ca_demo_schedule_reset(int $chasse_id, int $user_id): void
    {
        $chasse_id = (int) $chasse_id;
        $user_id   = (int) $user_id;

        if ($chasse_id <= 0 || $user_id <= 0) {
            return;
        }

        if (function_exists('ca_demo_is_demo_hunt') && !ca_demo_is_demo_hunt($chasse_id)) {
            return;
        }

        $delay = (int) apply_filters('ca_demo_reset_delay', 5, $chasse_id, $user_id);
        if ($delay < 0) {
            $delay = 0;
        }

        $hook      = 'ca_demo_run_reset_event';
        $scheduled = false;

        if (function_exists('wp_schedule_single_event')) {
            if (function_exists('wp_next_scheduled')) {
                $existing = wp_next_scheduled($hook, [$chasse_id, $user_id]);
                if ($existing && function_exists('wp_unschedule_event')) {
                    wp_unschedule_event($existing, $hook, [$chasse_id, $user_id]);
                }
            }

            $timestamp = time() + $delay;
            $scheduled = wp_schedule_single_event($timestamp, $hook, [$chasse_id, $user_id]);
        }

        if ($scheduled) {
            cat_debug(sprintf('🗓️ [DEMO] Reset scheduled for hunt %d (user %d) in %d seconds.', $chasse_id, $user_id, $delay));
        } else {
            cat_debug(sprintf('⚠️ [DEMO] Reset scheduling failed for hunt %d (user %d), running immediately.', $chasse_id, $user_id));
            $result = ca_demo_reset_user_progress($chasse_id, $user_id);

            if (function_exists('is_wp_error') && is_wp_error($result)) {
                $message = $result->get_error_message();
                cat_debug(sprintf('❌ [DEMO] Immediate reset failed for hunt %d (user %d): %s', $chasse_id, $user_id, $message));
            }
        }

        if (function_exists('chasse_clear_infos_affichage_cache')) {
            chasse_clear_infos_affichage_cache($chasse_id);
        }

        if (function_exists('enigme_clear_sidebar_cache')) {
            enigme_clear_sidebar_cache($chasse_id, $user_id);
        }
    }

    add_action('ca_demo_schedule_reset', 'ca_demo_schedule_reset', 10, 2);
}

if (!function_exists('ca_demo_execute_scheduled_reset')) {
    function ca_demo_execute_scheduled_reset(int $chasse_id, int $user_id): void
    {
        $chasse_id = (int) $chasse_id;
        $user_id   = (int) $user_id;

        if ($chasse_id <= 0 || $user_id <= 0) {
            return;
        }

        cat_debug(sprintf('▶️ [DEMO] Running scheduled reset for hunt %d (user %d).', $chasse_id, $user_id));

        $result = ca_demo_reset_user_progress($chasse_id, $user_id);

        if (function_exists('is_wp_error') && is_wp_error($result)) {
            $message = $result->get_error_message();
            cat_debug(sprintf('❌ [DEMO] Scheduled reset failed for hunt %d (user %d): %s', $chasse_id, $user_id, $message));
        }

        if (function_exists('chasse_clear_infos_affichage_cache')) {
            chasse_clear_infos_affichage_cache($chasse_id);
        }

        if (function_exists('enigme_clear_sidebar_cache')) {
            enigme_clear_sidebar_cache($chasse_id, $user_id);
        }
    }

    add_action('ca_demo_run_reset_event', 'ca_demo_execute_scheduled_reset', 10, 2);
}

function ca_demo_reset_chasse_ajax(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error([
            'message' => __('Vous devez être connecté pour effectuer cette action.', 'chassesautresor-com'),
        ]);
    }

    $chasse_id = isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;
    $nonce     = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    $user_id   = get_current_user_id();

    if ($chasse_id <= 0 || $user_id <= 0) {
        wp_send_json_error([
            'message' => __('Requête invalide.', 'chassesautresor-com'),
        ]);
    }

    $expected_nonce = 'ca_demo_reset_chasse_' . $chasse_id . '_' . $user_id;
    if (!wp_verify_nonce($nonce, $expected_nonce)) {
        wp_send_json_error([
            'message' => __('Votre session a expiré. Rechargez la page puis réessayez.', 'chassesautresor-com'),
        ]);
    }

    if (!ca_demo_is_demo_hunt($chasse_id)) {
        wp_send_json_error([
            'message' => __('Cette chasse ne peut pas être réinitialisée.', 'chassesautresor-com'),
        ]);
    }

    if (!utilisateur_est_engage_dans_chasse($user_id, $chasse_id)) {
        wp_send_json_error([
            'message' => __('Vous ne participez pas à cette chasse.', 'chassesautresor-com'),
        ]);
    }

    $result = ca_demo_reset_user_progress($chasse_id, $user_id);

    if (function_exists('is_wp_error') && is_wp_error($result)) {
        $message = $result->get_error_message();
        if ($message === '') {
            $message = __('Impossible de réinitialiser votre progression pour le moment.', 'chassesautresor-com');
        }

        wp_send_json_error([
            'message' => $message,
        ]);
    }

    if ($result !== true) {
        wp_send_json_error([
            'message' => __('Impossible de réinitialiser votre progression pour le moment.', 'chassesautresor-com'),
        ]);
    }

    wp_send_json_success([
        'message' => __('Votre progression a été réinitialisée.', 'chassesautresor-com'),
    ]);
}
add_action('wp_ajax_ca_demo_reset_chasse', 'ca_demo_reset_chasse_ajax');
