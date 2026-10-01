<?php
defined('ABSPATH') || exit;

if (!function_exists('cat_get_hint_unlock_service')) {
    function cat_get_hint_unlock_service(): ChassesAuTresor\Core\Progress\HintUnlockService
    {
        global $wpdb;
        return ChassesAuTresor\Core\Support\CoreServiceFactory::hintUnlock($wpdb);
    }
}

/**
 * Retrieve the display title for an indice.
 *
 * @param int|WP_Post $post Indice object or ID.
 * @return string
 */
function get_indice_title($post): string
{
    $post        = get_post($post);
    if (!$post) {
        return '';
    }

    $title   = (string) $post->post_title;
    $rank    = (int) get_post_meta($post->ID, 'indice_rank', true);
    $default = defined('TITRE_DEFAUT_INDICE') ? TITRE_DEFAUT_INDICE : '';
    $prefix  = defined('INDICE_DEFAULT_PREFIX') ? INDICE_DEFAULT_PREFIX : '';

    if (
        $title === ''
        || $title === $default
        || ($prefix !== '' && strpos($title, $prefix) === 0)
    ) {
        return sprintf(__('Indice #%d', 'chassesautresor-com'), $rank);
    }

    return $title;
}

/**
 * Check if a hint has been unlocked by a user.
 *
 * @param int $user_id   User identifier.
 * @param int $indice_id Hint identifier.
 * @return bool
 */
function indice_est_debloque(int $user_id, int $indice_id): bool
{
    return cat_get_hint_unlock_service()->isUnlocked($user_id, $indice_id);
}

function debloquer_indice(): void
{
    ChassesAuTresor\Core\Progress\HintUnlockAjaxHandler::handle();
}

/**
 * Enqueue script for hint unlocking on enigma pages.
 */
function charger_script_deblocage_indice(): void
{
    if (!is_singular('enigme')) {
        return;
    }

    $path = '/assets/js/indices-deblocage.js';
    wp_enqueue_script(
        'indices-deblocage',
        get_stylesheet_directory_uri() . $path,
        [],
        filemtime(get_stylesheet_directory() . $path),
        true
    );

    wp_localize_script('indices-deblocage', 'indicesUnlock', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('unlock_hint'),
        'texts'   => [
            'solde' => __('Solde', 'chassesautresor-com'),
            'pts'   => __('pts', 'chassesautresor-com'),
            'unlock'=> __('Débloquer l\'indice', 'chassesautresor-com'),
            'close' => __('Fermer', 'chassesautresor-com'),
        ],
    ]);
}
add_action('wp_enqueue_scripts', 'charger_script_deblocage_indice');
