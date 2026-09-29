<?php
defined('ABSPATH') || exit;

if (!class_exists(ChassesAuTresor\Core\Progress\RiddleEngagementService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Progress/RiddleEngagementRepository.php';
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Progress/RiddleEngagementService.php';
}

if (!function_exists('cat_get_riddle_engagement_service')) {
    function cat_get_riddle_engagement_service(): ChassesAuTresor\Core\Progress\RiddleEngagementService
    {
        global $wpdb;

        return new ChassesAuTresor\Core\Progress\RiddleEngagementService(
            new ChassesAuTresor\Core\Progress\RiddleEngagementRepository($wpdb)
        );
    }
}

    // ==================================================
    // 🧾 ENREGISTREMENT DES ENGAGEMENTS
    // ==================================================
    /**
     * 🔹 enregistrer_engagement_enigme() → Insère un engagement dans la table SQL `wp_engagements`.
     * 🔹 marquer_enigme_comme_engagee() → Met à jour le statut utilisateur ET enregistre un engagement SQL.
     */

    /**
     * Vérifie d’abord si un engagement identique existe déjà.
     *
     * @param int $user_id
     * @param int $enigme_id
     * @return bool True si insertion effectuée ou déjà existante.
     */
    function enregistrer_engagement_enigme(int $user_id, int $enigme_id): bool
    {
        $engagement = cat_get_riddle_engagement_service()->ensureEngaged(
            $user_id,
            $enigme_id,
            current_time('mysql')
        );
        if ($engagement['created']) {
            do_action('enigme_engagement_created', $enigme_id);
        }

        return $engagement['success'];
    }


    /**
     * @param int $user_id
     * @param int $enigme_id
     * @return bool True si tout s’est bien passé.
     */
    function marquer_enigme_comme_engagee(int $user_id, int $enigme_id): bool
    {
        $ok1 = enigme_mettre_a_jour_statut_utilisateur($enigme_id, $user_id, 'en_cours', true);
        $ok2 = enregistrer_engagement_enigme($user_id, $enigme_id);
        return $ok1 && $ok2;
    }

    /**
     * Vérifie si un utilisateur est déjà engagé sur une énigme donnée.
     *
     * @param int $user_id   ID de l'utilisateur
     * @param int $enigme_id ID de l'énigme
     * @return bool True si un engagement existe
     */
    function utilisateur_est_engage_dans_enigme(int $user_id, int $enigme_id): bool
    {
        return cat_get_riddle_engagement_service()->isEngaged($user_id, $enigme_id);
    }
