<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/badge-functions.php';
require_once __DIR__ . '/chasse/demo.php';


//
// 1. 📦 FONCTIONS LIÉES À UNE CHASSE
// 2. 📦 AFFICHAGE
//


// ==================================================
// 📦 FONCTIONS LIÉES À UNE CHASSE
// ==================================================
/**
 * 🔹 recuperer_infos_chasse → Récupérer les informations essentielles d’une chasse.
 * 🔹 chasse_get_champs → Récupérer les champs principaux et cachés structurés d'une chasse
 * 🔹 verifier_souscription_chasse → Vérifier si un utilisateur souscrit à une chasse pour la première fois en souscrivant à une énigme.
 * 🔹 chasse_install_winners_table → Créer la table des gagnants lors de l’activation du thème.
 * 🔹 enregistrer_gagnant_chasse → Enregistrer ou mettre à jour un gagnant de chasse.
 * 🔹 acf/validate_value/name=date_de_fin (function) → Valider les incohérences de dates dans les chasses.
 * 🔹 gerer_chasse_terminee → Déclencher toutes les actions nécessaires lorsqu’une chasse est terminée.
 * 🔹 compter_chasses_gagnees → Compter les chasses gagnées par un utilisateur.
 */



/**
 * Récupère les informations essentielles d'une chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @return array Associatif avec 'lot', 'date_de_debut', 'date_de_fin'.
 */
function recuperer_infos_chasse($chasse_id)
{
    $champs = get_fields($chasse_id);
    return [
        'lot' => $champs['lot'] ?? 'Non spécifié',
        'date_de_debut' => $champs['date_de_debut'] ?? 'Non spécifiée',
        'date_de_fin' => $champs['date_de_fin'] ?? 'Non spécifiée',
    ];
}

/**
 * Create the table storing hunt winners on theme activation.
 *
 * @return void
 */
function chasse_install_winners_table(): void
{
    global $wpdb;
    $table = $wpdb->prefix . 'chasse_winners';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT NOT NULL,
        chasse_id BIGINT NOT NULL,
        date_win DATETIME NOT NULL,
        UNIQUE KEY user_chasse (user_id, chasse_id),
        KEY chasse_id (chasse_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
add_action('after_switch_theme', 'chasse_install_winners_table');

/**
 * Insert or update a hunt winner.
 *
 * @param int    $user_id   User ID.
 * @param int    $chasse_id Hunt ID.
 * @param string $date_win  Win date in MySQL format.
 * @return void
 */
function enregistrer_gagnant_chasse(int $user_id, int $chasse_id, string $date_win): void
{
    global $wpdb;
    $table = $wpdb->prefix . 'chasse_winners';

    $wpdb->replace(
        $table,
        [
            'user_id'  => $user_id,
            'chasse_id' => $chasse_id,
            'date_win' => $date_win,
        ],
        ['%d', '%d', '%s']
    );
}

/**
 * Count hunts won by a user.
 *
 * @param int $user_id User ID.
 * @return int
 */
function compter_chasses_gagnees(int $user_id): int
{
    global $wpdb;
    $table = $wpdb->prefix . 'chasse_winners';

    return (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id)
    );
}

/**
 * Récupère les champs principaux et cachés d'une chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @return array
 */
function chasse_get_champs($chasse_id)
{
    return [
        'lot' => get_field('chasse_infos_recompense_texte', $chasse_id, false) ?? '',
        'titre_recompense' => get_field('chasse_infos_recompense_titre', $chasse_id) ?? '',
        'valeur_recompense' => get_field('chasse_infos_recompense_valeur', $chasse_id) ?? '',
        'cout_points' => get_field('chasse_infos_cout_points', $chasse_id) ?? 0,
        'is_demo' => ca_demo_is_demo_hunt((int) $chasse_id),
        // Lecture directe des dates pour éviter un éventuel cache ACF
        'date_debut' => (function () use ($chasse_id) {
            $val = get_field('chasse_infos_date_debut', $chasse_id);
            if (!$val) {
                $meta = get_post_meta($chasse_id, 'chasse_infos_date_debut', true);
                $val = $meta;
            }
            return $val;
        })(),
        'date_fin' => (function () use ($chasse_id) {
            $val = get_field('chasse_infos_date_fin', $chasse_id);
            if (!$val) {
                $meta = get_post_meta($chasse_id, 'chasse_infos_date_fin', true);
                $val = $meta;
            }
            return $val;
        })(),
        'illimitee' => get_field('chasse_infos_duree_illimitee', $chasse_id) ?? false,
        'nb_max' => get_field('chasse_infos_nb_max_gagants', $chasse_id) ?? 0,
        'date_decouverte' => get_field('chasse_cache_date_decouverte', $chasse_id),
        'gagnants' => get_field('chasse_cache_gagnants', $chasse_id) ?? '',
        'mode_fin' => get_field('chasse_mode_fin', $chasse_id) ?? 'automatique',
        'current_stored_statut' => get_field('chasse_cache_statut', $chasse_id),
    ];
}



/**
 * Vérifie si un utilisateur souscrit à une chasse pour la première fois en souscrivant à une énigme.
 *
 * @param int $user_id ID de l'utilisateur
 * @param int $enigme_id ID de l'énigme souscrite
 */
function verifier_souscription_chasse($user_id, $enigme_id)
{

    if (!$user_id || !$enigme_id) {
        cat_debug("🚨 ERREUR : ID utilisateur ou énigme manquant.");
        return;
    }

    // 🏴‍☠️ Récupération de la chasse associée à l’énigme
    $chasse_id = get_field('chasse_associee', $enigme_id);
    if (!$chasse_id) {
        cat_debug("⚠️ Aucune chasse associée à l'énigme ID {$enigme_id}");
        return;
    }

    // 🔍 Vérification si l'utilisateur a déjà joué une énigme de cette chasse
    $enigmes_associees = get_field('enigmes_associees', $chasse_id);
    if (!$enigmes_associees || !is_array($enigmes_associees)) {
        cat_debug("⚠️ Pas d'énigmes associées à la chasse ID {$chasse_id}");
        return;
    }

    foreach ($enigmes_associees as $eid) {
        $statut = get_user_meta($user_id, "statut_enigme_{$eid}", true);

        // 🚫 Si une énigme a déjà été souscrite, tentée ou trouvée, la chasse est déjà souscrite
        if ($statut && $statut !== 'non_souscrit') {
            cat_debug("🔄 L'utilisateur ID {$user_id} a déjà interagi avec l'énigme ID {$eid}. Chasse ID {$chasse_id} déjà souscrite.");
            return;
        }
    }

    cat_debug("🔍 Vérification avant mise à jour souscription chasse ID {$chasse_id} : Utilisateur ID {$user_id}");

    // ✅ Première souscription à une énigme de cette chasse => Marquer la chasse comme souscrite
    update_user_meta($user_id, "souscription_chasse_{$chasse_id}", true);

    // 🔄 Mise à jour du compteur global de souscriptions à la chasse
    $meta_key = "total_joueurs_souscription_chasse_{$chasse_id}";
    $total_souscriptions = get_post_meta($chasse_id, $meta_key, true) ?: 0;
    update_post_meta($chasse_id, $meta_key, $total_souscriptions + 1);
}
/**
 * Vérifie si un utilisateur est engagé dans une chasse.
 *
 * @param int $user_id
 * @param int $chasse_id
 * @return bool
 */
function utilisateur_est_engage_dans_chasse(int $user_id, int $chasse_id): bool
{
    global $wpdb;
    if (!$user_id || !$chasse_id) return false;

    if (
        function_exists('ca_demo_is_demo_hunt')
        && ca_demo_is_demo_hunt($chasse_id)
    ) {
        return $user_id > 0;
    }

    $table = $wpdb->prefix . 'engagements';

    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM $table WHERE user_id = %d AND chasse_id = %d AND enigme_id IS NULL LIMIT 1",
        $user_id,
        $chasse_id
    ));
}



/**
 * 📌 Validation des incohérences de dates dans les chasses.
 */
add_filter('acf/validate_value/name=date_de_fin', function ($valid, $value, $field, $input) {
    if (!$valid) {
        return $valid; // 🚫 Ne pas écraser d'autres erreurs
    }

    if (get_post_type($_POST['post_ID'] ?? 0) !== 'chasse') {
        return $valid;
    }

    // 🔄 Reformater `date_de_fin` si nécessaire
    if (preg_match('/^\d{8}$/', $value)) {
        $value = substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
    }

    // 🔍 Récupération de la date de début à partir des données du formulaire
    $caracteristiques_key = 'field_67ca7fd7f5117'; // ID du groupe "caracteristiques"
    $date_debut_key = 'field_67b58c6fd98ec'; // ID du champ "date_de_debut"
    $date_debut = $_POST['acf'][$caracteristiques_key][$date_debut_key] ?? null;


    // ✅ Vérification : La date de fin ne peut pas être avant la date de début
    if (!empty($date_debut) && !empty($value) && strtotime($value) < strtotime($date_debut)) {
        return __('⚠️ Erreur : La date de fin ne peut pas être antérieure à la date de début.', 'chassesautresor-com');
    }

    // ✅ Vérification : Si "maintenant" est sélectionné, date_de_fin ne peut pas être antérieure à aujourd'hui
    if (
        $_POST['acf'][$caracteristiques_key]['field_67ca858935c21'] === 'maintenant' &&
        !empty($value) && strtotime($value) < strtotime(date('Y-m-d'))
    ) {
        return __('⚠️ Erreur : La date de fin ne peut pas être antérieure à la date du jour si la chasse commence maintenant.', 'chassesautresor-com');
    }

    return $valid;
}, 10, 4);

/**
 * 📌 Déclenche les actions nécessaires lorsqu'une chasse est terminée.
 *
 * Met à jour le statut de toutes les énigmes associées pour tous les joueurs
 * dans la table `wp_enigme_statuts_utilisateur` ainsi que dans les metas
 * utilisateur correspondantes.
 *
 * @param int $chasse_id ID de la chasse concernée.
 * @return void
 */
function gerer_chasse_terminee($chasse_id)
{
    if (!$chasse_id) {
        return;
    }

    // 🔁 Ne rien faire si la chasse est déjà marquée terminée
    if (get_field('chasse_cache_statut', $chasse_id) === 'termine') {
        return;
    }

    $toutes_enigmes = recuperer_enigmes_associees($chasse_id);
    if (empty($toutes_enigmes)) {
        return;
    }

    $validables      = [];
    $non_validables  = [];
    foreach ($toutes_enigmes as $id) {
        if (get_field('enigme_mode_validation', $id) === 'aucune') {
            $non_validables[] = $id;
        } else {
            $validables[] = $id;
        }
    }

    global $wpdb;
    $table = $wpdb->prefix . 'enigme_statuts_utilisateur';
    $now   = current_time('mysql');

    $results = [];
    if ($validables) {
        $placeholders = implode(',', array_fill(0, count($validables), '%d'));
        $sql = "
            SELECT user_id, MIN(date_mise_a_jour) AS first_finish
            FROM {$table}
            WHERE statut IN ('resolue', 'terminee', 'terminée')
              AND enigme_id IN ($placeholders)
            GROUP BY user_id
            HAVING COUNT(DISTINCT enigme_id) = %d
            ORDER BY first_finish ASC
        ";
        $results = $wpdb->get_results(
            $wpdb->prepare($sql, array_merge($validables, [count($validables)]))
        );

        if ($non_validables) {
            $table_eng    = $wpdb->prefix . 'engagements';
            $ph_non_val   = implode(',', array_fill(0, count($non_validables), '%d'));
            foreach ($results as $idx => $row) {
                $uid      = (int) $row->user_id;
                $nb       = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT enigme_id) FROM {$table_eng} WHERE user_id = %d AND enigme_id IN ($ph_non_val)",
                    array_merge([$uid], $non_validables)
                ));
                if ($nb !== count($non_validables)) {
                    unset($results[$idx]);
                }
            }
            $results = array_values($results);
        }
    } elseif ($non_validables) {
        $table_eng    = $wpdb->prefix . 'engagements';
        $placeholders = implode(',', array_fill(0, count($non_validables), '%d'));
        $sql = "
            SELECT user_id, MIN(date_engagement) AS first_finish
            FROM {$table_eng}
            WHERE enigme_id IN ($placeholders)
            GROUP BY user_id
            HAVING COUNT(DISTINCT enigme_id) = %d
            ORDER BY first_finish ASC
        ";
        $results = $wpdb->get_results(
            $wpdb->prepare($sql, array_merge($non_validables, [count($non_validables)]))
        );
    }

    $max_winners = (int) get_field('chasse_infos_nb_max_gagants', $chasse_id);
    if ($max_winners > 0 && count($results) > $max_winners) {
        $total_found = count($results);
        cat_debug("⚠️ Plus de gagnants ({$total_found}) que la limite ({$max_winners}) pour la chasse {$chasse_id}. Seuls les premiers arrivés seront retenus.");
        $results = array_slice($results, 0, $max_winners);
    }

    $winner_ids = wp_list_pluck($results, 'user_id');
    $winner_total = count($winner_ids);

    $winner_names = [];
    foreach ($results as $row) {
        $uid = (int) $row->user_id;
        $date_win = $row->first_finish ?? $now;
        enregistrer_gagnant_chasse($uid, $chasse_id, $date_win);
        $user = get_userdata($uid);
        if ($user) {
            $winner_names[] = $user->display_name ?: $user->user_login;
        }
    }

    // Toujours enregistrer la liste des gagnants actuelle
    $list = implode(', ', $winner_names);
    update_field('chasse_cache_gagnants', $list, $chasse_id);

    $should_close = ($max_winners === 0 || $winner_total >= $max_winners);
    if ($should_close) {
        $date = current_time('Y-m-d H:i:s');
        $date_obj = DateTime::createFromFormat('Y-m-d H:i:s', $date);
        if ($date_obj && $date_obj->format('Y-m-d H:i:s') === $date) {
            update_field('chasse_cache_date_decouverte', $date, $chasse_id);
        }

        // ✅ Marquer la chasse comme complète et terminée
        update_field('chasse_cache_complet', 1, $chasse_id);
        update_field('chasse_cache_statut', 'termine', $chasse_id);
    }

    foreach ($toutes_enigmes as $enigme_id) {
        // 🗃️ Mise à jour des statuts en base
        $wpdb->update(
            $table,
            [
                'statut'           => 'terminee',
                'date_mise_a_jour' => $now,
            ],
            [
                'enigme_id' => $enigme_id,
            ],
            ['%s', '%s'],
            ['%d']
        );

        // 🔄 Synchronisation des metas utilisateur
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$table} WHERE enigme_id = %d",
            $enigme_id
        ));

        foreach ($user_ids as $uid) {
            update_user_meta((int) $uid, "statut_enigme_{$enigme_id}", 'terminee');
        }
    }

    // Rafraîchir le statut général de la chasse
    if (function_exists('mettre_a_jour_statuts_chasse')) {
        mettre_a_jour_statuts_chasse($chasse_id);
    }

    // 🏆 Actions futures : récompenses, notifications, etc.
    //attribuer_recompenses_chasse($chasse_id);
    //notifier_fin_chasse($chasse_id);
}



// ==================================================
// 📦 AFFICHAGE
// ==================================================
/**
 * 🔹 afficher_picture_vignette_chasse() → Affiche une balise <picture> responsive pour l’image d’une chasse.
 * 🔹 afficher_chasse_associee_callback → ffiche les informations principales de la chasse associée à l’énigme.
 */


/**
 *
 * @param int    $chasse_id
 * @param string $alt Texte alternatif pour l’image (optionnel)
 */
function afficher_picture_vignette_chasse($chasse_id, $alt = '')
{
    if (!is_numeric($chasse_id)) return;

    $image = get_field('chasse_principale_image', $chasse_id);
    $permalink = get_permalink($chasse_id);

    if (!is_array($image) || empty($image['url'])) {
        echo '<a href="' . esc_url($permalink) . '" class="image-chasse-placeholder">';
        echo '<i class="fa-solid fa-map fa-2x"></i>';
        echo '</a>';
        return;
    }

    $src_small = $image['sizes']['medium'] ?? $image['url'];
    $src_large = $image['sizes']['large'] ?? $image['url'];
    $alt = esc_attr($alt ?: $image['alt'] ?? get_the_title($chasse_id));

    echo '<a href="' . esc_url($permalink) . '">';
    echo '<picture>';
    echo '<source media="(min-width: 768px)" srcset="' . esc_url($src_large) . '">';
    echo '<img src="' . esc_url($src_small) . '" alt="' . $alt . '" loading="lazy">';
    echo '</picture>';
    echo '</a>';
}



/**
 * 🏴‍☠️ Affiche les informations principales de la chasse associée à l’énigme.
 *
 * Informations affichées (sauf si l'énigme est souscrite/en cours) :
 * - Titre de la chasse
 * - Lot
 * - Durée
 * - Icône Discord cliquable (si lien ACF disponible)
 *
 * @return string HTML des informations de la chasse ou chaîne vide si aucune chasse associée ou énigme en cours.
 */
function afficher_chasse_associee_callback()
{
    if (!is_singular('enigme')) return '';

    $enigme_id = get_the_ID();
    $user_id = get_current_user_id();

    // ✅ Si l’énigme est souscrite (en cours), on n'affiche pas la chasse associée
    $statut = enigme_get_statut_light($user_id, $enigme_id);
    if ($statut === 'en_cours') return '';

    $chasse = recuperer_chasse_associee($enigme_id);
    if (!$chasse) return ''; // 🚫 Pas de chasse associée

    $infos_chasse = recuperer_infos_chasse($chasse->ID) ?: [
        'lot' => 'Non spécifié',
        'date_de_debut' => 'Non spécifiée',
        'date_de_fin' => 'Non spécifiée',
    ];

    $lien_discord = get_field('lien_discord', $chasse->ID);
    $icone_discord = esc_url(get_stylesheet_directory_uri() . '/assets/images/discord-icon.png');
    $titre = esc_html(get_the_title($chasse->ID));
    $url = esc_url(get_permalink($chasse->ID));

    ob_start(); ?>
    <section class="chasse-associee">
        <h3>Chasse au Trésor</h3>
        <h2><strong><a href="<?= $url; ?>" class="lien-chasse-associee"><?= $titre; ?></a></strong></h2>
        <p>🏆 <strong>Lot :</strong> <?= esc_html($infos_chasse['lot']); ?></p>
        <p>📅 <strong>Durée :</strong> <?= esc_html($infos_chasse['date_de_debut']); ?> au <?= esc_html($infos_chasse['date_de_fin']); ?></p>

        <?php if (!empty($lien_discord)) : ?>
            <p>
                <a href="<?= esc_url($lien_discord); ?>" target="_blank" rel="noopener noreferrer" aria-label="Rejoindre le Discord">
                    <img src="<?= $icone_discord; ?>" alt="Discord" class="discord-icon">
                </a>
            </p>
        <?php endif; ?>
    </section>
<?php
    return ob_get_clean();
}

/**
 * Détermine si l'organisateur peut demander la validation d'une chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @param int $user_id   ID de l'utilisateur.
 * @return bool
 */
function peut_valider_chasse(int $chasse_id, int $user_id): bool
{
    if (!$chasse_id || !$user_id) {
        return false;
    }

    if (get_post_type($chasse_id) !== 'chasse') {
        return false;
    }

    if (!est_organisateur($user_id)) {
        return false;
    }

    if (!utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)) {
        return false;
    }

    $organisateur_id = get_organisateur_from_chasse($chasse_id);
    if (!$organisateur_id || !get_field('organisateur_cache_complet', $organisateur_id)) {
        return false;
    }

    if (!get_field('chasse_cache_complet', $chasse_id)) {
        return false;
    }

    if (get_post_status($chasse_id) !== 'pending') {
        return false;
    }

    $statut_validation = get_field('chasse_cache_statut_validation', $chasse_id);
    $statut_metier     = get_field('chasse_cache_statut', $chasse_id);

    if (!in_array($statut_validation ?? '', ['creation', 'correction'], true)) {
        return false;
    }

    if (($statut_metier ?? '') !== 'revision') {
        return false;
    }

    // Utilise la requête directe pour lister toutes les énigmes rattachées
    // afin d'éviter toute incohérence liée au cache "chasse_cache_enigmes".
    $enigmes = recuperer_ids_enigmes_pour_chasse($chasse_id);
    if (empty($enigmes)) {
        return false;
    }

    foreach ($enigmes as $eid) {
        $etat = get_field('enigme_cache_etat_systeme', $eid);
        $complet = get_field('enigme_cache_complet', $eid);
        if ($etat !== 'bloquee_chasse' || !$complet) {
            return false;
        }
    }

    return true;
}

/**
 * Calcule la progression d'un joueur dans une chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @param int $user_id   ID de l'utilisateur.
 * @return array{
 *     engagees:int,
 *     total:int,
 *     resolues:int,
 *     resolvables:int
 * }
 */
function chasse_calculer_progression_utilisateur(int $chasse_id, int $user_id): array
{
    $enigmes = recuperer_enigmes_associees($chasse_id);
    $total   = count($enigmes);
    $is_demo = function_exists('ca_demo_is_demo_hunt')
        ? ca_demo_is_demo_hunt($chasse_id)
        : false;

    $resolvables = 0;
    if ($total > 0) {
        global $wpdb;
        $placeholders = implode(',', array_fill(0, $total, '%d'));
        $sql = "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->prefix}postmeta WHERE meta_key = %s "
            . "AND meta_value <> %s AND post_id IN ($placeholders)";
        $params = array_merge(['enigme_mode_validation', 'aucune'], $enigmes);
        $resolvables = (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    $engagees = 0;
    $resolues = 0;

    if ($user_id && $total > 0) {
        global $wpdb;
        $placeholders = implode(',', array_fill(0, $total, '%d'));

        if ($is_demo) {
            $table_statuts = $wpdb->prefix . 'enigme_statuts_utilisateur';
            $params        = array_merge([$user_id], $enigmes);

            $sql_engagees = "SELECT COUNT(DISTINCT enigme_id) FROM {$table_statuts}"
                . " WHERE user_id = %d AND enigme_id IN ($placeholders)"
                . " AND statut NOT IN ('non_commencee','non_souscrite')";
            $engagees = (int) $wpdb->get_var($wpdb->prepare($sql_engagees, $params));

            $sql_resolues = "SELECT COUNT(DISTINCT enigme_id) FROM {$table_statuts}"
                . " WHERE user_id = %d AND enigme_id IN ($placeholders)"
                . " AND statut IN ('resolue','terminee','terminée')";
            $resolues = (int) $wpdb->get_var($wpdb->prepare($sql_resolues, $params));
        } else {
            $sql = "SELECT COUNT(DISTINCT enigme_id) FROM {$wpdb->prefix}engagements WHERE user_id = %d AND enigme_id IN ($placeholders)";
            $engagees = (int) $wpdb->get_var($wpdb->prepare($sql, array_merge([$user_id], $enigmes)));
        }
    }

    if (!$is_demo && $user_id && function_exists('compter_enigmes_resolues')) {
        $resolues = compter_enigmes_resolues($chasse_id, $user_id);
    }

    return [
        'engagees'    => $engagees,
        'total'       => $total,
        'resolues'    => $resolues,
        'resolvables' => $resolvables,
    ];
}

/**
 * Génère le bouton d'action et le message d'explication pour une chasse.
 *
 * @param int      $chasse_id ID de la chasse.
 * @param int|null $user_id   ID de l'utilisateur (par défaut : utilisateur courant).
 *
 * @return array{cta_html:string, cta_message:string}
 */
function generer_cta_chasse(int $chasse_id, ?int $user_id = null): array
{
    $user_id    = $user_id ?? get_current_user_id();
    $permalink  = get_permalink($chasse_id);
    $statut     = get_field('chasse_cache_statut', $chasse_id) ?: 'revision';
    $validation = get_field('chasse_cache_statut_validation', $chasse_id);
    $date_debut = get_field('chasse_infos_date_debut', $chasse_id);
    $date_fin   = get_field('chasse_infos_date_fin', $chasse_id);
    $is_demo    = function_exists('ca_demo_is_demo_hunt')
        ? ca_demo_is_demo_hunt($chasse_id)
        : false;
    $response   = static function (array $data) use ($is_demo): array {
        $data['is_demo'] = $is_demo;

        return $data;
    };

    // 🧑‍💻 Utilisateur non connecté
    if (! $user_id) {
        $login_url = wp_login_url($permalink);

        return $response([
            'cta_html'    => sprintf(
                '<a href="%s" class="bouton-cta bouton-cta--color">%s</a>',
                esc_url($login_url),
                esc_html__('S\'identifier', 'chassesautresor-com')
            ),
            'cta_message' => '',
            'type'        => 'connexion',
        ]);
    }

    if (peut_valider_chasse($chasse_id, $user_id)) {
        return $response([
            'cta_html'    => render_form_validation_chasse($chasse_id),
            'cta_message' => '',
            'type'        => 'validation',
        ]);
    }

    // 🔐 Admin or organiser info
    $admin_override = $GLOBALS['force_admin_override'] ?? null;
    $is_admin = $admin_override !== null ? (bool) $admin_override : current_user_can('administrator');
    $orga_override = $GLOBALS['force_organisateur_override'] ?? null;
    $is_orga = $orga_override !== null ? (bool) $orga_override : utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id);

    if ($validation === 'en_attente') {
        if ($is_orga) {
            return $response([
                'cta_html'    => render_form_annulation_validation_chasse($chasse_id),
                'cta_message' => '',
                'type'        => 'annuler_validation',
            ]);
        }
        return $response([
            'cta_html'    => '<span class="bouton-cta bouton-cta--pending" aria-disabled="true">'
                . esc_html__( 'Demande de validation en cours', 'chassesautresor-com' )
                . '</span>',
            'cta_message' => '',
            'type'        => 'en_attente',
        ]);
    }

    // 🔐 Admin or organiser: front-end edition
    if ($is_orga && in_array($validation, ['creation', 'correction'], true)) {
        $edition_url = function_exists('add_query_arg')
            ? add_query_arg(['edition' => 'open', 'tab' => 'param'], $permalink)
            : $permalink . '?edition=open&tab=param';

        return $response([
            'cta_html'    => sprintf(
                '<a href="%s" class="bouton-secondaire">%s</a>',
                esc_url($edition_url),
                esc_html__('Continuer l’édition', 'chassesautresor-com')
            ),
            'cta_message' => '',
            'type'        => 'edition',
        ]);
    }

    if (
        $is_orga
        && in_array($statut, ['en_cours', 'payante'], true)
        && in_array($validation, ['valide', 'active'], true)
    ) {
        $stats_url = function_exists('add_query_arg')
            ? add_query_arg(['edition' => 'open', 'tab' => 'stats'], $permalink)
            : $permalink . '?edition=open&tab=stats';

        return $response([
            'cta_html'    => sprintf(
                '<a href="%s" class="bouton-secondaire">%s</a>',
                esc_url($stats_url),
                esc_html__('Statistiques', 'chassesautresor-com')
            ),
            'cta_message' => '',
            'type'        => 'statistiques',
        ]);
    }

    if ($is_admin || $is_orga) {
        return $response([
            'cta_html'    => sprintf(
                '<button class="bouton-cta" disabled>%s</button>',
                esc_html__( 'Participer', 'chassesautresor-com' )
            ),
            'cta_message' => '',
            'type'        => 'indisponible',
        ]);
    }

    // ✅ Déjà engagé
    $engage_override = $GLOBALS['force_engage_override'] ?? null;
    $est_engage = $engage_override !== null ? (bool) $engage_override : utilisateur_est_engage_dans_chasse($user_id, $chasse_id);
    if ($est_engage) {
        if ($is_demo) {
            $nonce_action = 'ca_demo_reset_chasse_' . $chasse_id . '_' . $user_id;
            $nonce = function_exists('wp_create_nonce')
                ? wp_create_nonce($nonce_action)
                : $nonce_action;

            $icon_markup = '';
            if (function_exists('get_svg_icon')) {
                $icon_markup = trim(get_svg_icon('reset'));
            }

            if ($icon_markup !== '') {
                $icon_markup = '<span class="cta-reset-demo__icon" aria-hidden="true">' . $icon_markup . '</span>';
            }

            $button_html = sprintf(
                '<button type="button" class="bouton-cta bouton-cta--color cta-reset-demo__button" data-ca-demo-reset="1"'
                . ' data-chasse-id="%1$d" data-nonce="%2$s">%3$s<span class="cta-reset-demo__label">%4$s</span></button>',
                (int) $chasse_id,
                esc_attr($nonce),
                $icon_markup,
                esc_html__('Réinitialiser ma progression', 'chassesautresor-com')
            );

            return $response([
                'cta_html'    => $button_html,
                'cta_message' => '<p class="cta-reset-demo__message">' . esc_html__(
                    'Ceci est une chasse de démonstration',
                    'chassesautresor-com'
                ) . '</p>',
                'type'        => 'reset_demo',
            ]);
        }

        return $response([
            'cta_html'    => '<a href="#chasse-enigmes-wrapper" class="bouton-secondaire">' . esc_html__('Voir mes énigmes', 'chassesautresor-com') . '</a>',
            'cta_message' => '<p>✅ ' . esc_html__('Vous participez à cette chasse', 'chassesautresor-com') . '</p>',
            'type'        => 'engage',
        ]);
    }

    // ❌ Chasse non validée
    if ($validation !== 'valide') {
        return $response(['cta_html' => '', 'cta_message' => '', 'type' => '']);
    }

    $html    = '';
    $message = '';
    $type    = '';

    if ($statut === 'a_venir') {
        $html = sprintf(
            '<button class="bouton-cta" disabled>%s</button>',
            esc_html__('Indisponible', 'chassesautresor-com')
        );
        $type = 'indisponible';
        $message = $date_debut
            ? sprintf(
                __('Chasse disponible à partir du %s', 'chassesautresor-com'),
                date_i18n('d/m/Y \à H:i', strtotime($date_debut))
            )
            : __('Chasse disponible prochainement', 'chassesautresor-com');
    } elseif ($statut === 'en_cours' || $statut === 'payante') {
        $cout_points        = (int) get_field('chasse_infos_cout_points', $chasse_id);
        $points_disponibles = get_user_points($user_id);

        if ($statut === 'payante' && $cout_points > 0 && $points_disponibles < $cout_points) {
            $html = sprintf(
                '<button class="bouton-cta" disabled>%s</button>',
                esc_html__( 'Points insuffisants', 'chassesautresor-com' )
            );
            $points_manquants = $cout_points - $points_disponibles;
            $points_lien      = sprintf(
                '<a href="%s">%s</a>',
                esc_url( site_url( '/boutique' ) ),
                esc_html__( 'points', 'chassesautresor-com' )
            );
            $message = sprintf(
                __( 'Il vous manque %1$d %2$s pour participer à cette chasse.', 'chassesautresor-com' ),
                $points_manquants,
                $points_lien
            );
            $type = 'indisponible';
        } else {
            // 🔓 Participation gratuite à ce stade, engagement simple
            $html  = '<form method="post" action="' . esc_url(site_url('/traitement-engagement')) . '" class="cta-chasse-form">';
            $html .= '<input type="hidden" name="chasse_id" value="' . esc_attr($chasse_id) . '">';
            $html .= wp_nonce_field('engager_chasse_' . $chasse_id, 'engager_chasse_nonce', true, false);
            $html .= sprintf(
                '<button type="submit" class="bouton-cta bouton-cta--color">%s</button>',
                esc_html__('Participer', 'chassesautresor-com')
            );
            $html .= '</form>';
            $message = '';
            $type    = 'engager';
        }
    } elseif ($statut === 'termine') {
        // ✅ Chasse terminée : engagement requis pour accéder aux énigmes
        $html  = '<form method="post" action="' . esc_url(site_url('/traitement-engagement')) . '" class="cta-chasse-form">';
        $html .= '<input type="hidden" name="chasse_id" value="' . esc_attr($chasse_id) . '">';
        $html .= wp_nonce_field('engager_chasse_' . $chasse_id, 'engager_chasse_nonce', true, false);
        $html .= sprintf(
            '<button type="submit" class="bouton-cta bouton-cta--color">%s</button>',
            esc_html__('Redécouvrir', 'chassesautresor-com')
        );
        $html .= '</form>';
        $type = 'engager';
        $message = '';
    }

        return $response([
            'cta_html'    => $html,
            'cta_message' => $message,
            'type'        => $type,
        ]);
}

/**
 * Compte le nombre de joueurs ayant engagé au moins une énigme de la chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @return int Nombre de joueurs uniques engagés.
 */
function compter_joueurs_engages_chasse(int $chasse_id): int
{
    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        return 0;
    }

    if (
        function_exists('ca_demo_is_demo_hunt')
        && ca_demo_is_demo_hunt($chasse_id)
    ) {
        return 0;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'engagements';

    $query = $wpdb->prepare(
        "SELECT COUNT(DISTINCT user_id)
         FROM $table
         WHERE chasse_id = %d",
        $chasse_id
    );

    return (int) $wpdb->get_var($query);
}


/**
 * Enregistre un engagement à une chasse pour un utilisateur.
 *
 * @param int $user_id
 * @param int $chasse_id
 * @return bool true si un enregistrement a été effectué, false sinon.
 */
function enregistrer_engagement_chasse(int $user_id, int $chasse_id): bool
{
    global $wpdb;

    if (!$user_id || !$chasse_id) {
        return false;
    }

    if (current_user_can('administrator') || utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)) {
        return false;
    }

    if (
        function_exists('ca_demo_is_demo_hunt')
        && ca_demo_is_demo_hunt($chasse_id)
    ) {
        chasse_clear_infos_affichage_cache($chasse_id);

        return true;
    }

    $table = $wpdb->prefix . 'engagements';

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM $table WHERE user_id = %d AND chasse_id = %d AND enigme_id IS NULL LIMIT 1",
        $user_id,
        $chasse_id
    ));

    if ($exists) {
        return false;
    }

    $inserted = $wpdb->insert(
        $table,
        [
            'user_id'         => $user_id,
            'chasse_id'       => $chasse_id,
            'date_engagement' => current_time('mysql', 1),
        ],
        ['%d', '%d', '%s']
    );

    if (!empty($wpdb->last_error)) {
        return false;
    }

    if ($inserted) {
        do_action('chasse_engagement_created', $chasse_id);
    }

    return (bool) $inserted;
}

/**
 * Formate le libellé internationalisé du nombre de joueurs.
 *
 * @param int $nombre Nombre de joueurs.
 * @return string Libellé formaté.
 */
function formater_nombre_joueurs(int $nombre): string
{
    $quantite = ($nombre === 0 || $nombre === 1) ? 1 : $nombre;

    return sprintf(
        _n('%d joueur', '%d joueurs', $quantite, 'chassesautresor-com'),
        $nombre
    );
}

/**
 * Retourne la première chasse pouvant être soumise à validation pour un utilisateur.
 *
 * @param int $user_id ID utilisateur.
 * @return int|null ID de la chasse ou null.
 */
function trouver_chasse_a_valider(int $user_id): ?int
{
    $organisateur_id = get_organisateur_from_user($user_id);
    if (!$organisateur_id) {
        return null;
    }

    $query   = get_chasses_de_organisateur($organisateur_id);
    $chasses = is_a($query, 'WP_Query') ? $query->posts : (array) $query;

    foreach ($chasses as $chasse_id) {
        $chasse_id = (int) $chasse_id;
        if (peut_valider_chasse($chasse_id, $user_id)) {
            return $chasse_id;
        }
    }

    return null;
}

/**
 * Génère le formulaire de demande de validation pour une chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @return string HTML du formulaire.
 */
function render_form_validation_chasse(int $chasse_id): string
{
    $nonce = wp_create_nonce('validation_chasse_' . $chasse_id);
    ob_start();
?>
    <form method="post" action="<?= esc_url(site_url('/traitement-validation-chasse')); ?>" class="form-validation-chasse">
        <input type="hidden" name="chasse_id" value="<?= esc_attr($chasse_id); ?>">
        <input type="hidden" name="validation_chasse_nonce" value="<?= esc_attr($nonce); ?>">
        <input type="hidden" name="demande_validation_chasse" value="1">
        <button type="submit" class="bouton-cta bouton-cta--color bouton-validation-chasse">
            <?= esc_html__( 'Demander la validation', 'chassesautresor-com' ); ?>
        </button>
    </form>
<?php
    return ob_get_clean();
}

/**
 * Génère le formulaire d'annulation d'une demande de validation.
 *
 * @param int $chasse_id ID de la chasse.
 * @return string HTML du formulaire.
 */
function render_form_annulation_validation_chasse(int $chasse_id): string
{
    $nonce = wp_create_nonce('annulation_validation_chasse_' . $chasse_id);
    ob_start();
?>
    <form method="post" action="<?= esc_url(admin_url('admin-ajax.php')); ?>" class="form-annulation-validation-chasse">
        <input type="hidden" name="action" value="annulation_validation_chasse">
        <input type="hidden" name="chasse_id" value="<?= esc_attr($chasse_id); ?>">
        <input type="hidden" name="annulation_validation_chasse_nonce" value="<?= esc_attr($nonce); ?>">
        <input type="hidden" name="annuler_validation_chasse" value="1">
        <button type="submit" class="bouton-cta bouton-cta--color bouton-annulation-validation-chasse">
            <?= esc_html__( 'Annuler la demande', 'chassesautresor-com' ); ?>
        </button>
    </form>
<?php
    return ob_get_clean();
}

add_action('wp_ajax_annulation_validation_chasse', 'traiter_annulation_validation_chasse');
add_action('wp_ajax_nopriv_annulation_validation_chasse', 'traiter_annulation_validation_chasse');

function traiter_annulation_validation_chasse(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_redirect(home_url());
        exit;
    }

    $chasse_id = isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;
    $user_id   = get_current_user_id();

    if (!$user_id || !$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        wp_redirect(home_url());
        exit;
    }

    $nonce_action = 'annulation_validation_chasse_' . $chasse_id;
    if (
        !isset($_POST['annulation_validation_chasse_nonce']) ||
        !wp_verify_nonce($_POST['annulation_validation_chasse_nonce'], $nonce_action)
    ) {
        wp_die( __( 'Vérification de sécurité échouée.', 'chassesautresor-com' ) );
    }

    if (
        !current_user_can('administrator') &&
        !utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)
    ) {
        wp_die( __( 'Conditions non remplies.', 'chassesautresor-com' ) );
    }

    if (empty($_POST['annuler_validation_chasse'])) {
        wp_redirect(home_url());
        exit;
    }

    require_once get_theme_file_path('inc/statut-functions.php');
    require_once get_theme_file_path('inc/relations-functions.php');
    require_once get_theme_file_path('inc/user-functions.php');

    forcer_statut_apres_acf($chasse_id, 'a_venir');
    update_field('chasse_cache_statut', 'a_venir', $chasse_id);
    update_field('chasse_cache_statut_validation', 'correction', $chasse_id);

    wp_redirect(add_query_arg('validation_annulee', '1', get_permalink($chasse_id)));
    exit;
}

/**
 * Retourne le bloc d'incitation à la validation d'une chasse pour mise à jour dynamique.
 *
 * @hook wp_ajax_actualiser_cta_validation_chasse
 * @return void
 */
add_action('wp_ajax_actualiser_cta_validation_chasse', 'actualiser_cta_validation_chasse');

function actualiser_cta_validation_chasse(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $enigme_id = isset($_POST['enigme_id']) ? (int) $_POST['enigme_id'] : 0;
    if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') {
        wp_send_json_error('post_invalide');
    }

    $chasse_id = recuperer_id_chasse_associee($enigme_id);
    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        wp_send_json_error('chasse_invalide');
    }

    verifier_ou_mettre_a_jour_cache_complet($enigme_id);
    verifier_ou_mettre_a_jour_cache_complet($chasse_id);

    $posts = get_posts([
        'post_type'      => 'enigme',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'post_status'    => ['publish', 'pending', 'draft'],
        'meta_query'     => [[
            'key'     => 'enigme_chasse_associee',
            'value'   => $chasse_id,
            'compare' => 'LIKE',
        ]],
    ]);

    foreach ($posts as $p) {
        verifier_ou_mettre_a_jour_cache_complet($p->ID);
    }

    ob_start();
    if (peut_valider_chasse($chasse_id, get_current_user_id())) {
        echo '<div id="cta-validation-chasse" class="cta-chasse-row">';
        echo '<div class="cta-action">' . render_form_validation_chasse($chasse_id) . '</div>';
        echo '<div class="cta-message" aria-live="polite"></div>';
        echo '</div>';
    }
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}


/**
 * Retrieve the active solution for a given object.
 *
 * @param int    $id   Object ID.
 * @param string $type Object type ('enigme' or 'chasse').
 * @return WP_Post|null
 */
function solution_recuperer_par_objet(int $id, string $type)
{
    if (!$id || !in_array($type, ['enigme', 'chasse'], true)) {
        return null;
    }

    $meta_key   = $type === 'enigme' ? 'solution_enigme_linked' : 'solution_chasse_linked';
    $meta_query = [
        'relation' => 'AND',
        [
            'key'   => 'solution_cible_type',
            'value' => $type,
        ],
        [
            'key'     => 'solution_cache_etat_systeme',
            'value'   => [
                SOLUTION_STATE_EN_COURS,
                SOLUTION_STATE_A_VENIR,
                SOLUTION_STATE_FIN_CHASSE,
                SOLUTION_STATE_FIN_CHASSE_DIFFERE,
            ],
            'compare' => 'IN',
        ],
        [
            'relation' => 'OR',
            [
                'key'   => $meta_key,
                'value' => $id,
            ],
            [
                'key'     => $meta_key,
                'value'   => '"' . $id . '"',
                'compare' => 'LIKE',
            ],
        ],
    ];

    $solutions = get_posts([
        'post_type'      => 'solution',
        'post_status'    => ['publish', 'pending', 'draft'],
        'posts_per_page' => 1,
        'meta_query'     => $meta_query,
    ]);

    return $solutions[0] ?? null;
}

/**
 * Checks whether a solution exists for the given object.
 *
 * Unlike {@see solution_recuperer_par_objet()}, this helper ignores the
 * cache state and returns any matching solution regardless of its
 * publication status.
 *
 * @param int    $id   Object ID.
 * @param string $type Object type ('enigme' or 'chasse').
 * @return bool True if a solution exists, false otherwise.
 */
function solution_existe_pour_objet(int $id, string $type): bool
{
    if (!$id || !in_array($type, ['enigme', 'chasse'], true)) {
        return false;
    }

    $meta_key   = $type === 'enigme' ? 'solution_enigme_linked' : 'solution_chasse_linked';
    $meta_query = [
        'relation' => 'AND',
        [
            'key'   => 'solution_cible_type',
            'value' => $type,
        ],
        [
            'relation' => 'OR',
            [
                'key'   => $meta_key,
                'value' => $id,
            ],
            [
                'key'     => $meta_key,
                'value'   => '"' . $id . '"',
                'compare' => 'LIKE',
            ],
        ],
    ];

    $solutions = get_posts([
        'post_type'      => 'solution',
        'post_status'    => ['publish', 'pending', 'draft', 'private', 'future'],
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'posts_per_page' => 1,
        'meta_query'     => $meta_query,
    ]);

    return !empty($solutions);
}

/**
 * Vérifie si la solution d'une énigme peut être affichée.
 *
 * La solution n'est visible que si la chasse associée est terminée
 * et que l'éventuel délai configuré est écoulé.
 *
 * @param int $enigme_id ID de l'énigme.
 * @return bool
 */
function solution_peut_etre_affichee(int $enigme_id): bool
{
    if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') {
        return false;
    }

    $solution = solution_recuperer_par_objet($enigme_id, 'enigme');
    if (!$solution) {
        return false;
    }

    $chasse_id = recuperer_id_chasse_associee($enigme_id);
    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        return false;
    }

    $statut   = get_field('chasse_cache_statut', $chasse_id);
    if ($statut !== 'termine') {
        return false;
    }

    $dispo    = get_field('solution_disponibilite', $solution->ID) ?: 'fin_chasse';
    $decalage = (int) get_field('solution_decalage_jours', $solution->ID);
    $heure    = get_field('solution_heure_publication', $solution->ID) ?: '00:00';
    $now      = current_time('timestamp');

    if ($dispo === 'differee') {
        $base = get_field('date_de_decouverte', $chasse_id);
        if (!$base) {
            $base = get_field('chasse_infos_date_fin', $chasse_id);
        }
        $timestamp_base = $base ? strtotime($base) : $now;
        $cible          = strtotime("+$decalage days $heure", $timestamp_base);
        if ($cible && $now < $cible) {
            return false;
        }
    }

    return true;
}

/**
 * Vérifie si la solution d'une chasse peut être affichée.
 *
 * La solution n'est visible que si la chasse est terminée et que
 * l'éventuel délai configuré est écoulé.
 *
 * @param int $chasse_id ID de la chasse.
 * @return bool
 */
function solution_chasse_peut_etre_affichee(int $chasse_id): bool
{
    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        return false;
    }

    $solution = solution_recuperer_par_objet($chasse_id, 'chasse');
    if (!$solution) {
        return false;
    }

    $statut   = get_field('chasse_cache_statut', $chasse_id);
    if ($statut !== 'termine') {
        return false;
    }

    $dispo    = get_field('solution_disponibilite', $solution->ID) ?: 'fin_chasse';
    $decalage = (int) get_field('solution_decalage_jours', $solution->ID);
    $heure    = get_field('solution_heure_publication', $solution->ID) ?: '00:00';
    $now      = current_time('timestamp');

    if ($dispo === 'differee') {
        $base = get_field('date_de_decouverte', $chasse_id);
        if (!$base) {
            $base = get_field('chasse_infos_date_fin', $chasse_id);
        }
        $timestamp_base = $base ? strtotime($base) : $now;
        $cible          = strtotime("+$decalage days $heure", $timestamp_base);
        if ($cible && $now < $cible) {
            return false;
        }
    }

    return true;
}

/**
 * Retourne le HTML d'une solution (PDF ou texte).
 *
 * @param WP_Post $solution Solution post object.
 * @return string
 */
function solution_contenu_html(WP_Post $solution): string
{
    $fichier = get_field('solution_fichier', $solution->ID);
    $texte   = get_field('solution_explication', $solution->ID);
    $content = '';

    if ($texte) {
        $content .= '<div class="solution-text"><p>'
            . wp_kses_post($texte)
            . '</p></div>';
    }

    if ($fichier) {
        if (is_array($fichier)) {
            $fichier_url = $fichier['url'] ?? '';
        } else {
            $fichier_url = wp_get_attachment_url($fichier);
        }

        if (!empty($fichier_url)) {
            $content .= '<object data="' . esc_url($fichier_url)
                . '" type="application/pdf" width="100%" height="800">'
                . '<p>'
                . esc_html__(
                    'Votre navigateur ne peut pas afficher le PDF.',
                    'chassesautresor-com'
                )
                . ' <a href="' . esc_url($fichier_url)
                . '" class="lien-solution-pdf" target="_blank" rel="noopener">'
                . esc_html__('Télécharger', 'chassesautresor-com')
                . '</a></p></object>';
        }
    }

    return $content;
}

/**
 * Affiche la section des solutions pour une chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @param int $user_id   ID de l'utilisateur courant.
 */
function render_chasse_solutions(int $chasse_id, int $user_id): void
{
    if (get_post_type($chasse_id) !== 'chasse') {
        return;
    }

    $sections = '';

    if (solution_chasse_peut_etre_affichee($chasse_id)
        && utilisateur_peut_voir_solution_chasse($chasse_id, $user_id)) {
        $solution = solution_recuperer_par_objet($chasse_id, 'chasse');
        if ($solution) {
            $content = solution_contenu_html($solution);
            if ($content !== '') {
                $sections .= '<section class="solution">';
                $sections .= '<details><summary>'
                    . esc_html__('Solution de la chasse', 'chassesautresor-com')
                    . '</summary>';
                $sections .= '<div class="solution-content">'
                    . $content
                    . '</div></details></section>';
            }
        }
    }

    if ($sections === '') {
        return;
    }

    echo '<section id="chasse-solutions" class="chasse-solutions">';
    echo '<h2>' . esc_html__('Solutions', 'chassesautresor-com') . '</h2>';
    echo $sections;
    echo '</section>';
}

/**
 * Prépare les données d'affichage des termes associés à une chasse.
 *
 * @param int    $chasse_id ID de la chasse.
 * @param string $taxonomy  Taxonomie ciblée.
 *
 * @return array[]
 */
function chasse_preparer_termes_affichage(int $chasse_id, string $taxonomy): array
{
    $terms = [];

    if (function_exists('wp_get_post_terms')) {
        $terms = wp_get_post_terms($chasse_id, $taxonomy, ['orderby' => 'term_order']);
        if (is_wp_error($terms)) {
            $terms = [];
        }
    }

    if (empty($terms) && function_exists('get_field')) {
        $acf_fields = [
            'chasse_region' => 'chasse_region',
            'theme_chasse'  => 'chasse_theme',
        ];

        if (isset($acf_fields[$taxonomy])) {
            $raw_terms = get_field($acf_fields[$taxonomy], $chasse_id);

            if ($raw_terms instanceof \WP_Term) {
                $terms = [$raw_terms];
            } elseif (is_array($raw_terms)) {
                $terms = chasse_is_list($raw_terms) ? $raw_terms : [$raw_terms];
            } elseif ($raw_terms !== null && $raw_terms !== '') {
                $terms = [$raw_terms];
            }
        }
    }

    if (empty($terms)) {
        return [];
    }

    $items = [];

    foreach ($terms as $term) {
        $item = chasse_normalize_term_for_display($term, $taxonomy);

        if ($item !== null) {
            $items[] = $item;
        }
    }

    return $items;
}

/**
 * Format raw term items into HTML snippets for the meta-etiquette blocks.
 *
 * @param array<int, array<string, mixed>>|null $terms Raw terms as returned by chasse_preparer_termes_affichage.
 *
 * @return string[]
 */
function chasse_format_meta_terms($terms): array
{
    if (!is_array($terms) || $terms === []) {
        return [];
    }

    $escape_html = static function (string $value): string {
        if (function_exists('esc_html')) {
            return esc_html($value);
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    };

    $format_url = static function (string $url): string {
        if (function_exists('esc_url')) {
            return esc_url($url);
        }

        $sanitized = filter_var($url, FILTER_SANITIZE_URL);

        return is_string($sanitized) ? $sanitized : '';
    };

    $formatted = [];

    foreach ($terms as $term) {
        if (!is_array($term)) {
            continue;
        }

        $raw_name = $term['nom'] ?? ($term['name'] ?? '');
        $name     = trim((string) $raw_name);

        if ($name === '') {
            continue;
        }

        $raw_link = $term['lien'] ?? ($term['link'] ?? '');
        $link     = is_string($raw_link) ? trim($raw_link) : '';

        $escaped_name = $escape_html($name);

        if ($link !== '') {
            $escaped_link = $format_url($link);

            if ($escaped_link !== '') {
                $formatted[] = sprintf(
                    '<a class="meta-etiquette__value" href="%s">%s</a>',
                    $escaped_link,
                    $escaped_name
                );
                continue;
            }
        }

        $formatted[] = sprintf('<span class="meta-etiquette__value">%s</span>', $escaped_name);
    }

    return $formatted;
}

/**
 * Normalise une valeur de terme afin de la rendre exploitable pour l'affichage.
 *
 * @param mixed  $term     Valeur brute issue de WordPress ou d'ACF.
 * @param string $taxonomy Taxonomie ciblée.
 *
 * @return array{nom: string, slug: string, lien: string}|null
 */
function chasse_normalize_term_for_display($term, string $taxonomy): ?array
{
    $wp_term = chasse_resolve_term_candidate($term, $taxonomy);

    if ($wp_term instanceof \WP_Term) {
        $link = '';

        if (function_exists('get_term_link')) {
            $link = get_term_link($wp_term);
            if (is_wp_error($link)) {
                $link = '';
            }
        }

        return [
            'nom'  => $wp_term->name,
            'slug' => $wp_term->slug,
            'lien' => is_string($link) ? $link : '',
        ];
    }

    $name = '';
    $link = '';
    $slug_candidates = [];

    if (is_array($term)) {
        $link_candidates = [
            $term['lien'] ?? null,
            $term['link'] ?? null,
            $term['url'] ?? null,
        ];

        foreach ($link_candidates as $link_candidate) {
            if (!is_string($link_candidate)) {
                continue;
            }

            $trimmed = trim($link_candidate);

            if ($trimmed === '') {
                continue;
            }

            $link = $trimmed;
            break;
        }

        $name_candidates = [
            $term['nom'] ?? null,
            $term['name'] ?? null,
            $term['label'] ?? null,
            $term['title'] ?? null,
            $term['post_title'] ?? null,
            $term['display_name'] ?? null,
            $term['value'] ?? null,
        ];

        foreach ($name_candidates as $candidate) {
            if (!is_string($candidate) && !is_numeric($candidate)) {
                continue;
            }

            $candidate_value = trim((string) $candidate);

            if ($candidate_value === '') {
                continue;
            }

            if (ctype_digit($candidate_value)) {
                continue;
            }

            $taxonomy_pattern = '/^' . preg_quote($taxonomy, '/') . '[_:-]?\d+$/i';
            if (preg_match('/^term[_:-]?\d+$/i', $candidate_value)
                || preg_match('/^term\s+\d+$/i', $candidate_value)
                || preg_match('/^term\s*id\s*[:=]?\s*\d+$/i', $candidate_value)
                || preg_match($taxonomy_pattern, $candidate_value)
            ) {
                continue;
            }

            $name = $candidate_value;
            break;
        }

        $slug_candidates[] = $term['slug'] ?? null;
        if (isset($term['value']) && is_string($term['value'])) {
            $slug_candidates[] = $term['value'];
        }
    } elseif (is_string($term) || is_numeric($term)) {
        $name = trim((string) $term);
    }

    if ($name === '') {
        return null;
    }

    $slug_candidates[] = $name;

    $slug = '';

    foreach ($slug_candidates as $candidate) {
        if (!is_string($candidate) && !is_numeric($candidate)) {
            continue;
        }

        $candidate_value = trim((string) $candidate);

        if ($candidate_value === '') {
            continue;
        }

        if (function_exists('sanitize_title')) {
            $slug = sanitize_title($candidate_value);
        } else {
            $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $candidate_value));
            $slug = trim($slug, '-');
        }

        if ($slug !== '') {
            break;
        }
    }

    return [
        'nom'  => $name,
        'slug' => $slug,
        'lien' => $link,
    ];
}

/**
 * Extract a numeric term identifier from raw data returned by ACF or caches.
 *
 * @param mixed  $value    Raw candidate value.
 * @param string $taxonomy Related taxonomy slug.
 *
 * @return int|null
 */
function chasse_extract_term_id_from_value($value, string $taxonomy): ?int
{
    if (is_int($value)) {
        return $value;
    }

    if (is_float($value) || (is_numeric($value) && !is_string($value))) {
        return (int) $value;
    }

    if (!is_string($value)) {
        return null;
    }

    $trimmed = trim($value);

    if ($trimmed === '') {
        return null;
    }

    if (ctype_digit($trimmed)) {
        return (int) $trimmed;
    }

    $taxonomy_pattern = '/^' . preg_quote($taxonomy, '/') . '[_:-]?\d+$/i';
    $patterns = [
        '/^term[_:-]?(\d+)$/i',
        '/^term\s+(\d+)$/i',
        '/^term\s*id\s*[:=]?\s*(\d+)$/i',
        $taxonomy_pattern,
        '/^id[_:-]?(\d+)$/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $trimmed, $matches)) {
            return (int) $matches[1];
        }
    }

    return null;
}

/**
 * Tente de transformer une valeur brute en objet \WP_Term.
 *
 * @param mixed  $candidate Valeur récupérée via ACF ou le cache.
 * @param string $taxonomy  Taxonomie ciblée.
 *
 * @return \WP_Term|null
 */
function chasse_resolve_term_candidate($candidate, string $taxonomy): ?\WP_Term
{
    $taxonomy_candidates = chasse_resolve_taxonomy_aliases($taxonomy);

    $load_term_from_value = static function ($value) use ($taxonomy_candidates): ?\WP_Term {
        if (!function_exists('get_term')) {
            return null;
        }

        $term_id = null;

        foreach ($taxonomy_candidates as $taxonomy_candidate) {
            $term_id = chasse_extract_term_id_from_value($value, $taxonomy_candidate);

            if ($term_id !== null) {
                break;
            }
        }

        if ($term_id === null) {
            return null;
        }

        foreach ($taxonomy_candidates as $taxonomy_candidate) {
            $term = get_term($term_id, $taxonomy_candidate);

            if ($term instanceof \WP_Term) {
                return $term;
            }

            if (function_exists('is_wp_error') && is_wp_error($term)) {
                continue;
            }
        }

        $term = get_term($term_id);

        if ($term instanceof \WP_Term) {
            $term_taxonomy = property_exists($term, 'taxonomy') ? (string) $term->taxonomy : '';

            if ($term_taxonomy === '' || in_array($term_taxonomy, $taxonomy_candidates, true)) {
                return $term;
            }
        }

        return null;
    };

    if ($candidate instanceof \WP_Term) {
        return $candidate;
    }

    if (is_array($candidate) && isset($candidate['term'])) {
        $resolved = chasse_resolve_term_candidate($candidate['term'], $taxonomy);

        if ($resolved instanceof \WP_Term) {
            return $resolved;
        }
    }

    $term_from_root_candidate = $load_term_from_value($candidate);
    if ($term_from_root_candidate instanceof \WP_Term) {
        return $term_from_root_candidate;
    }

    if (is_array($candidate)) {
        $id_keys = ['term_id', 'termId', 'ID', 'id', 'value'];

        foreach ($id_keys as $key) {
            if (!isset($candidate[$key]) || !is_numeric($candidate[$key])) {
                continue;
            }

            $term = $load_term_from_value($candidate[$key]);

            if ($term instanceof \WP_Term) {
                return $term;
            }
        }

        $slug_keys = ['slug', 'value'];

        foreach ($slug_keys as $key) {
            if (!isset($candidate[$key]) || (!is_string($candidate[$key]) && !is_numeric($candidate[$key]))) {
                continue;
            }

            $term = $load_term_from_value($candidate[$key]);

            if ($term instanceof \WP_Term) {
                return $term;
            }

            if (function_exists('get_term_by')) {
                $slug = trim((string) $candidate[$key]);

                if ($slug !== '') {
                    foreach ($taxonomy_candidates as $taxonomy_candidate) {
                        $term = get_term_by('slug', $slug, $taxonomy_candidate);

                        if ($term instanceof \WP_Term) {
                            return $term;
                        }
                    }
                }
            }
        }

        $name_keys = ['nom', 'name', 'label', 'value', 'title', 'post_title', 'display_name'];

        foreach ($name_keys as $key) {
            if (!isset($candidate[$key]) || (!is_string($candidate[$key]) && !is_numeric($candidate[$key]))) {
                continue;
            }

            $term = $load_term_from_value($candidate[$key]);

            if ($term instanceof \WP_Term) {
                return $term;
            }

            if (function_exists('get_term_by')) {
                $name_candidate = trim((string) $candidate[$key]);

                if ($name_candidate === '') {
                    continue;
                }

                foreach ($taxonomy_candidates as $taxonomy_candidate) {
                    $term = get_term_by('name', $name_candidate, $taxonomy_candidate);

                    if ($term instanceof \WP_Term) {
                        return $term;
                    }
                }
            }
        }
    }

    if (is_string($candidate) && function_exists('get_term_by')) {
        $candidate = trim($candidate);

        if ($candidate !== '') {
            $term = $load_term_from_value($candidate);

            if ($term instanceof \WP_Term) {
                return $term;
            }

            foreach ($taxonomy_candidates as $taxonomy_candidate) {
                $term = get_term_by('slug', $candidate, $taxonomy_candidate);

                if ($term instanceof \WP_Term) {
                    return $term;
                }

                $term = get_term_by('name', $candidate, $taxonomy_candidate);

                if ($term instanceof \WP_Term) {
                    return $term;
                }
            }
        }
    }

    return null;
}

/**
 * Build a list of taxonomy aliases that may be used in the database.
 */
function chasse_resolve_taxonomy_aliases(string $taxonomy): array
{
    $candidates = [$taxonomy];

    $aliases = [
        'chasse_region' => ['chasse_regions', 'region', 'regions'],
        'theme_chasse'  => ['chasse_theme', 'theme_chasses', 'themes_chasse'],
    ];

    if (isset($aliases[$taxonomy])) {
        foreach ($aliases[$taxonomy] as $alias) {
            if (!in_array($alias, $candidates, true)) {
                $candidates[] = $alias;
            }
        }
    }

    return $candidates;
}

/**
 * Vérifie si un tableau est indexé numériquement en séquence.
 */
function chasse_is_list(array $array): bool
{
    if (function_exists('array_is_list')) {
        return array_is_list($array);
    }

    if ($array === []) {
        return true;
    }

    return array_keys($array) === range(0, count($array) - 1);
}

/**
 * Prépare les représentations courtes des dates d'une chasse.
 *
 * @param string|null $start_date Date de début brute.
 * @param string|null $end_date   Date de fin brute.
 * @param bool        $is_unlimited Indique si la chasse est illimitée.
 *
 * @return array{date_debut_court: string, date_fin_court: string}
 */
function chasse_preparer_dates_courtes(?string $start_date, ?string $end_date, bool $is_unlimited): array
{
    $start_value = $start_date !== null ? (string) $start_date : null;
    $end_value   = $end_date !== null ? (string) $end_date : null;

    $start_timestamp = false;
    if ($start_value !== null && $start_value !== '') {
        if (function_exists('convertir_en_timestamp')) {
            $start_timestamp = convertir_en_timestamp($start_value);
        } else {
            $start_timestamp = strtotime(str_replace('/', '-', $start_value));
        }
    }

    $end_timestamp = false;
    if (!$is_unlimited && $end_value !== null && $end_value !== '') {
        if (function_exists('convertir_en_timestamp')) {
            $end_timestamp = convertir_en_timestamp($end_value);
        } else {
            $end_timestamp = strtotime(str_replace('/', '-', $end_value));
        }
    }

    if (function_exists('_x')) {
        /* translators: Short date format for hunt metadata (day/month/year). */
        $short_date_format = _x('d/m/y', 'short date format for hunts', 'chassesautresor-com');
    } else {
        $short_date_format = 'd/m/y';
    }

    $non_specifiee_label = function_exists('__')
        ? __('Non spécifiée', 'chassesautresor-com')
        : 'Non spécifiée';
    $illimitee_label = function_exists('__')
        ? __('Illimitée', 'chassesautresor-com')
        : 'Illimitée';

    $start_short = $start_timestamp
        ? wp_date($short_date_format, $start_timestamp)
        : $non_specifiee_label;

    $end_short = $is_unlimited
        ? $illimitee_label
        : ($end_timestamp ? wp_date($short_date_format, $end_timestamp) : $non_specifiee_label);

    return [
        'date_debut_court' => $start_short,
        'date_fin_court'   => $end_short,
    ];
}

function preparer_infos_affichage_carte_chasse(int $chasse_id, int $word_limit = 300, array $options = []): array
{
    if (get_post_type($chasse_id) !== 'chasse') {
        return [];
    }

    $options = wp_parse_args(
        $options,
        [
            'badge_format' => 'text',
        ]
    );

    $titre     = get_the_title($chasse_id);
    $permalink = get_permalink($chasse_id);

    $description = get_field('chasse_principale_description', $chasse_id);
    $description = preg_replace('/^\s*Présentation\s*2\.1\s*/i', '', (string) $description);
    $texte_complet = wp_strip_all_tags($description);
    $extrait = wp_trim_words($texte_complet, $word_limit, '...');

    $image_data = get_field('chasse_principale_image', $chasse_id);
    $image_id = 0;
    $image = '';
    $image_width = 0;
    $image_height = 0;
    $image_size = 'medium_large';

    if (is_array($image_data)) {
        if (!empty($image_data['ID'])) {
            $image_id = (int) $image_data['ID'];
        } elseif (!empty($image_data['id'])) {
            $image_id = (int) $image_data['id'];
        }
    } elseif (!empty($image_data)) {
        $image_id = (int) $image_data;
    }

    if (!$image_id) {
        $image_id = get_post_thumbnail_id($chasse_id);
    }

    if ($image_id) {
        $preferred_sizes = ['medium_large', 'large', 'medium', 'full'];

        foreach ($preferred_sizes as $size_candidate) {
            $image_src = wp_get_attachment_image_src($image_id, $size_candidate);

            if (!is_array($image_src) || empty($image_src[0])) {
                continue;
            }

            $image = $image_src[0];
            $image_width = (int) $image_src[1];
            $image_height = (int) $image_src[2];
            $image_size = $size_candidate;

            break;
        }

        if ($image === '') {
            $image = wp_get_attachment_url($image_id) ?: '';
            $image_size = 'full';
        }
    } elseif (is_array($image_data) && !empty($image_data['url'])) {
        $image = (string) $image_data['url'];
    } elseif (is_string($image_data) && $image_data !== '') {
        $image = $image_data;
    }

    $image_ratio = '';
    $image_ratio_padding = '';

    $organisateur_id = get_organisateur_from_chasse($chasse_id);

    if ($image_width > 0 && $image_height > 0) {
        $image_ratio = $image_width . ' / ' . $image_height;

        $ratio_value = $image_width / $image_height;
        if ($ratio_value > 0) {
            $ratio_padding_value = 100 / $ratio_value;
            $image_ratio_padding = rtrim(rtrim(sprintf('%.6F', $ratio_padding_value), '0'), '.');
            if ($image_ratio_padding !== '') {
                $image_ratio_padding .= '%';
            }
        }
    }

    $champs = chasse_get_champs($chasse_id);
    $is_demo = !empty($champs['is_demo']);
    $demo_label = function_exists('__') ? __('Démo', 'chassesautresor-com') : 'Démo';
    $demo_aria_label = function_exists('__')
        ? __('Chasse de démonstration', 'chassesautresor-com')
        : 'Chasse de démonstration';
    $demo_title = function_exists('__')
        ? __('Cette chasse est proposée en mode démonstration.', 'chassesautresor-com')
        : 'Cette chasse est proposée en mode démonstration.';
    $demo_screen = function_exists('__')
        ? __('Chasse en mode démonstration', 'chassesautresor-com')
        : 'Chasse en mode démonstration';
    $demo_icon = function_exists('get_svg_icon') ? get_svg_icon('idea') : '';
    $demo_badge = [
        'label'       => $demo_label,
        'aria_label'  => $demo_aria_label,
        'icon_html'   => $demo_icon,
        'icon_name'   => 'idea',
        'title'       => $demo_title,
        'screen_text' => $demo_screen,
    ];
    $regions = chasse_preparer_termes_affichage($chasse_id, 'chasse_region');
    $themes = chasse_preparer_termes_affichage($chasse_id, 'theme_chasse');
    $region_principale = !empty($regions) ? $regions[0] : null;
    $titre_recompense  = $champs['titre_recompense'];
    $valeur_recompense = $champs['valeur_recompense'];
    $cout_points       = (int) $champs['cout_points'];
    $date_debut        = $champs['date_debut'];
    $date_fin          = $champs['date_fin'];
    $illimitee         = $champs['illimitee'];
    $date_decouverte   = $champs['date_decouverte'];

    verifier_ou_recalculer_statut_chasse($chasse_id);
    $statut            = get_field('chasse_cache_statut', $chasse_id) ?: 'revision';
    $statut_validation = get_field('chasse_cache_statut_validation', $chasse_id);

    if ($statut === 'termine' && $date_decouverte) {
        $date_fin = $date_decouverte;
    }

    $date_debut_affichage = formater_date($date_debut);
    $date_fin_affichage   = $illimitee
        ? __('Illimitée', 'chassesautresor-com')
        : ($date_fin ? formater_date($date_fin) : __('Non spécifiée', 'chassesautresor-com'));

    $dates_courtes    = chasse_preparer_dates_courtes($date_debut, $date_fin, (bool) $illimitee);
    $date_debut_court = $dates_courtes['date_debut_court'];
    $date_fin_court   = $dates_courtes['date_fin_court'];

    $nb_joueurs       = compter_joueurs_engages_chasse($chasse_id);
    $nb_joueurs_label = formater_nombre_joueurs($nb_joueurs);
    $badge_infos = chasse_preparer_badge_statut($statut, $statut_validation);
    $badge_format = ($options['badge_format'] === 'icon' && $badge_infos['icon_html']) ? 'icon' : 'text';
    $badge_class = $badge_infos['base_class'];
    $badge_tooltip = $badge_infos['label'];
    $badge_requires_interaction = ($badge_format === 'icon' && $badge_tooltip !== '');

    if ($badge_format === 'icon') {
        $badge_class .= ' badge-statut--format-icon';
    }

    $badge_content = $badge_format === 'icon'
        ? '<span class="badge-statut__icon" aria-hidden="true">' . $badge_infos['icon_html'] . '</span>'
            . '<span class="screen-reader-text">' . esc_html($badge_infos['label']) . '</span>'
        : esc_html($badge_infos['label']);

    $enigmes_associees = recuperer_enigmes_associees($chasse_id);
    $total_enigmes     = count($enigmes_associees);

    $user_id = get_current_user_id();
    $progression = chasse_calculer_progression_utilisateur($chasse_id, $user_id);
    $cta_data    = generer_cta_chasse($chasse_id, $user_id);

    $liens = get_field('chasse_principale_liens', $chasse_id);
    $liens = is_array($liens) ? $liens : [];
    if (empty($liens)) {
        $orga_id   = get_organisateur_from_chasse($chasse_id);
        $liens_org = organisateur_get_liens_actifs($orga_id);
        foreach ($liens_org as $type => $url) {
            $liens[] = [
                'chasse_principale_liens_type' => $type,
                'chasse_principale_liens_url'  => $url,
            ];
        }
    }
    $has_lien = false;
    foreach ($liens as $entree) {
        $type_raw = $entree['chasse_principale_liens_type'] ?? null;
        $url      = $entree['chasse_principale_liens_url'] ?? null;
        $type     = is_array($type_raw) ? ($type_raw[0] ?? '') : $type_raw;
        if (is_string($type) && trim($type) !== '' && is_string($url) && trim($url) !== '') {
            $has_lien = true;
            break;
        }
    }
    $liens_html = $has_lien ? render_liens_publics($liens, 'chasse') : '';

    $footer_icones = [];
    if ($cout_points > 0) {
        $footer_icones[] = 'coins-points';
    }

    $mode_validation = '';
    $modes          = [];
    $enigmes_validables = [];
    foreach ($enigmes_associees as $eid) {
        $mode = get_field('enigme_mode_validation', $eid);
        if ($mode) {
            $modes[$mode] = true;
            if ($mode !== 'aucune') {
                $enigmes_validables[] = (int) $eid;
            }
        }
    }
    if (isset($modes['manuelle'])) {
        $footer_icones[] = 'reply-mail';
        $mode_validation = 'manuelle';
    } elseif (isset($modes['automatique'])) {
        $footer_icones[] = 'reply-auto';
        $mode_validation = 'automatique';
    }

    $resolues_validables = 0;
    if (!empty($enigmes_validables) && !empty($progression['resolvables']) && $progression['resolvables'] > 0 && $user_id) {
        global $wpdb;
        $table        = $wpdb->prefix . 'enigme_statuts_utilisateur';
        $placeholders = implode(',', array_fill(0, count($enigmes_validables), '%d'));
        $sql          = "SELECT COUNT(DISTINCT enigme_id) FROM {$table} WHERE user_id = %d AND statut IN ('resolue','terminee','terminée') AND enigme_id IN ($placeholders)";
        $params       = array_merge([$user_id], $enigmes_validables);
        $resolues_validables = (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    $lot_html = '';
    if (!empty($titre_recompense) && (float) $valeur_recompense > 0) {
        $footer_icones[] = 'trophy';
        $lot_html       = '<div class="chasse-lot" aria-live="polite">'
            . '<span class="chasse-lot__icon">' . get_svg_icon('trophy') . '</span>'
            . '<span class="screen-reader-text">' . esc_html__('Récompense :', 'chassesautresor-com') . '</span>'
            . '<span class="badge-recompense avec-recompense">'
            . esc_html(number_format_i18n(round((float) $valeur_recompense), 0))
            . '<span class="badge-recompense__devise prix-devise">€</span>'
            . '</span>'
            . '<span class="chasse-lot__title">' . esc_html($titre_recompense) . '</span>'
            . '</div>';
    }


    $extrait_html = $extrait
        ? '<p class="chasse-intro-extrait liste-elegante">' . esc_html($extrait) . '</p>'
        : '';

    $cta_html    = $cta_data['cta_html'] ?? '';
    $cta_message = $cta_data['cta_message'] ?? '';

    $footer_liens_html = '';
    $footer_icones_html = '';

    if ($has_lien) {
        $footer_liens_html = '<div class="liens-publics-carte">' . $liens_html . '</div>';
    }

    if (!empty($footer_icones)) {
        $footer_icones_html = '<div class="footer-icones">';
        foreach ($footer_icones as $icn) {
            $footer_icones_html .= get_svg_icon($icn);
        }
        $footer_icones_html .= '</div>';
    }

    $footer_html = '';
    if ($footer_liens_html || $footer_icones_html) {
        $footer_html = '<div class="carte-ligne__footer meta-etiquette">'
            . $footer_icones_html
            . $footer_liens_html
            . '</div>';
    }


    $infos = [
        'titre'             => $titre,
        'permalink'         => $permalink,
        'image_id'          => $image_id,
        'image'             => $image,
        'image_ratio'       => $image_ratio,
        'image_ratio_padding' => $image_ratio_padding,
        'image_size'        => $image_size,
        'total_enigmes'     => $total_enigmes,
        'nb_joueurs'        => $nb_joueurs,
        'nb_joueurs_label'  => $nb_joueurs_label,
        'cout_points'       => $cout_points,
        'mode_validation'   => $mode_validation,
        'mode_fin'          => $champs['mode_fin'],
        'date_debut'        => $date_debut_affichage,
        'date_fin'          => $date_fin_affichage,
        'date_debut_court'  => $date_debut_court,
        'date_fin_court'    => $date_fin_court,
        'badge_class'       => trim($badge_class),
        'statut_label'      => $badge_infos['label'],
        'statut_icon'       => $badge_infos['icon_html'],
        'statut_icon_name'  => $badge_infos['icon_name'],
        'badge_format'      => $badge_format,
        'badge_content'     => $badge_content,
        'badge_tooltip'     => $badge_tooltip,
        'badge_requires_interaction' => $badge_requires_interaction,
        'classe_statut'     => $badge_infos['base_class'],
        'extrait_html'      => $extrait_html,
        'lot_html'          => $lot_html,
        'cta_html'          => $cta_html,
        'cta_message'       => $cta_message,
        'cta_type'         => $cta_data['type'] ?? '',
        'cta_is_demo'      => !empty($cta_data['is_demo']),
        'footer_html'       => $footer_html,
        'regions'           => $regions,
        'themes'            => $themes,
        'region_principale' => $region_principale,
        'organisateur_id'   => $organisateur_id,
        'is_demo'           => $is_demo,
        'demo_badge'        => $is_demo ? $demo_badge : null,
    ];

    if (!empty($progression['resolvables'])) {
        $resolvables_count = (int) $progression['resolvables'];
        $infos['progression'] = $progression;
        $infos['resolues_validables'] = min($resolues_validables, $resolvables_count);
    }

    return $infos;
}

/**
 * Prépare les informations complètes d'affichage pour une chasse.
 * Cette fonction centralise tous les appels ACF et fonctions métiers
 * afin d'éviter les appels répétés lors du rendu d'une page.
 *
 * @param int      $chasse_id ID de la chasse.
 * @param int|null $user_id   Utilisateur courant pour le CTA. Par défaut get_current_user_id().
 * @return array
 */
function preparer_infos_affichage_chasse(int $chasse_id, ?int $user_id = null): array
{
    static $memo = [];

    $user_id  = $user_id ?? get_current_user_id();
    $memo_key = $chasse_id . '-' . $user_id;

    if (get_post_type($chasse_id) !== 'chasse') {
        return [];
    }

    $current_demo_flag = ca_demo_is_demo_hunt($chasse_id);

    if (isset($memo[$memo_key])) {
        $memo_demo_flag = isset($memo[$memo_key]['is_demo'])
            ? (bool) $memo[$memo_key]['is_demo']
            : (bool) ($memo[$memo_key]['champs']['is_demo'] ?? false);

        if ($memo_demo_flag === $current_demo_flag) {
            return $memo[$memo_key];
        }

        unset($memo[$memo_key]);
    }

    $cache_key = chasse_infos_affichage_cache_key($chasse_id);
    $cache     = wp_cache_get($cache_key, 'chasse_affichage');
    if (!is_array($cache)) {
        $cache = get_transient($cache_key);
        $cache = is_array($cache) ? $cache : [];
    }

    if (isset($cache[$user_id])) {
        $cache_demo_flag = isset($cache[$user_id]['is_demo'])
            ? (bool) $cache[$user_id]['is_demo']
            : (bool) ($cache[$user_id]['champs']['is_demo'] ?? false);

        if ($cache_demo_flag === $current_demo_flag) {
            $memo[$memo_key] = $cache[$user_id];
            return $memo[$memo_key];
        }

        chasse_clear_infos_affichage_cache($chasse_id);
        $cache = [];
    }

    $champs = chasse_get_champs($chasse_id);
    $is_demo = !empty($champs['is_demo']);
    $demo_label = function_exists('__') ? __('Démo', 'chassesautresor-com') : 'Démo';
    $demo_aria_label = function_exists('__')
        ? __('Chasse de démonstration', 'chassesautresor-com')
        : 'Chasse de démonstration';
    $demo_title = function_exists('__')
        ? __('Cette chasse est proposée en mode démonstration.', 'chassesautresor-com')
        : 'Cette chasse est proposée en mode démonstration.';
    $demo_screen = function_exists('__')
        ? __('Chasse en mode démonstration', 'chassesautresor-com')
        : 'Chasse en mode démonstration';
    $demo_icon = function_exists('get_svg_icon') ? get_svg_icon('idea') : '';
    $demo_badge = [
        'label'       => $demo_label,
        'aria_label'  => $demo_aria_label,
        'icon_html'   => $demo_icon,
        'icon_name'   => 'idea',
        'title'       => $demo_title,
        'screen_text' => $demo_screen,
    ];
    $regions = chasse_preparer_termes_affichage($chasse_id, 'chasse_region');
    $themes = chasse_preparer_termes_affichage($chasse_id, 'theme_chasse');
    $region_principale = !empty($regions) ? $regions[0] : null;

    $raw_start_date   = $champs['date_debut'] ?? null;
    $raw_end_date     = $champs['date_fin'] ?? null;
    $raw_discovery    = $champs['date_decouverte'] ?? null;
    $is_unlimited     = !empty($champs['illimitee']);
    $status_value     = get_field('chasse_cache_statut', $chasse_id) ?: 'revision';
    $status_validation = get_field('chasse_cache_statut_validation', $chasse_id);

    if ($status_value === 'termine' && !empty($raw_discovery)) {
        $raw_end_date = $raw_discovery;
    }

    $start_date_value = null;
    if (is_string($raw_start_date) && $raw_start_date !== '') {
        $start_date_value = $raw_start_date;
    } elseif (is_numeric($raw_start_date)) {
        $start_date_value = (string) $raw_start_date;
    }

    $end_date_value = null;
    if (is_string($raw_end_date) && $raw_end_date !== '') {
        $end_date_value = $raw_end_date;
    } elseif (is_numeric($raw_end_date)) {
        $end_date_value = (string) $raw_end_date;
    }

    $dates_courtes = chasse_preparer_dates_courtes($start_date_value, $end_date_value, (bool) $is_unlimited);

    $description   = get_field('chasse_principale_description', $chasse_id);
    $texte_complet = wp_strip_all_tags($description);
    $extrait       = wp_trim_words($texte_complet, 60, '...');

    $image_raw = get_field('chasse_principale_image', $chasse_id);
    $image_id  = is_array($image_raw) ? ($image_raw['ID'] ?? null) : $image_raw;
    $image_url = $image_id ? wp_get_attachment_image_src($image_id, 'chasse-fiche')[0] : null;
    $image_alt = $image_id ? get_post_meta($image_id, '_wp_attachment_image_alt', true) : '';
    $image_alt = $image_alt !== '' ? $image_alt : get_the_title($chasse_id);

    $liens = get_field('chasse_principale_liens', $chasse_id);
    $liens = is_array($liens) ? $liens : [];

    $enigmes             = recuperer_enigmes_associees($chasse_id);
    $nb_enigmes_payantes = 0;
    foreach ($enigmes as $eid) {
        $cout = (int) get_field('enigme_tentative_cout_points', $eid);
        $mode = get_field('enigme_mode_validation', $eid);
        if ($cout > 0 && $mode !== 'aucune') {
            $nb_enigmes_payantes++;
        }
    }

    $progression = chasse_calculer_progression_utilisateur($chasse_id, $user_id);
    $cta_data    = generer_cta_chasse($chasse_id, $user_id);

    $nb_joueurs = compter_joueurs_engages_chasse($chasse_id);
    $top_nb      = 0;
    $top_enigmes = 0;
    if ($nb_joueurs > 0) {
        $participants = chasse_lister_participants($chasse_id, $nb_joueurs, 0, 'resolution', 'DESC');
        $top_enigmes  = $participants[0]['nb_resolues'] ?? 0;
        if ($top_enigmes > 0) {
            foreach ($participants as $p) {
                if ($p['nb_resolues'] === $top_enigmes) {
                    $top_nb++;
                } else {
                    break;
                }
            }
        }
    }

    $memo[$memo_key] = [
        'champs'              => $champs,
        'description'         => $description,
        'texte_complet'       => $texte_complet,
        'extrait'             => $extrait,
        'image_raw'           => $image_raw,
        'image_id'            => $image_id,
        'image_url'           => $image_url,
        'image_alt'           => $image_alt,
        'liens'               => $liens,
        'enigmes_associees'   => $enigmes,
        'total_enigmes'       => count($enigmes),
        'progression'         => $progression,
        'cta_data'            => $cta_data,
        'cta_type'            => $cta_data['type'] ?? '',
        'cta_is_demo'         => !empty($cta_data['is_demo']),
        'nb_joueurs'          => $nb_joueurs,
        'nb_enigmes_payantes' => $nb_enigmes_payantes,
        'top_avances'         => [
            'nb'      => $top_nb,
            'enigmes' => $top_enigmes,
        ],
        'statut'            => $status_value,
        'statut_validation' => $status_validation,
        'regions'           => $regions,
        'themes'            => $themes,
        'region_principale' => $region_principale,
        'date_debut_court'  => $dates_courtes['date_debut_court'],
        'date_fin_court'    => $dates_courtes['date_fin_court'],
        'is_demo'           => $is_demo,
        'demo_badge'        => $is_demo ? $demo_badge : null,
    ];

    $cache[$user_id] = $memo[$memo_key];
    $ttl             = HOUR_IN_SECONDS;
    wp_cache_set($cache_key, $cache, 'chasse_affichage', $ttl);
    set_transient($cache_key, $cache, $ttl);

    return $memo[$memo_key];
}

function chasse_infos_affichage_cache_key(int $chasse_id): string
{
    return "chasse_infos_affichage_{$chasse_id}";
}

function chasse_clear_infos_affichage_cache_for_organisateur(int $organisateur_id): void
{
    if ($organisateur_id <= 0) {
        return;
    }

    if (!function_exists('get_chasses_de_organisateur')) {
        return;
    }

    $query = get_chasses_de_organisateur($organisateur_id);
    $raw_ids = [];

    if (is_object($query) && isset($query->posts) && is_array($query->posts)) {
        $raw_ids = $query->posts;
    } elseif (is_array($query)) {
        $raw_ids = $query;
    }

    foreach ($raw_ids as $maybe_id) {
        $chasse_id = is_object($maybe_id)
            ? (int) ($maybe_id->ID ?? 0)
            : (int) $maybe_id;

        if ($chasse_id > 0) {
            chasse_clear_infos_affichage_cache($chasse_id);
        }
    }
}

function chasse_clear_infos_affichage_cache(int $chasse_id): void
{
    $key = chasse_infos_affichage_cache_key($chasse_id);
    wp_cache_delete($key, 'chasse_affichage');
    delete_transient($key);
}

function chasse_invalidate_infos_affichage_terms(
    int $object_id,
    $terms,
    $tt_ids,
    string $taxonomy,
    $append,
    $old_tt_ids
): void {
    if (!in_array($taxonomy, ['chasse_region', 'theme_chasse'], true)) {
        return;
    }

    if (get_post_type($object_id) !== 'chasse') {
        return;
    }

    chasse_clear_infos_affichage_cache((int) $object_id);
}

function chasse_invalidate_infos_affichage_cache(int $post_id, \WP_Post $post, bool $update): void
{
    if (wp_is_post_revision($post_id)) {
        return;
    }

    if ($post->post_type === 'chasse') {
        chasse_clear_infos_affichage_cache($post_id);
    } elseif ($post->post_type === 'enigme') {
        $chasse_id = get_field('chasse_associee', $post_id);
        if ($chasse_id) {
            chasse_clear_infos_affichage_cache((int) $chasse_id);
        }
    } elseif ($post->post_type === 'organisateur') {
        chasse_clear_infos_affichage_cache_for_organisateur($post_id);
    }
}

function chasse_acf_clear_infos_affichage_cache($post_id): void
{
    if (!is_numeric($post_id)) {
        return;
    }

    $post_id = (int) $post_id;
    $type = get_post_type($post_id);

    if ($type === 'chasse') {
        chasse_clear_infos_affichage_cache($post_id);
    } elseif ($type === 'organisateur') {
        chasse_clear_infos_affichage_cache_for_organisateur($post_id);
    }
}
add_action('acf/save_post', 'chasse_acf_clear_infos_affichage_cache', 20);
add_action('save_post', 'chasse_invalidate_infos_affichage_cache', 10, 3);
add_action('chasse_engagement_created', 'chasse_clear_infos_affichage_cache');
add_action('set_object_terms', 'chasse_invalidate_infos_affichage_terms', 10, 6);

function chasse_acf_handle_utilisateurs_associes($value, $post_id)
{
    if (is_numeric($post_id) && get_post_type((int) $post_id) === 'organisateur') {
        chasse_clear_infos_affichage_cache_for_organisateur((int) $post_id);
    }

    return $value;
}
add_filter('acf/update_value/name=utilisateurs_associes', 'chasse_acf_handle_utilisateurs_associes', 20, 2);
