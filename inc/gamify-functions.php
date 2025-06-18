<?php
defined( 'ABSPATH' ) || exit;


// ==================================================
// 📚 SOMMAIRE DU FICHIER : gamify-functions.php
// ==================================================
//
// 1. 💎 GESTION DES POINTS UTILISATEUR
//    - Lecture, mise à jour, affichage et attribution des points.
//    - Intégration WooCommerce et affichage du modal.
//
// 2. 💎 PROGRESSION DANS LA CHASSE
//    - Suivi des énigmes résolues et vérification de fin de chasse.
//
// 3. 🏆 TROPHÉES
//    - Attribution, affichage et gestion des trophées et badges.
//

// ==================================================
// 💎 GESTION DES POINTS UTILISATEUR
// ==================================================
/**
 * 🔹 get_user_points → Récupérer le solde de points d’un utilisateur.
 * 🔹 update_user_points → Mettre à jour le solde de points de l’utilisateur.
 * 🔹 attribuer_points_apres_achat → Attribuer les points après l’achat d’un pack de points.
 * 🔹 woocommerce_thankyou (function) → Attribuer les points et vider le panier après la commande.
 * 🔹 afficher_points_utilisateur_callback → Afficher les points de l’utilisateur selon le statut de l’énigme.
 * 🔹 ajouter_modal_points → Charger le script du modal des points en ajoutant un paramètre de version dynamique.
 * 🔹 utilisateur_a_assez_de_points → Vérifie si l'utilisateur a suffisamment de points pour une opération donnée.
 * 🔹 deduire_points_utilisateur → Déduit un montant de points à un utilisateur.
 * 🔹 ajouter_points_utilisateur → Ajoute un montant de points à un utilisateur .
 */

/**
 * 🔢 Récupère le solde de points d’un utilisateur.
 *
 * @param int|null $user_id ID de l'utilisateur (par défaut : utilisateur courant).
 * @return int Nombre de points (0 si aucun point n'est trouvé).
 */
function get_user_points($user_id = null) {
    $user_id = $user_id ?: get_current_user_id();
    return ($user_id) ? intval(get_user_meta($user_id, 'points_utilisateur', true)) : 0;
}

/**
 * ➕➖ Met à jour le solde de points de l'utilisateur.
 *
 * - Empêche les points négatifs.
 * - Rafraîchit la session utilisateur si connecté.
 *
 * @param int $user_id ID de l'utilisateur.
 * @param int $points_change Nombre de points à ajouter ou retirer.
 */
function update_user_points($user_id, $points_change) {
    if (!$user_id) return;

    $current_points = get_user_points($user_id);
    $new_points = max(0, $current_points + $points_change); // Empêche les points négatifs
    update_user_meta($user_id, 'points_utilisateur', $new_points);

    // 🔄 Rafraîchit la session utilisateur si connecté
    if (is_user_logged_in()) {
        //wc_set_customer_auth_cookie($user_id); // Recharge les données utilisateur
    }
}

/**
 * 🎁 Attribue les points après l’achat d’un pack de points.
 *
 * @param int $order_id ID de la commande.
 */
function attribuer_points_apres_achat($order_id) {
    $order = wc_get_order($order_id);
    if (!$order || $order->get_meta('_points_deja_attribues')) return; // 🔒 Évite les doublons

    $user_id = $order->get_user_id();
    if (!$user_id) return;

    $packs_points = [
        'pack-100-points'  => 100,
        'pack-500-points'  => 500,
        'pack-1000-points' => 1000,
    ];

    $points_ajoutes = 0;

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) continue;

        $slug = $product->get_slug();
        if (isset($packs_points[$slug])) {
            $points_to_add = $packs_points[$slug] * $item->get_quantity();
            update_user_points($user_id, $points_to_add);
            $points_ajoutes += $points_to_add;
            $order->add_order_note("✅ {$points_to_add} points ajoutés.");
        }
    }

    if ($points_ajoutes > 0) {
        mettre_a_jour_points_achetes($points_ajoutes); // 🔄 Mise à jour des points acheté
        $order->update_meta_data('_points_deja_attribues', true); // ✅ Marque la commande comme traitée
        $order->save();
    }
}

/**
 * 🛒 Attribue les points et vide le panier après la commande.
 */
add_action('woocommerce_thankyou', function($order_id) {
    attribuer_points_apres_achat($order_id); // 🎁 Attribution des points

    if (!is_admin() && WC()->cart) {
        WC()->cart->empty_cart(); // 🧹 Vide le panier
    }
});

/**
 * 💎 Affiche les points de l'utilisateur selon le statut de l'énigme.
 *
 * Cas d'affichage :
 * - Si bonne réponse : affiche les points gagnés et le nouveau solde.
 * - Si échec ou pas encore tenté : affiche le solde actuel.
 * - Si autre statut : aucun affichage.
 *
 * @return string HTML des points ou chaîne vide.
 */
function afficher_points_utilisateur_callback() {
    // 🛑 Vérifie si l'utilisateur est connecté
    if (!is_user_logged_in()) return '';

    // 🏷️ Récupération des données utilisateur
    $user_id = get_current_user_id();
    $points = intval(get_user_meta($user_id, 'points_utilisateur', true)) ?: 0;
    $icone_points_url = esc_url(get_stylesheet_directory_uri() . '/assets/images/points-small.png');
    $boutique_url = esc_url(home_url('/boutique'));

    // 🎉 Vérification si des points ont été gagnés (bonne réponse)
    $points_gagnes_html = '';
    if (!empty($_GET['reponse']) && sanitize_text_field($_GET['reponse']) === 'bonne' && isset($_GET['points_gagnes'])) {
        $points_gagnes = intval($_GET['points_gagnes']);

        // ✅ Sécurité : On s'assure que les points gagnés sont un entier valide et positif
        if ($points_gagnes > 0) {
            $points_gagnes_html = "
                <div class='points-gagnes'>
                    +<strong>{$points_gagnes}</strong> points gagnés !
                </div>";
        }
    }

    // 📌 Affichage des points avec icône (texte en style par défaut)
    return "
    <div class='zone-points'>
        {$points_gagnes_html}
        <a href='{$boutique_url}' class='points-link' title='Accéder à la boutique'>
            <span class='points-plus-circle'>+</span>
            <span class='points-value'>{$points}</span>
            <span class='points-euro'>pts</span>
        </a>
    </div>";
}

/**
 * Ajoute le modal des points à la fin du <body> via wp_footer.
 */
function ajouter_modal_points() {
    get_template_part('template-parts/modal-points');
}
add_action('wp_footer', 'ajouter_modal_points');

/**
 * Charger le script du modal des points en ajoutant un paramètre de version dynamique
 */
function charger_script_modal_points() {
    wp_enqueue_script(
        'modal-points',
        get_stylesheet_directory_uri() . '/assets/js/modal-points.js',
        [],
        filemtime(get_stylesheet_directory() . '/assets/js/modal-points.js'), // Utilise la date de modification comme version
        true
    );
}
add_action('wp_enqueue_scripts', 'charger_script_modal_points');


/**
 * 🔒 Vérifie si l'utilisateur a suffisamment de points pour une opération donnée.
 *
 * @param int $user_id
 * @param int $montant Nombre de points nécessaires.
 * @return bool True si le solde est suffisant.
 */
function utilisateur_a_assez_de_points(int $user_id, int $montant): bool {
    if (!$user_id || $montant < 0) return false;

    $points_disponibles = get_user_points($user_id);
    return $points_disponibles >= $montant;
}

/**
 * ➖ Déduit un montant de points à un utilisateur.
 *
 * @param int $user_id
 * @param int $montant Nombre de points à retirer (doit être positif).
 * @return void
 */
function deduire_points_utilisateur(int $user_id, int $montant): void {
    if ($user_id && $montant > 0) {
        update_user_points($user_id, -$montant);
    }
}

/**
 * ➕ Ajoute un montant de points à un utilisateur.
 *
 * @param int $user_id
 * @param int $montant Nombre de points à ajouter (doit être positif).
 * @return void
 */
function ajouter_points_utilisateur(int $user_id, int $montant): void {
    if ($user_id && $montant > 0) {
        update_user_points($user_id, $montant);
    }
}



// ==================================================
// 💎 PROGRESSION DANS LA CHASSE
// ==================================================
/**
 * 🔹 enigme_get_chasse_progression → Calculer la progression d’un utilisateur dans une chasse donnée.
 * 🔹 compter_enigmes_resolues → Compter le nombre d’énigmes résolues par un utilisateur pour une chasse.
 * 🔹 verifier_fin_de_chasse → Vérifier si l’utilisateur a terminé toutes les énigmes d’une chasse.
 */

/**
 * 📊 Calcule la progression d’un utilisateur dans une chasse donnée.
 *
 * @param int $chasse_id ID de la chasse.
 * @param int $user_id ID de l’utilisateur.
 * @return array Nombre d’énigmes résolues et total d’énigmes.
 */
 function enigme_get_chasse_progression(int $chasse_id, int $user_id): array {
    $enigmes = recuperer_enigmes_associees($chasse_id); // ✅ Retourne directement les IDs
    $resolues = count(array_filter($enigmes, fn($id) => get_user_meta($user_id, "statut_enigme_{$id}", true) === 'terminée'));

    return [
        'resolues' => $resolues,
        'total' => count($enigmes),
    ];
}

/**
 * 📊 Compte le nombre d'énigmes résolues par un utilisateur pour une chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @param int $user_id ID de l'utilisateur.
 * @return int Nombre d'énigmes résolues.
 */
function compter_enigmes_resolues($chasse_id, $user_id): int {
    if (!$chasse_id || !$user_id) return 0; // 🔒 Vérification des IDs

    $enigmes = get_field('enigmes_associees', $chasse_id) ?: [];
    if (empty($enigmes)) return 0;

    return count(array_filter($enigmes, function($enigme) use ($user_id) {
        $enigme_id = is_object($enigme) ? $enigme->ID : (int) $enigme;
        return $enigme_id && get_user_meta($user_id, "statut_enigme_{$enigme_id}", true) === 'terminée';
    }));
}

/**
 * 🏁 Vérifie si l'utilisateur a terminé toutes les énigmes d'une chasse.
 *
 * 🔎 Si toutes les énigmes sont résolues :
 * - Attribue le trophée de la chasse (si présent).
 * - Si la chasse est de type "enjeu" :
 *   - Met à jour le gagnant, la date de découverte et le statut à "terminé".
 *
 * @param int $user_id  ID de l'utilisateur.
 * @param int $enigme_id ID de l'énigme résolue.
 */
function verifier_fin_de_chasse($user_id, $enigme_id) {
    error_log("🔍 Vérification de fin de chasse pour l'utilisateur {$user_id} (énigme : {$enigme_id})");

    // 🧭 Récupération de la chasse associée
    $chasse_id = get_field('chasse_associee', $enigme_id, false);
    $chasse_id = is_array($chasse_id) ? reset($chasse_id) : $chasse_id;

    if (!$chasse_id) {
        error_log("❌ Aucune chasse associée trouvée.");
        return;
    }

    // 📄 Récupération des énigmes associées
    $enigmes_associees = get_field('enigmes_associees', $chasse_id);
    if (empty($enigmes_associees) || !is_array($enigmes_associees)) {
        error_log("⚠️ Pas d'énigmes associées à la chasse (ID: {$chasse_id})");
        return;
    }

    $enigmes_ids = array_filter($enigmes_associees, 'is_numeric');
    if (empty($enigmes_ids)) {
        error_log("❌ Aucun ID valide parmi les énigmes.");
        return;
    }

    // ✅ Vérification des énigmes résolues
    $enigmes_resolues = array_filter($enigmes_ids, function($associee_id) use ($user_id) {
        $statut = get_user_meta($user_id, "statut_enigme_{$associee_id}", true);
        error_log("📄 Énigme (ID: {$associee_id}) - Statut: {$statut}");
        return $statut === 'terminée';
    });

    error_log("✅ Résolues : " . count($enigmes_resolues) . " / " . count($enigmes_ids));

    // 🏆 Si toutes les énigmes sont résolues
    if (count($enigmes_resolues) === count($enigmes_ids)) {
        error_log("🏁 Toutes les énigmes sont résolues. Attribution du trophée de chasse.");
        attribuer_trophee_si_associe($user_id, $chasse_id); // 🏅 Attribue le trophée de chasse
    
        $illimitee = get_field('illimitee', $chasse_id); // Récupère le mode de la chasse ("stop" ou "continue")
        $statut_chasse = get_field('statut_chasse', $chasse_id);
    
        // 🏆 Si la chasse est en mode "stop" et non déjà terminée
        if ($illimitee === 'stop' && mb_strtolower($statut_chasse) !== 'terminé') {
            $user_info = get_userdata($user_id);
            $gagnant = $user_info ? $user_info->display_name : 'Utilisateur inconnu';
    
            update_field('gagnant', $gagnant, $chasse_id);
            update_field('date_de_decouverte', current_time('Y-m-d'), $chasse_id);
            update_field('statut_chasse', 'terminé', $chasse_id);
    
            // 🔄 Nettoyage du cache après mise à jour
            wp_cache_delete($chasse_id, 'post_meta');
            clean_post_cache($chasse_id);
    
            error_log("🏆 Chasse (ID: {$chasse_id}) terminée. Gagnant : {$gagnant}");
            incrementer_total_chasses_terminees_utilisateur($user_id);
        }
    
        // ✅ Active l'effet WOW pour l'utilisateur
        update_user_meta($user_id, "effet_wow_chasse_{$chasse_id}", 1);
    }
}
add_action('enigme_resolue', function($user_id, $enigme_id) {
    verifier_fin_de_chasse($user_id, $enigme_id); // 🎯 Vérifie et termine la chasse si besoin
});



// ==================================================
// 🏆 TROPHÉES
// ==================================================
/**
 * 🔹 attribuer_trophee_utilisateur → Attribuer le trophée associé à un post (énigme ou chasse).
 * 🔹 afficher_trophees_utilisateur_callback → Shortcode pour afficher la liste des trophées d’un utilisateur connecté.
 * 🔹 attribuer_trophee_si_associe → Attribuer le trophée associé à une énigme ou une chasse si l’utilisateur ne l’a pas déjà.
 * 🔹 attribuer_badge_utilisateur → Attribuer un badge à un utilisateur (si non déjà attribué).
 * 🔹 save_post (function) → Gérer l’attribution des métadonnées des trophées lors de leur enregistrement.
 * 🔹 gerer_trophee_personnalise → Gérer la création et la mise à jour des trophées personnalisés pour une chasse ou une énigme.
 * 🔹 gerer_trophee_pour_post → Gérer la suppression, la mise à jour ou la création d’un trophée pour une chasse ou une énigme.
 * 🔹 acf/fields/relationship/query (function) → Filtrer la liste des trophées affichés dans les champs relation ACF pour les chasses et les énigmes.
 * 🔹 afficher_trophee_chasse → Générer l’affichage du trophée d’une chasse si elle est terminée.
 */

/**
 * 🏅 Attribue le trophée associé à un post (énigme ou chasse) à un utilisateur.
 *
 * Vérifie que le trophée est valide et que l'utilisateur ne le possède pas déjà.
 *
 * @param int $user_id ID de l'utilisateur.
 * @param int $post_id ID du post (énigme ou chasse) contenant le champ ACF 'trophee_associe'.
 * @return void
 */
function attribuer_trophee_utilisateur($user_id, $post_id) {
    $trophee_id = get_field('trophee_associe', $post_id); // 🎯 Récupère l'ID du trophée

    // 📦 Si le champ renvoie un tableau (Relation), prend le premier élément
    if (is_array($trophee_id)) $trophee_id = reset($trophee_id);

    // 🚫 Vérifie la validité du trophée
    if (!$trophee_id || !is_numeric($trophee_id)) return;

    // 🗂️ Récupère les trophées déjà obtenus
    $trophees_utilisateur = get_user_meta($user_id, 'trophees_utilisateur', true);
    $trophees_utilisateur = is_array($trophees_utilisateur) ? $trophees_utilisateur : [];

    // 🚫 Si l'utilisateur possède déjà ce trophée, arrête l'exécution
    if (isset($trophees_utilisateur[$trophee_id])) return;

    // ✅ Ajoute le trophée avec date et origine
    $trophees_utilisateur[$trophee_id] = [
        'attribue_le' => current_time('mysql'),         // 🕒 Date d'attribution
        'origine'     => get_the_title($post_id),       // 📝 Nom du post source
    ];

    update_user_meta($user_id, 'trophees_utilisateur', $trophees_utilisateur); // 💾 Sauvegarde
}
add_action('chasse_terminee', function($user_id, $chasse_id) {
    attribuer_trophee_utilisateur($user_id, $chasse_id); // 🏅 Remise du trophée associé à la chasse
});
add_action('enigme_terminee', function($user_id, $enigme_id) {
    attribuer_trophee_utilisateur($user_id, $enigme_id); // 🏆 Trophée lié à l’énigme
});


/**
 * 🏆 Shortcode pour afficher la liste des trophées d'un utilisateur connecté.
 *
 * @return string HTML des trophées ou message d'invitation à se connecter.
 */
/**
 * Affiche les trophées d'un utilisateur.
 *
 * @param int|null $nombre Nombre de trophées à afficher (par défaut : tous)
 * @return string HTML des trophées à afficher.
 */
function afficher_trophees_utilisateur_callback($nombre = null) {
    if (!is_user_logged_in()) {
        return '<p>🔒 Connectez-vous pour voir vos trophées.</p>';
    }

    $user_id = get_current_user_id();
    $trophees = get_user_meta($user_id, 'trophees_utilisateur', true);

    // 🚫 Aucun trophée obtenu
    if (empty($trophees)) {
        return '';
    }

    // Trier les trophées par date d’attribution (du plus récent au plus ancien)
    uasort($trophees, function($a, $b) {
        return strtotime($b['attribue_le']) - strtotime($a['attribue_le']);
    });

    // Si un nombre est défini, ne garder que les X derniers trophées
    if ($nombre !== null && is_numeric($nombre)) {
        $trophees = array_slice($trophees, 0, (int) $nombre, true);
    }

    // Génération du HTML des trophées
    $output = '<div class="liste-trophees" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 10px;">';

    foreach ($trophees as $trophee_id => $trophee) {
        $titre = get_the_title($trophee_id);
        $image = get_the_post_thumbnail_url($trophee_id, 'thumbnail');
        $origine_trophee = get_field('origine_trophee', $trophee_id) ?? 'Non spécifié';
        $date = date_i18n('d/m/Y', strtotime($trophee['attribue_le']));

        $output .= "
            <div class='trophee' style='text-align:center; width:100px; padding:10px; border:1px solid #ddd; border-radius:8px; background-color:#f9f9f9;'>
                <img src='{$image}' alt='Trophée' style='max-width:80px; margin-bottom:5px;'>
                <h4 style='font-size:12px; margin:5px 0;'>{$titre}</h4>
                <small style='color:#888;'>🏅 {$date}</small>
            </div>";
    }

    $output .= '</div>';
    return $output;
}

/**
 * 🏆 Attribue le trophée associé à une énigme ou une chasse si l’utilisateur ne l’a pas déjà.
 *
 * @param int $user_id  ID de l'utilisateur.
 * @param int $post_id  ID de l'énigme ou de la chasse contenant le champ ACF 'trophee_associe'.
 *
 * 🔎 Vérifications effectuées :
 * - Vérifie si le post a un trophée associé (via ACF).
 * - Empêche les doublons en vérifiant les trophées déjà obtenus.
 * - Met à jour la méta 'trophees_utilisateur' avec le nouvel ID de trophée.
 */
function attribuer_trophee_si_associe($user_id, $post_id) {
    if (!is_numeric($user_id) || !is_numeric($post_id)) {
        error_log("⚠️ Paramètres invalides : user_id={$user_id}, post_id={$post_id}");
        return;
    }

    // 🏅 Récupère le trophée associé au post (champ ACF 'trophee_associe')
    $trophee_id = get_field('trophee_associe', $post_id);
    if (!$trophee_id) {
        error_log("ℹ️ Aucun trophée associé au post (ID: {$post_id})");
        return; // 🚫 Pas de trophée
    }

    // 📦 Gestion du cas où le champ est un tableau (Relation ou Post Object ACF)
    $trophee_id = is_array($trophee_id) ? reset($trophee_id) : $trophee_id;
    if (!is_numeric($trophee_id)) {
        error_log("⚠️ ID de trophée non valide (post ID: {$post_id}, valeur : {$trophee_id})");
        return; // 🚫 ID incorrect
    }

    // 🗂️ Récupère les trophées déjà obtenus par l’utilisateur
    $trophees_utilisateur = get_user_meta($user_id, 'trophees_utilisateur', true);
    $trophees_utilisateur = is_array($trophees_utilisateur) ? $trophees_utilisateur : [];

    // 🚫 Vérifie si l’utilisateur possède déjà ce trophée
    if (in_array($trophee_id, $trophees_utilisateur, true)) {
        error_log("🚫 Utilisateur (ID: {$user_id}) possède déjà le trophée (ID: {$trophee_id})");
        return;
    }

    // ➕ Ajoute le nouveau trophée et sauvegarde
    $trophees_utilisateur[] = $trophee_id;
    update_user_meta($user_id, 'trophees_utilisateur', $trophees_utilisateur);

    error_log("🏆 Trophée (ID: {$trophee_id}) attribué à l'utilisateur (ID: {$user_id}) avec succès.");
}

/**
 * 🏅 Attribue un badge à un utilisateur (si non déjà attribué).
 *
 * @param int    $user_id      ID de l'utilisateur.
 * @param string $badge_slug   Slug du badge (par défaut : 'enfantun').
 *
 * @return bool  Retourne true si le badge a été attribué, false sinon.
 */
function attribuer_badge_utilisateur($user_id, $badge_slug = 'enfantun') {
    if (!$user_id || empty($badge_slug) || !get_userdata($user_id)) {
        error_log("❌ Attribution de badge échouée : utilisateur invalide ou badge manquant.");
        return false; 
    }

    $badges_utilisateur = get_user_meta($user_id, 'badges_utilisateur', true);
    $badges_utilisateur = is_array($badges_utilisateur) ? $badges_utilisateur : [];

    if (!in_array($badge_slug, $badges_utilisateur, true)) {
        $badges_utilisateur[] = $badge_slug;
        update_user_meta($user_id, 'badges_utilisateur', $badges_utilisateur);

        clean_user_cache($user_id); // 🔄 Nettoie le cache utilisateur
        error_log("🏅 Badge '{$badge_slug}' attribué à l'utilisateur (ID: {$user_id}).");
        return true;
    }

    error_log("🚫 L'utilisateur (ID: {$user_id}) possède déjà le badge '{$badge_slug}'.");
    return false;
}
add_action('enigme_resolue', function($user_id) {
    attribuer_badge_utilisateur($user_id, 'enfantun'); // 🥇 Remise du badge
});

/**
 * Gère l'attribution des métadonnées des trophées lors de leur enregistrement.
 *
 * - Si un trophée est créé manuellement par un administrateur :
 *   - Il est automatiquement défini comme "systeme" (trophée réutilisable).
 *
 * - Si un trophée est marqué comme "unique" (trophée personnalisé) :
 *   - Il doit être associé à une chasse via le champ "chasse_associee".
 *   - Un message d'erreur est loggé si l'association est absente.
 *
 * @param int    $post_id ID du trophée en cours d'enregistrement.
 * @param object $post    Objet WP_Post du trophée.
 * @param bool   $update  Indique si le post est une mise à jour (true) ou une création (false).
 *
 * @return void
 */
add_action('save_post', function ($post_id, $post, $update) {
    // Vérifier que le post est bien un trophée
    if ($post->post_type !== 'trophee') {
        return;
    }

    // Vérifier si la meta "origine_trophee" existe déjà
    $origine_trophee = get_post_meta($post_id, 'origine_trophee', true);

    // 🔹 Si le trophée a été créé manuellement, on le met en "systeme"
    if (empty($origine_trophee)) {
        update_post_meta($post_id, 'origine_trophee', 'systeme');
    }

    // 🔍 Vérifier si c'est un trophée personnalisé "unique"
    if ($origine_trophee === 'unique') {
        $chasse_associee = get_field('chasse_associee', $post_id);

        if (empty($chasse_associee)) {
            error_log("⚠️ ERREUR : Aucun ID de chasse associé au trophée personnalisé ID {$post_id}.");
        }
    }
}, 10, 3);

/**
 * Gère la création et la mise à jour des trophées personnalisés pour une chasse ou une énigme.
 *
 * - Si un trophée "unique" existe déjà pour cette chasse/énigme, il est mis à jour.
 * - Sinon, un nouveau trophée "unique" est créé et associé à la chasse/énigme.
 *
 * @param int    $post_id      ID de la chasse ou de l'énigme associée au trophée.
 * @param string $nom_trophee  Nom du trophée personnalisé.
 * @param int    $icone_trophee ID de l'image associée (doit être un média WordPress).
 * @param string $type         Type du post associé au trophée ('chasse' ou 'enigme').
 *
 * @return void
 */
function gerer_trophee_personnalise($post_id, $nom_trophee, $icone_trophee, $type = 'chasse') {
    // Définir la bonne clé ACF en fonction du type
    $meta_key_chasse = ($type === 'chasse') ? 'chasse_associee' : 'enigme_associee';

    // 🔍 Vérifier si un trophée personnalisé existe déjà pour cette chasse ou énigme
    $trophee_existante = new WP_Query([
        'post_type'      => 'trophee',
        'meta_query'     => [
            [
                'key'     => $meta_key_chasse,
                'value'   => $post_id,
                'compare' => '='
            ],
            [
                'key'     => 'origine_trophee',
                'value'   => 'unique',
                'compare' => '='
            ]
        ],
        'posts_per_page' => 1
    ]);

    if ($trophee_existante->have_posts()) {
        $trophee_id = $trophee_existante->posts[0]->ID;

        // 📌 Vérifier si des modifications ont été faites
        $nom_actuel = get_the_title($trophee_id);
        $icone_actuelle = get_post_thumbnail_id($trophee_id);

        if ($nom_actuel !== $nom_trophee || $icone_actuelle !== $icone_trophee) {
            wp_update_post([
                'ID'         => $trophee_id,
                'post_title' => sanitize_text_field($nom_trophee),
            ]);

            set_post_thumbnail($trophee_id, $icone_trophee);
            error_log("♻️ Trophée unique ID {$trophee_id} mis à jour.");
        }

        return;
    }

    // 🏆 Si aucun trophée existant, en créer un nouveau
    $trophee_id = wp_insert_post([
        'post_title'   => sanitize_text_field($nom_trophee),
        'post_status'  => 'pending',
        'post_type'    => 'trophee',
        'meta_input'   => [
            $meta_key_chasse => $post_id, // Association avec la chasse ou l'énigme
            'origine_trophee' => 'unique'
        ]
    ]);

    if (is_wp_error($trophee_id)) {
        error_log("❌ ERREUR : Impossible de créer le trophée pour le post ID {$post_id}.");
        return;
    }

    set_post_thumbnail($trophee_id, $icone_trophee);
    error_log("✅ Trophée unique (ID: {$trophee_id}) créé pour {$type} ID {$post_id}.");
}

/**
 * Gère la suppression, la mise à jour ou la création d’un trophée pour une chasse ou une énigme.
 *
 * - Supprime un trophée "unique" si l'organisateur passe à "le_votre" ou "non".
 * - Vérifie que les champs sont remplis avant de créer un trophée "unique".
 * - Met à jour ou crée un trophée "unique" selon les besoins.
 *
 * @param int    $post_id   ID du post en cours d'enregistrement (chasse ou énigme).
 * @param string $type      Type du post ('chasse' ou 'enigme').
 * @param string $association_trophee Valeur sélectionnée ('non', 'le_votre', 'le_mien').
 * @param string $nom_trophee Nom du trophée (si "le_mien").
 * @param int    $icone_trophee ID de l'image du trophée (si "le_mien").
 *
 * @return void
 */
function gerer_trophee_pour_post($post_id, $type, $association_trophee, $nom_trophee = '', $icone_trophee = '') {
    // Définition des champs en fonction du type (chasse ou énigme)
    $champ_trophee_associe = ($type === 'chasse') ? 'trophee_associe' : 'trophee_associe_enigme';
    $meta_key_associee = ($type === 'chasse') ? 'chasse_associee' : 'enigme_associee';

    if ($association_trophee === 'non' || $association_trophee === 'le_votre') {
        // 🚨 Aucun trophée ou trophée "prêt à l’emploi" → On supprime toutes les valeurs liées
        update_field($champ_trophee_associe, '', $post_id);
        update_field('icone_du_trophee', '', $post_id);
        update_field('nom_du_trophee', '', $post_id);

        // 🔄 Supprimer l'ancien trophée "unique" s'il existe
        $trophee_existante = new WP_Query([
            'post_type'      => 'trophee',
            'meta_query'     => [
                [
                    'key'     => $meta_key_associee,
                    'value'   => $post_id,
                    'compare' => '='
                ],
                [
                    'key'     => 'origine_trophee',
                    'value'   => 'unique',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ]);

        if ($trophee_existante->have_posts()) {
            $trophee_id = $trophee_existante->posts[0]->ID;
            wp_delete_post($trophee_id, true); // Suppression définitive
            error_log("🗑️ Trophée unique ID {$trophee_id} supprimé car le {$type} ID {$post_id} utilise maintenant un trophée système.");
        }
    } elseif ($association_trophee === 'le_mien') {
        // ✨ Création d’un trophée personnalisé → On vide le champ relation
        update_field($champ_trophee_associe, '', $post_id);

        // 🚨 Vérification que les champs sont bien remplis
        if (empty($nom_trophee) || empty($icone_trophee)) {
            error_log("⚠️ ERREUR : Nom ou icône du trophée manquant pour le {$type} ID {$post_id}.");
            return;
        }

        // ✅ Appel de la fonction pour créer ou mettre à jour le CPT "trophée"
        gerer_trophee_personnalise($post_id, $nom_trophee, $icone_trophee, $type);
    }
}

/**
 * Filtre la liste des trophées affichés dans les champs relation ACF pour les chasses et les énigmes.
 *
 * - Seuls les trophées "prêts à l’emploi" (`origine_trophee = systeme`) sont affichés.
 * - Les trophées "personnalisés" (`origine_trophee = unique`) sont exclus s'ils sont déjà liés à une chasse ou une énigme.
 *
 * @param array $args   Arguments de la requête WP_Query pour ACF.
 * @param array $field  Données du champ ACF.
 * @param int   $post_id ID du post en cours d'édition.
 *
 * @return array Arguments WP_Query modifiés.
 */
add_filter('acf/fields/relationship/query', function ($args, $field, $post_id) {
    // Vérifier si on est sur le champ "trophee_associe" (chasses) ou "trophee_associe_enigme" (énigmes)
    if ($field['name'] !== 'trophee_associe' && $field['name'] !== 'trophee_associe_enigme') {
        return $args; // On ne modifie pas la requête pour les autres champs
    }

    // Définition de la clé meta associée en fonction du champ
    $meta_key_associee = ($field['name'] === 'trophee_associe') ? 'chasse_associee' : 'enigme_associee';

    // 📌 Appliquer le filtre pour ne montrer que les trophées "prêts à l’emploi"
    $args['meta_query'] = [
        'relation' => 'OR',
        // Afficher les trophées système (disponibles pour tout le monde)
        [
            'key'     => 'origine_trophee',
            'value'   => 'systeme',
            'compare' => '='
        ],
        // Afficher les trophées "unique" SEULEMENT s'ils sont associés à la chasse/enigme actuelle
        [
            'relation' => 'AND',
            [
                'key'     => 'origine_trophee',
                'value'   => 'unique',
                'compare' => '='
            ],
            [
                'key'     => $meta_key_associee,
                'value'   => $post_id,
                'compare' => '='
            ]
        ]
    ];

    return $args;
}, 10, 3);


/**
 * 📌 Génère l'affichage du trophée d'une chasse si elle est terminée.
 *
 * @param int    $chasse_id      ID de la chasse concernée.
 * @param string $statut_chasse  Statut actuel de la chasse.
 *
 * @return string|false  HTML du trophée ou false si aucun trophée à afficher.
 */
function afficher_trophee_chasse($chasse_id, $statut_chasse) {

    // 🔍 Récupérer les données du groupe "trophee"
    $trophee = get_field('trophee', $chasse_id);
    if (!$trophee) {
        return false;
    }

    // 🔍 Récupérer le type de trophée sélectionné
    $type_trophee = $trophee['association_dun_trophee_a_cette_chasse'] ?? 'non';

    // ❌ Si aucun trophée n'est associé, ne rien afficher
    if ($type_trophee === 'non' || empty($type_trophee)) {
        return false;
    }

    // 🏆 Récupération du trophée selon le type
    $icone_trophee = null;
    if ($type_trophee === 'le_votre') {
        // Trophée système (lié via ACF)
        $trophee_id = is_array($trophee['trophee_associe']) ? reset($trophee['trophee_associe']) : $trophee['trophee_associe'];

        if (!$trophee_id) {
            return false;
        }

        // Récupérer l'image mise en avant du trophée en taille "icone" optimisée par Imagify
        $icone_trophee = get_the_post_thumbnail_url($trophee_id, 'icone');

    } elseif ($type_trophee === 'le_mien') {
        // 🔍 Rechercher le trophée unique associé à cette chasse
        $trophee_id = new WP_Query([
            'post_type'      => 'trophee',
            'post_status'    => 'pending',
            'meta_query'     => [
                [
                    'key'     => 'chasse_associee',
                    'value'   => $chasse_id,
                    'compare' => '='
                ],
                [
                    'key'     => 'origine_trophee',
                    'value'   => 'unique',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ]);
    
        if ($trophee_id->have_posts()) {
            $trophee_id = $trophee_id->posts[0]->ID;
        } else {
            $trophee_id = null;
    }

    // Vérifier si une image est bien associée au trophée
    $icone_trophee = get_the_post_thumbnail_url($trophee_id, 'icone');
    }

    // ❌ Vérifier si une icône est trouvée
    if (empty($icone_trophee)) {
        return false;
    }

    // 🎨 Générer le HTML du trophée (icône uniquement)
    return '<span class="trophee-chasse">
                <img src="' . esc_url($icone_trophee) . '" alt="Trophée" class="trophee-chasse__icone">
            </span>';
}


