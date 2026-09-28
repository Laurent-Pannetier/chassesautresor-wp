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
function get_user_points($user_id = null): int {
    $user_id = $user_id ?: get_current_user_id();
    if (!$user_id) {
        return 0;
    }

    global $wpdb;
    $repo = new PointsRepository($wpdb);

    return $repo->getBalance((int) $user_id);
}

/**
 * ➕➖ Met à jour le solde de points de l'utilisateur.
 *
 * - Empêche les points négatifs.
 * - Rafraîchit la session utilisateur si connecté.
 *
 * @param int    $user_id       ID de l'utilisateur.
 * @param int    $points_change Nombre de points à ajouter ou retirer.
 * @param string $reason        Motif lisible par l'utilisateur.
 * @param string $origin_type   Catégorie de l'opération.
 * @param int|null $origin_id   Identifiant de l'élément lié.
 */
function update_user_points(
    int $user_id,
    int $points_change,
    string $reason = '',
    string $origin_type = 'admin',
    ?int $origin_id = null
): void {
    if (!$user_id) {
        return;
    }

    global $wpdb;
    $repo = new PointsRepository($wpdb);
    $repo->addPoints($user_id, $points_change, $reason, $origin_type, $origin_id);

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
            $reason = sprintf('Achat de %d points (commande #%d)', $points_to_add, $order_id);
            update_user_points($user_id, $points_to_add, $reason, 'achat', $order_id);
            $points_ajoutes += $points_to_add;
            $order->add_order_note("✅ {$points_to_add} points ajoutés.");
        }
    }

    if ($points_ajoutes > 0) {
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
    $points = get_user_points($user_id);
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
    get_template_part('template-parts/modals/modal-points');
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
 * @param int      $user_id
 * @param int      $montant     Nombre de points à retirer (doit être positif).
 * @param string   $reason      Motif de la déduction.
 * @param string   $origin_type Catégorie de l'opération.
 * @param int|null $origin_id   Identifiant lié.
 * @return void
 */
function deduire_points_utilisateur(
    int $user_id,
    int $montant,
    string $reason = '',
    string $origin_type = 'admin',
    ?int $origin_id = null
): void {
    if ($user_id && $montant > 0) {
        update_user_points($user_id, -$montant, $reason, $origin_type, $origin_id);
    }
}

/**
 * ➕ Ajoute un montant de points à un utilisateur.
 *
 * @param int      $user_id
 * @param int      $montant     Nombre de points à ajouter (doit être positif).
 * @param string   $reason      Motif de l'ajout.
 * @param string   $origin_type Catégorie de l'opération.
 * @param int|null $origin_id   Identifiant lié.
 * @return void
 */
function ajouter_points_utilisateur(
    int $user_id,
    int $montant,
    string $reason = '',
    string $origin_type = 'admin',
    ?int $origin_id = null
): void {
    if ($user_id && $montant > 0) {
        update_user_points($user_id, $montant, $reason, $origin_type, $origin_id);
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
function enigme_get_chasse_progression(int $chasse_id, int $user_id): array
{
    $enigmes = recuperer_enigmes_associees($chasse_id); // ✅ IDs uniquement
    if (empty($enigmes)) {
        return ['resolues' => 0, 'total' => 0];
    }

    $validables      = [];
    $non_validables  = [];
    foreach ($enigmes as $id) {
        if (get_field('enigme_mode_validation', $id) === 'aucune') {
            $non_validables[] = $id;
        } else {
            $validables[] = $id;
        }
    }

    global $wpdb;
    $resolues = 0;

    if ($validables) {
        $table        = $wpdb->prefix . 'enigme_statuts_utilisateur';
        $placeholders = implode(',', array_fill(0, count($validables), '%d'));
        $sql = "SELECT COUNT(DISTINCT enigme_id) FROM {$table} WHERE user_id = %d AND statut IN ('resolue','terminee','terminée') AND enigme_id IN ($placeholders)";
        $resolues = (int) $wpdb->get_var($wpdb->prepare($sql, array_merge([$user_id], $validables)));
    }

    if ($non_validables) {
        $table_eng    = $wpdb->prefix . 'engagements';
        $placeholders = implode(',', array_fill(0, count($non_validables), '%d'));
        $sql = "SELECT COUNT(DISTINCT enigme_id) FROM {$table_eng} WHERE user_id = %d AND enigme_id IN ($placeholders)";
        $resolues += (int) $wpdb->get_var($wpdb->prepare($sql, array_merge([$user_id], $non_validables)));
    }

    return [
        'resolues' => $resolues,
        'total'    => count($validables) + count($non_validables),
    ];
}

/**
 * 📊 Compte le nombre d'énigmes résolues par un utilisateur pour une chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @param int $user_id ID de l'utilisateur.
 * @return int Nombre d'énigmes résolues.
 */
function compter_enigmes_resolues($chasse_id, $user_id): int
{
    if (!$chasse_id || !$user_id) {
        return 0; // 🔒 Vérification des IDs
    }

    $enigmes = recuperer_enigmes_associees($chasse_id);
    if (empty($enigmes)) {
        return 0;
    }

    $validables     = [];
    $non_validables = [];
    foreach ($enigmes as $eid) {
        if (get_field('enigme_mode_validation', $eid) === 'aucune') {
            $non_validables[] = $eid;
        } else {
            $validables[] = $eid;
        }
    }

    global $wpdb;
    $resolues = 0;

    if ($validables) {
        $table        = $wpdb->prefix . 'enigme_statuts_utilisateur';
        $placeholders = implode(',', array_fill(0, count($validables), '%d'));
        $sql = "SELECT COUNT(DISTINCT enigme_id) FROM {$table} WHERE user_id = %d AND statut IN ('resolue','terminee','terminée') AND enigme_id IN ($placeholders)";
        $resolues = (int) $wpdb->get_var($wpdb->prepare($sql, array_merge([$user_id], $validables)));
    }

    if ($non_validables) {
        $table_eng    = $wpdb->prefix . 'engagements';
        $placeholders = implode(',', array_fill(0, count($non_validables), '%d'));
        $sql = "SELECT COUNT(DISTINCT enigme_id) FROM {$table_eng} WHERE user_id = %d AND enigme_id IN ($placeholders)";
        $resolues += (int) $wpdb->get_var($wpdb->prepare($sql, array_merge([$user_id], $non_validables)));
    }

    return $resolues;
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
function verifier_fin_de_chasse($user_id, $enigme_id)
{
    cat_debug("🔍 Vérification de fin de chasse pour l'utilisateur {$user_id} (énigme : {$enigme_id})");

    // 🧭 Récupération de la chasse associée
    $chasse_id = recuperer_id_chasse_associee($enigme_id);

    if (!$chasse_id) {
        cat_debug("❌ Aucune chasse associée trouvée.");
        return;
    }

    $mode_fin = get_field('chasse_mode_fin', $chasse_id) ?: 'automatique';
    if ($mode_fin !== 'automatique') {
        return; // 🔁 La complétion se fait manuellement
    }

    // 📄 Récupération des énigmes associées (IDs uniquement)
    $enigmes_associees = recuperer_enigmes_associees($chasse_id);
    if (empty($enigmes_associees)) {
        cat_debug("⚠️ Pas d'énigmes associées à la chasse (ID: {$chasse_id})");
        return;
    }

    $validables      = [];
    $non_validables  = [];
    foreach ($enigmes_associees as $eid) {
        if (get_field('enigme_mode_validation', $eid) === 'aucune') {
            $non_validables[] = $eid;
        } else {
            $validables[] = $eid;
        }
    }

    global $wpdb;
    $nb_resolues = 0;

    if ($validables) {
        $table        = $wpdb->prefix . 'enigme_statuts_utilisateur';
        $placeholders = implode(',', array_fill(0, count($validables), '%d'));
        $sql = "SELECT COUNT(DISTINCT enigme_id) FROM {$table} WHERE user_id = %d AND statut IN ('resolue','terminee','terminée') AND enigme_id IN ($placeholders)";
        $nb_resolues  = (int) $wpdb->get_var($wpdb->prepare($sql, array_merge([$user_id], $validables)));
    }

    $engagements_ok = true;
    if ($non_validables) {
        $table_eng    = $wpdb->prefix . 'engagements';
        $placeholders = implode(',', array_fill(0, count($non_validables), '%d'));
        $sql = "SELECT COUNT(DISTINCT enigme_id) FROM {$table_eng} WHERE user_id = %d AND enigme_id IN ($placeholders)";
        $nb_engagees  = (int) $wpdb->get_var($wpdb->prepare($sql, array_merge([$user_id], $non_validables)));
        $engagements_ok = ($nb_engagees === count($non_validables));
    }

    if ($nb_resolues === count($validables) && $engagements_ok) {
        gerer_chasse_terminee($chasse_id);

        if (function_exists('ca_demo_is_demo_hunt') && ca_demo_is_demo_hunt((int) $chasse_id)) {
            do_action('ca_demo_schedule_reset', (int) $chasse_id, (int) $user_id);
        }
    }
}
add_action('enigme_resolue', function($user_id, $enigme_id) {
    verifier_fin_de_chasse($user_id, $enigme_id); // 🎯 Vérifie et termine la chasse si besoin
}, 10, 2);

/**
 * Retrieve points history for a user with pagination.
 */
function get_user_points_history(int $user_id = null, int $page = 1, int $per_page = 20): array
{
    $user_id = $user_id ?: get_current_user_id();
    if (!$user_id) {
        return [];
    }

    global $wpdb;
    $repo   = new PointsRepository($wpdb);
    $offset = ($page - 1) * $per_page;

    return $repo->getHistory((int) $user_id, $per_page, $offset);
}

/**
 * Count total history entries for a user.
 */
function count_user_points_history(int $user_id = null): int
{
    $user_id = $user_id ?: get_current_user_id();
    if (!$user_id) {
        return 0;
    }

    global $wpdb;
    $repo = new PointsRepository($wpdb);

    return $repo->countHistory((int) $user_id);
}

/**
 * Format a points operation reason by replacing identifiers with linked titles.
 *
 * @param array $op Single operation data.
 * @return string Formatted reason.
 */
function format_points_history_reason(array $op): string
{
    $reason      = $op['reason'] ?? '';
    $origin_id   = isset($op['origin_id']) ? (int) $op['origin_id'] : 0;
    $origin_type = $op['origin_type'] ?? '';

    if ($origin_id > 0 && in_array($origin_type, ['chasse', 'tentative', 'indice', 'enigme'], true)) {
        $title = get_the_title($origin_id);
        $link  = get_permalink($origin_id);
        if ($title && $link) {
            $replacement = sprintf('<a href="%s">%s</a>', esc_url($link), esc_html($title));
            $reason      = str_replace('#' . $origin_id, $replacement, $reason);
        }
    }

    return $reason;
}

/**
 * Render points history table for a user.
 *
 * @param int $user_id User identifier.
 * @return string HTML table or empty string.
 */
function render_points_history_table(int $user_id): string
{
    $per_page   = 20;
    $operations = get_user_points_history($user_id, 1, $per_page);
    $total      = count_user_points_history($user_id);
    if ($total === 0) {
        return '';
    }

    enqueue_points_history_script();
    $total_pages = (int) ceil($total / $per_page);

    ob_start();
    ?>
    <div class="stats-table-wrapper" data-per-page="<?php echo esc_attr($per_page); ?>">
        <h3><?php esc_html_e('Historique de vos points', 'chassesautresor-com'); ?></h3>
        <table class="stats-table">
            <thead>
            <tr>
                <th scope="col"><?php esc_html_e('ID', 'chassesautresor-com'); ?></th>
                <th scope="col"><?php esc_html_e('Date', 'chassesautresor-com'); ?></th>
                <th scope="col"><?php esc_html_e('Origine', 'chassesautresor-com'); ?></th>
                <th scope="col"><?php esc_html_e('Motif', 'chassesautresor-com'); ?></th>
                <th scope="col"><?php esc_html_e('Variation', 'chassesautresor-com'); ?></th>
                <th scope="col"><?php esc_html_e('Solde', 'chassesautresor-com'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($operations as $op) :
                $variation       = (int) $op['points'];
                $variation_label = $variation > 0 ? '+' . $variation : (string) $variation;
                $date            = !empty($op['request_date']) ? mysql2date('d/m/Y', $op['request_date']) : '';
                $reason          = format_points_history_reason($op);
                ?>
                <tr>
                    <td><?php echo esc_html($op['id']); ?></td>
                    <td><?php echo esc_html($date); ?></td>
                    <td><span class="etiquette"><?php echo esc_html($op['origin_type']); ?></span></td>
                    <td><?php echo wp_kses_post($reason); ?></td>
                    <td><span class="etiquette etiquette-grande"><?php echo esc_html($variation_label); ?></span></td>
                    <td><span class="etiquette etiquette-grande"><?php echo esc_html($op['balance']); ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php echo cta_render_pager(1, $total_pages, 'points-history-pager'); ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Enqueue script handling AJAX pagination for points history.
 */
function enqueue_points_history_script(): void
{
    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();

    wp_enqueue_script(
        'pager',
        $uri . '/assets/js/core/pager.js',
        [],
        filemtime($dir . '/assets/js/core/pager.js'),
        true
    );

    wp_enqueue_script(
        'points-history',
        $uri . '/assets/js/points-history.js',
        ['pager'],
        filemtime($dir . '/assets/js/points-history.js'),
        true
    );

    wp_localize_script(
        'points-history',
        'PointsHistoryAjax',
        [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('points-history-nonce'),
        ]
    );
}

/**
 * AJAX handler for loading paginated points history.
 */
function ajax_load_points_history(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error();
    }

    check_ajax_referer('points-history-nonce', 'nonce');

    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $page = max(1, $page);
    $per_page = 20;
    $user_id = get_current_user_id();
    $operations = get_user_points_history($user_id, $page, $per_page);

    ob_start();
    foreach ($operations as $op) {
        $variation       = (int) $op['points'];
        $variation_label = $variation > 0 ? '+' . $variation : (string) $variation;
        $date            = !empty($op['request_date']) ? mysql2date('d/m/Y', $op['request_date']) : '';
        $reason          = format_points_history_reason($op);
        ?>
        <tr>
            <td><?php echo esc_html($op['id']); ?></td>
            <td><?php echo esc_html($date); ?></td>
            <td><span class="etiquette"><?php echo esc_html($op['origin_type']); ?></span></td>
            <td><?php echo wp_kses_post($reason); ?></td>
            <td><span class="etiquette etiquette-grande"><?php echo esc_html($variation_label); ?></span></td>
            <td><span class="etiquette etiquette-grande"><?php echo esc_html($op['balance']); ?></span></td>
        </tr>
        <?php
    }
    $rows = ob_get_clean();

    wp_send_json_success(['rows' => $rows]);
}
add_action('wp_ajax_load_points_history', 'ajax_load_points_history');



