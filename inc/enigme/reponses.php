<?php
defined('ABSPATH') || exit;




    // ==================================================
    // 📬 GESTION DES RÉPONSES MANUELLES (FRONTEND)
    // ==================================================

    // 🔹 afficher_formulaire_reponse_manuelle() → Affiche le formulaire de réponse manuelle (frontend).
    // 🔹 utilisateur_peut_repondre_manuelle() → Vérifie si l'utilisateur peut répondre à une énigme manuelle.
    // 🔹 soumettre_reponse_manuelle() → Traite la soumission d'une réponse manuelle (frontend).



    /**
     * Affiche le formulaire de réponse manuelle pour une énigme.
     *
     * @param int $enigme_id L'ID de l'énigme.
     * @return string HTML du formulaire.
     */
    function afficher_formulaire_reponse_manuelle($enigme_id)
    {
        if (!is_user_logged_in()) {
            return '<p>Veuillez vous connecter pour répondre à cette énigme.</p>';
        }

        $user_id = get_current_user_id();

        if (!utilisateur_peut_repondre_manuelle($user_id, $enigme_id)) {
            return '<p>Vous ne pouvez plus répondre à cette énigme.</p>';
        }

        $mode_validation = get_field('enigme_mode_validation', $enigme_id);
        $badge_html      = '';
        if ($mode_validation !== 'aucune') {
            if ($mode_validation === 'automatique') {
                $icon_html = trim(get_svg_icon('automatic'));
                $message   = __("Mode de validation de l'énigme automatique. Vous connaîtrez le résultat de votre tentative immédiatement après l'avoir soumise.", 'chassesautresor-com');
            } else {
                $icon_html         = '<i class="fa-solid fa-envelope" aria-hidden="true"></i>';
                $chasse_id         = function_exists('recuperer_id_chasse_associee') ? (int) recuperer_id_chasse_associee($enigme_id) : 0;
                $organisateur_id   = $chasse_id ? get_organisateur_from_chasse($chasse_id) : 0;
                $organisateur_nom  = $organisateur_id ? get_the_title($organisateur_id) : '';
                $organisateur_lien = $organisateur_id ? get_permalink($organisateur_id) : '#';
                $message           = sprintf(
                    __("Mode de validation de l'énigme manuelle. Vous connaîtrez le résultat de votre tentative après son traitement par %s.", 'chassesautresor-com'),
                    '<a href="' . esc_url($organisateur_lien) . '">' . esc_html($organisateur_nom) . '</a>'
                );
            }
            $badge_html = '<button type="button" class="badge-validation" data-tooltip="'
                . esc_attr($message)
                . '">' . $icon_html . '</button>';
        }

        $data  = calculer_contexte_points($user_id, $enigme_id);
        $nonce = wp_create_nonce('reponse_manuelle_nonce');
        global $wpdb;
        $retry_state = ChassesAuTresor\Core\Support\CoreServiceFactory::riddleRetry($wpdb)->getState(
            (int) $user_id,
            (int) $enigme_id
        );
        ob_start();
    ?>
    <form
        method="post"
        class="bloc-reponse formulaire-reponse-manuelle"
        data-cout="<?php echo esc_attr($data['cout']); ?>"
        data-solde-avant="<?php echo esc_attr($data['solde_avant']); ?>"
        data-solde-apres="<?php echo esc_attr($data['solde_apres']); ?>"
        data-seuil="<?php echo esc_attr($data['seuil']); ?>"
        data-retry-state="<?php echo esc_attr(wp_json_encode($retry_state)); ?>"
    >
        <h3><?php echo $badge_html . esc_html__('Votre réponse', 'chassesautresor-com'); ?></h3>
        <?php if ($data['points_manquants'] > 0) : ?>
            <p class="message-limite" data-points="manquants">
                <?php echo esc_html(sprintf(__('Il vous manque %d points pour soumettre votre réponse.', 'chassesautresor-com'), $data['points_manquants'])); ?>
            </p>
        <?php else : ?>
            <textarea name="reponse_manuelle" id="reponse_manuelle_<?php echo esc_attr($enigme_id); ?>" rows="3" required></textarea>
        <?php endif; ?>
        <input type="hidden" name="enigme_id" value="<?php echo esc_attr($enigme_id); ?>">
        <input type="hidden" name="reponse_manuelle_nonce" value="<?php echo esc_attr($nonce); ?>">
        <div class="reponse-cta-row">
            <?php if ($data['points_manquants'] > 0) : ?>
                <a href="<?php echo esc_url($data['boutique_url']); ?>" class="bouton-cta points-manquants" title="<?php echo esc_attr__('Accéder à la boutique', 'chassesautresor-com'); ?>">
                    <span class="points-plus-circle">+</span>
                    <?php echo esc_html__('Ajouter des points', 'chassesautresor-com'); ?>
                </a>
            <?php else : ?>
                <button type="submit" class="bouton-cta"><?php echo esc_html($data['label_btn']); ?></button>
            <?php endif; ?>
        </div>
        <?php if ($data['points_manquants'] <= 0 && $data['cout'] > 0) : ?>
            <p class="points-sousligne txt-small">
                <?php echo esc_html(sprintf(__('Solde : %1$d → %2$d pts', 'chassesautresor-com'), $data['solde_avant'], $data['solde_apres'])); ?>
            </p>
        <?php endif; ?>
    </form>
    <div class="reponse-feedback" style="display:none"></div>
    <?php
        return ob_get_clean();
    }




    /**
     * Intercepte et traite la soumission d'une réponse manuelle à une énigme (frontend).
     *
     * Conditions :
     * - utilisateur connecté
     * - champ réponse + nonce + enigme_id présents
     * - nonce valide
     */
    // ==================================================
    // ✉️ ENVOI D'EMAILS (RÉPONSES MANUELLES)
    // ==================================================

    // 🔹 envoyer_mail_reponse_manuelle() → Envoie un mail HTML à l'organisateur avec la réponse (expéditeur = joueur).
    // 🔹 envoyer_mail_resultat_joueur() → Envoie un mail HTML au joueur après validation ou refus de sa réponse.
    // 🔹 envoyer_mail_accuse_reception_joueur() → Envoie un accusé de réception au joueur juste après sa soumission.

    /**
     * Envoie un email à l'organisateur avec la réponse manuelle soumise.
     *
     * @param int    $user_id
     * @param int    $enigme_id
     * @param string $reponse
     * @param string $uid
     */
/**
 * Charge le script gérant la soumission automatique des réponses.
 */
function charger_script_reponse_automatique() {
    if (is_singular('enigme')) {
        charger_script_delai_soumission();
        $path = '/assets/js/reponse-automatique.js';
        wp_enqueue_script(
            'reponse-automatique',
            get_stylesheet_directory_uri() . $path,
            ['riddle-retry-countdown', 'enigme-image-viewer'],
            filemtime(get_stylesheet_directory() . $path),
            true
        );
        wp_localize_script('reponse-automatique', 'RiddleSidebarAjax', [
            'nonce' => wp_create_nonce('riddle_sidebar'),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'charger_script_reponse_automatique');

function charger_script_etapes_enigme(): void
{
    if (!is_singular('enigme') || !is_user_logged_in()) {
        return;
    }

    charger_script_delai_soumission();
    wp_enqueue_style(
        'leaflet',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        [],
        '1.9.4'
    );
    wp_enqueue_script(
        'leaflet',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        [],
        '1.9.4',
        true
    );
    $path = '/assets/js/riddle-step-player.js';
    wp_enqueue_script(
        'riddle-step-player',
        get_stylesheet_directory_uri() . $path,
        ['riddle-retry-countdown', 'leaflet', 'enigme-image-viewer'],
        filemtime(get_stylesheet_directory() . $path),
        true
    );
    wp_localize_script('riddle-step-player', 'RiddleStepPlayer', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'error' => __('Impossible de valider cette étape.', 'chassesautresor-com'),
        'wrong' => __('Cette réponse n’est pas correcte.', 'chassesautresor-com'),
        'sequenceLabel' => __('Séquence saisie', 'chassesautresor-com'),
        'emptySequenceLabel' => __('vide', 'chassesautresor-com'),
        'safeValueLabel' => __('Valeur de la molette', 'chassesautresor-com'),
        'clockwiseLabel' => __('sens horaire', 'chassesautresor-com'),
        'counterclockwiseLabel' => __('sens antihoraire', 'chassesautresor-com'),
        'closeWidgetLabel' => __('Fermer', 'chassesautresor-com'),
    ]);
}
add_action('wp_enqueue_scripts', 'charger_script_etapes_enigme');

function charger_script_delai_soumission(): void
{
    if (wp_script_is('riddle-retry-countdown', 'enqueued')) {
        return;
    }

    $path = '/assets/js/riddle-retry-countdown.js';
    wp_enqueue_script(
        'riddle-retry-countdown',
        get_stylesheet_directory_uri() . $path,
        [],
        filemtime(get_stylesheet_directory() . $path),
        true
    );
    wp_localize_script('riddle-retry-countdown', 'RiddleRetryCountdownConfig', [
        'message' => __('Nouvelle tentative disponible dans', 'chassesautresor-com'),
    ]);
}

/**
 * Charge le script gérant la soumission manuelle des réponses.
 */
function charger_script_reponse_manuelle() {
    if (is_singular('enigme')) {
        charger_script_delai_soumission();
        $path = '/assets/js/reponse-manuelle.js';
        wp_enqueue_script(
            'reponse-manuelle',
            get_stylesheet_directory_uri() . $path,
            ['riddle-retry-countdown'],
            filemtime(get_stylesheet_directory() . $path),
            true
        );

        wp_localize_script('reponse-manuelle', 'REPONSE_MANUELLE_I18N', [
            'success'    => esc_html__('Tentative bien reçue.', 'chassesautresor-com'),
            'processing' => __(
                '⏳ Votre tentative %1$s a été soumise le %2$s à %3$s.<br>' .
                'Vous serez immédiatement averti de son traitement par l\'organisateur par email ' .
                'et sur votre <a href="%4$s">espace personnel</a>.',
                'chassesautresor-com'
            ),
            'accountUrl' => esc_url(home_url('/mon-compte/')),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'charger_script_reponse_manuelle');
