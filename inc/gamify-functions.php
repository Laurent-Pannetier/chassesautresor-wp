<?php
defined( 'ABSPATH' ) || exit;

/**
 * Create the service responsible for points operations.
 */
function cat_get_points_service(): ChassesAuTresor\Core\Points\PointsService
{
    global $wpdb;
    return ChassesAuTresor\Core\Support\CoreServiceFactory::points($wpdb);
}

/**
 * Create the service responsible for purchased point packs.
 */
function cat_get_purchase_points_service(): ChassesAuTresor\Core\Points\PurchasePointsService
{
    global $wpdb;
    return ChassesAuTresor\Core\Support\CoreServiceFactory::purchasePoints($wpdb);
}

/**
 * Create the service responsible for point conversion requests.
 */
function cat_get_conversion_service(): ChassesAuTresor\Core\Points\ConversionService
{
    global $wpdb;
    return ChassesAuTresor\Core\Support\CoreServiceFactory::conversion($wpdb);
}

if (!function_exists('cat_get_hunt_progress_service')) {
    function cat_get_hunt_progress_service(): ChassesAuTresor\Core\Progress\HuntProgressService
    {
        global $wpdb;
        return ChassesAuTresor\Core\Support\CoreServiceFactory::huntProgress($wpdb);
    }
}

function cat_classify_hunt_riddles(array $riddleIds): array
{
    return (new ChassesAuTresor\Core\Progress\HuntRiddleClassifier())->classify($riddleIds);
}

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

    return cat_get_points_service()->getBalance((int) $user_id);
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

    cat_get_points_service()->changeBalance($user_id, $points_change, $reason, $origin_type, $origin_id);

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

    cat_get_purchase_points_service()->awardOrder($order);
}

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
    return cat_get_points_service()->hasEnough($user_id, $montant);
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
    cat_get_points_service()->deduct($user_id, $montant, $reason, $origin_type, $origin_id);
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
    cat_get_points_service()->add($user_id, $montant, $reason, $origin_type, $origin_id);
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

    $classified = cat_classify_hunt_riddles($enigmes);
    $validables = $classified['validatable'];
    $non_validables = $classified['engagement_only'];

    $progress = cat_get_hunt_progress_service()->calculate($user_id, $validables, $non_validables);

    return [
        'resolues' => $progress['completed'],
        'total'    => $progress['total'],
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

    $classified = cat_classify_hunt_riddles($enigmes);
    $validables = $classified['validatable'];
    $non_validables = $classified['engagement_only'];

    $progress = cat_get_hunt_progress_service()->calculate((int) $user_id, $validables, $non_validables);

    return $progress['completed'];
}

/**
 * Retrieve points history for a user with pagination.
 */
function get_user_points_history(int $user_id = null, int $page = 1, int $per_page = 20): array
{
    $user_id = $user_id ?: get_current_user_id();
    if (!$user_id) {
        return [];
    }

    return cat_get_points_service()->getHistory((int) $user_id, $page, $per_page);
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

    return cat_get_points_service()->countHistory((int) $user_id);
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
function cat_render_points_history_rows(array $operations): string
{
    ob_start();
    foreach ($operations as $op) {
        $variation = (int) $op['points'];
        $variation_label = $variation > 0 ? '+' . $variation : (string) $variation;
        $date = !empty($op['request_date']) ? mysql2date('d/m/Y', $op['request_date']) : '';
        $reason = format_points_history_reason($op);
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

    return (string) ob_get_clean();
}

function ajax_load_points_history(): void
{
    ChassesAuTresor\Core\Points\PointsHistoryAjaxHandler::handle();
}

if (class_exists(ChassesAuTresor\Core\Points\PointsHistoryAjaxHandler::class)) {
    ChassesAuTresor\Core\Points\PointsHistoryAjaxHandler::configure(
        static function (int $user_id, int $page, int $per_page): array {
            return get_user_points_history($user_id, $page, $per_page);
        },
        static function (array $operations): string {
            return cat_render_points_history_rows($operations);
        }
    );
}
