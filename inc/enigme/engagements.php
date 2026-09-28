<?php
defined('ABSPATH') || exit;

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
        global $wpdb;
        $table = $wpdb->prefix . 'engagements';

        $existe = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND enigme_id = %d",
            $user_id,
            $enigme_id
        ));

        if ($existe) return true;

        $result = $wpdb->insert($table, [
            'user_id'         => $user_id,
            'enigme_id'       => $enigme_id,
            'date_engagement' => current_time('mysql'),
        ], ['%d', '%d', '%s']);
        if ($result !== false) {
            do_action('enigme_engagement_created', $enigme_id);
        }

        return $result !== false;
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
        global $wpdb;
        if (!$user_id || !$enigme_id) {
            return false;
        }

        $table = $wpdb->prefix . 'engagements';

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM $table WHERE user_id = %d AND enigme_id = %d LIMIT 1",
            $user_id,
            $enigme_id
        ));
    }
