<?php
defined('ABSPATH') || exit;

    // ==================================================
    // 🎨 AFFICHAGE STYLISÉ DES ÉNIGMES
    // ==================================================
    /**
     * 🔹 afficher_enigme_stylisee() → Affiche l’énigme avec son style d’affichage (structure unique + blocs surchargeables)
     * 🔸 enigme_get_partial() → Charge un partiel adapté au style (ex: pirate/images.php), avec fallback global.
     */

    /**     
     * Affiche l’énigme avec son style et son état selon le contexte utilisateur.
     *
     * @param int $enigme_id ID de l’énigme à afficher.
     * @param array $statut_data Données de statut retournées par traiter_statut_enigme().
     */
    function afficher_enigme_stylisee(int $enigme_id, array $statut_data = []): void
    {
        if (get_post_type($enigme_id) !== 'enigme') return;

        if (!empty($statut_data)) {
            // statut_data transmis
        } else {
            // Aucune donnée statut_data transmise à afficher_enigme_stylisee()
        }

        $etat = get_field('enigme_cache_etat_systeme', $enigme_id) ?? 'accessible';

        if ($etat !== 'accessible' && !utilisateur_peut_modifier_enigme($enigme_id)) {
            echo '<div class="enigme-inaccessible">';
            echo '<p>🔒 Cette énigme n’est pas accessible actuellement.</p>';
            echo '<p><a href="' . esc_url(home_url('/')) . '" class="bouton-retour-home">← Retour à l’accueil</a></p>';
            echo '</div>';
            return;
        }

        if (!empty($statut_data['afficher_message'])) {
            echo $statut_data['message_html'];
        }

        $user_id = get_current_user_id();
        $style = get_field('enigme_style_affichage', $enigme_id) ?? 'defaut';

        echo '<div class="enigme-affichage enigme-style-' . esc_attr($style) . '">';

        foreach (['titre', 'images', 'texte', 'bloc-reponse', 'solution', 'retour-chasse'] as $slug) {
            enigme_get_partial($slug, $style, [
                'post_id' => $enigme_id,
                'user_id' => $user_id,
            ]);
        }

        echo '</div>';
    }


    /**
     * Charge un partiel adapté au style d’énigme (ex: pirate/images.php), avec fallback global.
     *
     * @param string $slug   Nom du bloc (titre, images, etc.)
     * @param string $style  Style d’affichage (ex : 'pirate', 'vintage')
     * @param array  $args   Données à transmettre au partial
     */
    function enigme_get_partial(string $slug, string $style = 'defaut', array $args = []): void
    {
        $base_path = "template-parts/enigme/partials";

        // 🧠 Nouveau : on préfixe tous les fichiers par 'enigme-partial-'
        $slug_final = 'enigme-partial-' . $slug;

        $variant = "{$base_path}/{$style}/{$slug_final}.php";
        $fallback = "{$base_path}/{$slug_final}.php";

        if (locate_template($variant)) {
            get_template_part("{$base_path}/{$style}/{$slug_final}", null, $args);
        } elseif (locate_template($fallback)) {
            get_template_part("{$base_path}/{$slug_final}", null, $args);
        } else {
            error_log("❌ Aucun partial trouvé pour $slug (style: $style)");
        }
    }


