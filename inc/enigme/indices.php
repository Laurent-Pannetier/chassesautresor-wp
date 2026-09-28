<?php
defined('ABSPATH') || exit;

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
    global $wpdb;
    $table = $wpdb->prefix . 'indices_deblocages';
    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM {$table} WHERE user_id = %d AND indice_id = %d LIMIT 1",
        $user_id,
        $indice_id
    ));
}

/**
 * AJAX handler to unlock a hint.
 *
 * @return void
 */
function debloquer_indice(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte', 403);
    }

    $indice_id = isset($_POST['indice_id']) ? (int) $_POST['indice_id'] : 0;
    if ($indice_id <= 0 || get_post_type($indice_id) !== 'indice') {
        wp_send_json_error('indice_invalide', 400);
    }

    $user_id = get_current_user_id();

    if (indice_est_debloque($user_id, $indice_id)) {
        $contenu   = get_field('indice_contenu', $indice_id) ?: '';
        $processed = function_exists('apply_filters')
            ? apply_filters('the_content', $contenu)
            : $contenu;
        $texte = function_exists('wp_kses_post')
            ? wp_kses_post($processed)
            : htmlspecialchars($processed, ENT_QUOTES);
        $image_id = get_field('indice_image', $indice_id);
        $image    = '';
        if ($image_id) {
            $thumb = wp_get_attachment_image($image_id, 'thumbnail');
            $full  = wp_get_attachment_image_url($image_id, 'full');
            $image = $full
                ? '<a href="' . esc_url($full) . '" class="image eyebox-trigger" data-full="' . esc_url($full) . '">' . $thumb . '<i class="fa-solid fa-eye eyebox-icon" aria-hidden="true"></i></a>'
                : $thumb;
        }
        $html = '<div class="indice-contenu">';
        if ($image !== '') {
            $html .= '<div class="indice-contenu__image">' . $image . '</div>';
        }
        $html .= '<div class="indice-contenu__texte">' . $texte . '</div></div>';
        wp_send_json_success([
            'html'    => $html,
            'points'  => function_exists('get_user_points') ? get_user_points($user_id) : 0,
            'message' => esc_html__('Indice débloqué', 'chassesautresor-com'),
        ]);
    }

    $cout        = (int) get_field('indice_cout_points', $indice_id);
    $chasse_raw  = get_field('indice_chasse_linked', $indice_id);
    if (is_array($chasse_raw)) {
        $first     = $chasse_raw[0] ?? null;
        $chasse_id = is_array($first) ? (int) ($first['ID'] ?? 0) : (int) $first;
    } else {
        $chasse_id = (int) $chasse_raw;
    }
    $enigme_id = (int) get_field('indice_enigme_linked', $indice_id);

    if ($cout > 0) {
        deduire_points_utilisateur(
            $user_id,
            $cout,
            __('Déblocage indice', 'chassesautresor-com'),
            'indice',
            $indice_id
        );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'indices_deblocages';
    $wpdb->insert($table, [
        'user_id'        => $user_id,
        'indice_id'      => $indice_id,
        'chasse_id'      => $chasse_id ?: null,
        'enigme_id'      => $enigme_id ?: null,
        'points_depenses'=> $cout,
        'date_deblocage' => current_time('mysql', 1),
    ], ['%d', '%d', '%d', '%d', '%d', '%s']);

    $wpdb->insert($wpdb->prefix . 'engagements', [
        'user_id'        => $user_id,
        'enigme_id'      => $enigme_id ?: null,
        'chasse_id'      => $chasse_id ?: null,
        'indice_id'      => $indice_id,
        'date_engagement'=> current_time('mysql', 1),
    ], ['%d', '%d', '%d', '%d', '%s']);

    $points_restants = function_exists('get_user_points') ? get_user_points($user_id) : 0;
    $contenu         = get_field('indice_contenu', $indice_id) ?: '';
    $processed       = function_exists('apply_filters')
        ? apply_filters('the_content', $contenu)
        : $contenu;
    $texte           = function_exists('wp_kses_post')
        ? wp_kses_post($processed)
        : htmlspecialchars($processed, ENT_QUOTES);
    $image_id = get_field('indice_image', $indice_id);
    $image    = '';
    if ($image_id) {
        $thumb = wp_get_attachment_image($image_id, 'thumbnail');
        $full  = wp_get_attachment_image_url($image_id, 'full');
        $image = $full
            ? '<a href="' . esc_url($full) . '" class="image eyebox-trigger" data-full="' . esc_url($full) . '">' . $thumb . '<i class="fa-solid fa-eye eyebox-icon" aria-hidden="true"></i></a>'
            : $thumb;
    }
    $html = '<div class="indice-contenu">';
    if ($image !== '') {
        $html .= '<div class="indice-contenu__image">' . $image . '</div>';
    }
    $html .= '<div class="indice-contenu__texte">' . $texte . '</div></div>';

    wp_send_json_success([
        'html'    => $html,
        'points'  => $points_restants,
        'message' => esc_html__('Indice débloqué', 'chassesautresor-com'),
    ]);
}
add_action('wp_ajax_debloquer_indice', 'debloquer_indice');
add_action('wp_ajax_nopriv_debloquer_indice', 'debloquer_indice');

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
        'texts'   => [
            'solde' => __('Solde', 'chassesautresor-com'),
            'pts'   => __('pts', 'chassesautresor-com'),
            'unlock'=> __('Débloquer l\'indice', 'chassesautresor-com'),
            'close' => __('Fermer', 'chassesautresor-com'),
        ],
    ]);
}
add_action('wp_enqueue_scripts', 'charger_script_deblocage_indice');
