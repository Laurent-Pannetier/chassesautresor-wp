<?php
defined('ABSPATH') || exit;


    // ==================================================
    // 📊 GESTION DES TENTATIVES UTILISATEUR
    // ==================================================
    // 🔹 inserer_tentative() → Insère une tentative dans la table personnalisée.
    // 🔹 get_tentative_by_uid() → Récupère une tentative par son identifiant UID.
    // 🔹 traiter_tentative_manuelle() → Effectue la validation/refus d'une tentative (une seule fois).
    // 🔹 recuperer_infos_tentative() → Renvoie toutes les données pour l'affichage d'une tentative.
    // 🔹 get_etat_tentative() → Retourne l'état logique d'une tentative selon son champ `resultat`.

    /**
     * Fonction générique pour insérer une tentative dans la table personnalisée.
     *
     * @param int $user_id
     * @param int $enigme_id
     * @param string $reponse
     * @param string $resultat Valeur par défaut : 'attente'.
     * @param int $points_utilises Points dépensés pour cette tentative.
     * @return string UID unique généré pour cette tentative.
     */
    function inserer_tentative($user_id, $enigme_id, $reponse, $resultat = 'attente', $points_utilises = 0): string
    {
        global $wpdb;
        $table = $wpdb->prefix . 'enigme_tentatives';
        $uid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('tent_', true);

        $inserted = $wpdb->insert($table, [
            'tentative_uid'   => $uid,
            'user_id'         => $user_id,
            'enigme_id'       => $enigme_id,
            'reponse_saisie'  => $reponse,
            'resultat'        => $resultat,
            'points_utilises' => $points_utilises,
            'ip'              => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
        if ($inserted !== false) {
            do_action('enigme_tentative_created', $enigme_id);
        }

        return $uid;
    }

    /**
     * Récupère une tentative par son UID.
     */
    function get_tentative_by_uid(string $uid): ?object
    {
        global $wpdb;
        $table = $wpdb->prefix . 'enigme_tentatives';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE tentative_uid = %s", $uid));
    }

    /**
     * Check if the current user can view the proposition linked to a tentative.
     */
    function ca_user_can_view_tentative_proposition(object $tentative): bool
    {
        $current_user_id = (int) get_current_user_id();
        if ($current_user_id <= 0) {
            return false;
        }

        if ((int) ($tentative->user_id ?? 0) === $current_user_id) {
            return true;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $enigme_id = isset($tentative->enigme_id) ? (int) $tentative->enigme_id : 0;
        if ($enigme_id <= 0) {
            return false;
        }

        $chasse_id = function_exists('recuperer_id_chasse_associee') ? (int) recuperer_id_chasse_associee($enigme_id) : 0;
        if ($chasse_id <= 0) {
            return false;
        }

        if (
            function_exists('utilisateur_est_organisateur_associe_a_chasse')
            && utilisateur_est_organisateur_associe_a_chasse($current_user_id, $chasse_id)
        ) {
            return true;
        }

        return false;
    }

    /**
     * AJAX handler to safely reveal a tentative proposition.
     */
    function ca_ajax_view_tentative_proposition(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Unauthorized', 'chassesautresor-com')], 403);
        }

        $uid = isset($_POST['uid']) ? sanitize_text_field(wp_unslash((string) $_POST['uid'])) : '';
        if ($uid === '') {
            wp_send_json_error(['message' => __('Identifiant de tentative invalide.', 'chassesautresor-com')], 400);
        }

        $nonce_valid = true;
        if (function_exists('check_ajax_referer')) {
            $nonce_valid = check_ajax_referer('ca_view_tentative_' . $uid, 'nonce', false);
        }

        if ($nonce_valid === false) {
            wp_send_json_error(['message' => __('Vérification de sécurité échouée.', 'chassesautresor-com')], 403);
        }

        $tentative = get_tentative_by_uid($uid);
        if (!$tentative) {
            wp_send_json_error(['message' => __('Tentative introuvable.', 'chassesautresor-com')], 404);
        }

        if (!ca_user_can_view_tentative_proposition($tentative)) {
            wp_send_json_error(['message' => __('Unauthorized', 'chassesautresor-com')], 403);
        }

        $proposition = isset($tentative->reponse_saisie) ? (string) $tentative->reponse_saisie : '';

        wp_send_json_success([
            'proposition' => $proposition,
        ]);
    }
    add_action('wp_ajax_ca_view_tentative_proposition', 'ca_ajax_view_tentative_proposition');
    add_action('wp_ajax_nopriv_ca_view_tentative_proposition', 'ca_ajax_view_tentative_proposition');


    /**
     * Traite une tentative manuelle : effectue l'action (validation/refus) une seule fois.
     *
     * @param string $uid Identifiant unique de la tentative.
     * @param string $resultat 'bon' ou 'faux'.
     * @return bool true si traitement effectué, false si déjà traité ou interdit.
     */
    function traiter_tentative_manuelle(string $uid, string $resultat): bool

    {
        global $wpdb;
        $table = $wpdb->prefix . 'enigme_tentatives';


        cat_debug("👣 Tentative traitement UID=$uid par IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'inconnue'));

        $tentative = get_tentative_by_uid($uid);
        if (!$tentative) {
            cat_debug("❌ Tentative introuvable");
            return false;
        }

        if ($tentative->resultat !== 'attente') {
            cat_debug("⛔ Tentative déjà traitée → statut actuel = " . $tentative->resultat);
            return false;
        }


        $user_id = (int) $tentative->user_id;
        $enigme_id = (int) $tentative->enigme_id;

        // 🔐 Sécurité : si déjà "resolue", on refuse toute tentative de traitement
        $statut_user = $wpdb->get_var($wpdb->prepare(
            "SELECT statut FROM {$wpdb->prefix}enigme_statuts_utilisateur WHERE user_id = %d AND enigme_id = %d",
            $user_id,
            $enigme_id
        ));

        if ($statut_user === 'resolue') {
            cat_debug("⛔ Statut utilisateur déjà 'resolue' → refus de traitement UID=$uid");
            return false;
        }

        // 🔐 Vérification organisateur ou admin
        $current_user_id = get_current_user_id();
        $chasse_id = recuperer_id_chasse_associee($enigme_id);
        $organisateur_id = get_organisateur_from_chasse($chasse_id);
        $organisateur_user_ids = (array) get_field('utilisateurs_associes', $organisateur_id);

        if (
            !current_user_can('manage_options') &&
            !in_array($current_user_id, array_map('intval', $organisateur_user_ids), true)
        ) {
            cat_debug("⛔ Accès interdit au traitement pour UID=$uid");
            return false;
        }

        // ✅ Mise à jour
        $wpdb->update(
            $table,
            ['resultat' => $resultat, 'traitee' => 1],
            ['tentative_uid' => $uid],
            ['%s', '%d'],
            ['%s']
        );

        traiter_tentative($user_id, $enigme_id, (string) $tentative->reponse_saisie, $resultat, false, true, true);

        $titre_enigme = get_the_title($enigme_id);
        $message      = sprintf(
            $resultat === 'bon'
                ? __(
                    'Votre demande de résolution de l\'énigme %1$s%2$s%3$s a été validée. Félicitations !',
                    'chassesautresor-com'
                )
                : __(
                    'Votre demande de résolution de l\'énigme %1$s%2$s%3$s a été invalidée.',
                    'chassesautresor-com'
                ),
            '<a href="' . esc_url(get_permalink($enigme_id)) . '">',
            esc_html($titre_enigme),
            '</a>'
        );
        myaccount_remove_persistent_message($user_id, 'tentative_' . $uid);

        $chasse_id       = recuperer_id_chasse_associee($enigme_id);
        $organisateur_id = get_organisateur_from_chasse($chasse_id);
        $orga_users      = (array) get_field('utilisateurs_associes', $organisateur_id);
        foreach ($orga_users as $orga_user_id) {
            myaccount_remove_persistent_message((int) $orga_user_id, 'tentative_' . $uid);
        }

        myaccount_add_flash_message(
            $user_id,
            $message,
            $resultat === 'bon' ? 'success' : 'error'
        );

        cat_debug("✅ Tentative UID=$uid traitée comme $resultat");
        return true;
    }


    /**
     * Renvoie toutes les données d'affichage pour une tentative (état, utilisateur, statut, etc.)
     *
     * @param string $uid Identifiant unique de la tentative.
     * @return array
     */
    /**
     * Récupère toutes les informations nécessaires à l'affichage d'une tentative.
     *
     * @param string $uid UID unique de la tentative.
     * @return array Données enrichies : statut, nom, etc.
     */
    function recuperer_infos_tentative(string $uid): array
    {
        $tentative = get_tentative_by_uid($uid);
        if (!$tentative) {
            return ['etat_tentative' => 'inexistante'];
        }

        $etat_tentative = get_etat_tentative($uid); // logique métier (attente/validee/refusee)
        $resultat = $tentative->resultat ?? '';
        $traitee = (int) ($tentative->traitee ?? 0) === 1;

        $user = get_userdata($tentative->user_id);
        $nom_user = ($user && isset($user->display_name)) ? $user->display_name : 'Utilisateur inconnu';

        return [
            'etat_tentative'        => $etat_tentative,
            'statut_initial'        => $resultat ?: 'invalide',
            'statut_final'          => $resultat,
            'resultat'              => $resultat,
            'deja_traitee'          => ($etat_tentative !== 'attente'),
            'traitee'               => $traitee,
            'vient_d_etre_traitee'  => $traitee && $etat_tentative !== 'attente',
            'tentative'             => $tentative,
            'nom_user'              => $nom_user,
            'permalink'             => get_permalink($tentative->enigme_id),
            'statistiques'          => [
                'total_user'   => 0,
                'total_enigme' => 0,
                'total_chasse' => 0,
            ],
        ];
    }


    /**
     * Retourne l'état logique d'une tentative selon son champ `resultat`.
     *
     * @param string $uid
     * @return string 'attente' | 'validee' | 'refusee' | 'invalide' | 'inexistante'
     */
function get_etat_tentative(string $uid): string
    {
        global $wpdb;
        $table = $wpdb->prefix . 'enigme_tentatives';
        $resultat = $wpdb->get_var($wpdb->prepare("SELECT resultat FROM $table WHERE tentative_uid = %s", $uid));

        if ($resultat === null) return 'inexistante';
        if ($resultat === 'attente') return 'attente';
        if ($resultat === 'bon') return 'validee';
        if ($resultat === 'faux') return 'refusee';

        return 'invalide';
}

/**
 * Récupère les tentatives enregistrées pour une énigme.
 *
 * @param int $enigme_id ID de l'énigme.
 * @param int $limit     Nombre de résultats à retourner.
 * @param int $offset    Décalage pour la pagination.
 * @return array         Liste des tentatives triées par priorité manuelle puis
 *                       par date de soumission décroissante.
 */
function recuperer_tentatives_enigme(int $enigme_id, int $limit = 5, int $offset = 0): array
{
    global $wpdb;
    $table = $wpdb->prefix . 'enigme_tentatives';
    $query = $wpdb->prepare(
        "SELECT * FROM $table WHERE enigme_id = %d ORDER BY (resultat = 'attente') DESC, date_tentative DESC LIMIT %d OFFSET %d",
        $enigme_id,
        $limit,
        $offset
    );
    $res = $wpdb->get_results($query);
    return $res ?: [];
}

/**
 * Compte le nombre total de tentatives pour une énigme.
 *
 * @param int $enigme_id ID de l'énigme.
 * @return int Nombre de tentatives.
 */
function compter_tentatives_enigme(int $enigme_id): int
{
    global $wpdb;
    $table = $wpdb->prefix . 'enigme_tentatives';
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE enigme_id = %d", $enigme_id));
}

/**
 * Retourne la liste HTML des tentatives pour une page donnée via AJAX.
 *
 * @hook wp_ajax_lister_tentatives_enigme
 */
add_action('wp_ajax_lister_tentatives_enigme', 'ajax_lister_tentatives_enigme');

function ajax_lister_tentatives_enigme()
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $enigme_id = isset($_POST['enigme_id']) ? (int) $_POST['enigme_id'] : 0;
    $page      = max(1, (int) ($_POST['page'] ?? 1));
    $par_page  = 20;

    if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') {
        wp_send_json_error('post_invalide');
    }

    if (!utilisateur_peut_modifier_post($enigme_id)) {
        wp_send_json_error('acces_refuse');
    }

    $offset     = ($page - 1) * $par_page;
    $tentatives = recuperer_tentatives_enigme($enigme_id, $par_page, $offset);
    $total      = compter_tentatives_enigme($enigme_id);
    $pages      = (int) ceil($total / $par_page);

    ob_start();
    get_template_part('template-parts/enigme/partials/enigme-partial-tentatives', null, [
        'tentatives' => $tentatives,
        'page'       => $page,
        'par_page'   => $par_page,
        'total'      => $total,
        'pages'      => $pages,
    ]);
    $html = ob_get_clean();

    wp_send_json_success([
        'html'  => $html,
        'total' => $total,
        'page'  => $page,
        'pages' => $pages,
    ]);
}

/**
 * Compte le nombre de tentatives en attente de traitement pour une énigme.
 *
 * @param int $enigme_id ID de l'énigme.
 * @return int Nombre de tentatives non traitées.
 */
function compter_tentatives_en_attente(int $enigme_id): int
{
    global $wpdb;
    $table = $wpdb->prefix . 'enigme_tentatives';
    $query = $wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE enigme_id = %d AND resultat = 'attente' AND traitee = 0",
        $enigme_id
    );
    return (int) $wpdb->get_var($query);
}

/**
 * Récupère les énigmes ayant des tentatives manuelles en attente pour un organisateur.
 *
 * @param int $organisateur_id ID du CPT organisateur.
 * @return array Liste d'IDs d'énigmes.
 */
function recuperer_enigmes_tentatives_en_attente(int $organisateur_id): array
{
    if ($organisateur_id <= 0) {
        return [];
    }

    $query = get_chasses_de_organisateur($organisateur_id);
    if (empty($query->posts)) {
        return [];
    }

    $result = [];

    foreach ($query->posts as $chasse_id) {
        $enigmes = recuperer_enigmes_associees((int) $chasse_id);
        foreach ($enigmes as $enigme_id) {
            $mode = enigme_normaliser_mode_validation(
                get_field('enigme_mode_validation', $enigme_id)
            );

            if ($mode === 'manuelle' && compter_tentatives_en_attente($enigme_id) > 0) {
                $result[] = $enigme_id;
            }
        }
    }

    return array_values(array_unique($result));
}

/**
 * Compte le nombre de tentatives effectuées par un utilisateur pour une énigme
 * durant la journée courante (heure de Paris).
 */
function compter_tentatives_du_jour(int $user_id, int $enigme_id): int
{
    global $wpdb;
    $table = $wpdb->prefix . 'enigme_tentatives';

    $tz = new DateTimeZone('Europe/Paris');
    $debut = (new DateTime('now', $tz))->setTime(0, 0)->format('Y-m-d H:i:s');
    $fin   = (new DateTime('now', $tz))->setTime(23, 59, 59)->format('Y-m-d H:i:s');

    $query = $wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE user_id = %d AND enigme_id = %d AND date_tentative BETWEEN %s AND %s",
        $user_id,
        $enigme_id,
        $debut,
        $fin
    );

    return (int) $wpdb->get_var($query);
}

/**
 * Traite une tentative d'énigme : déduction des points, enregistrement et
 * mise à jour du statut utilisateur.
 *
 * @param bool $email_echec  Envoyer un email même en cas d'échec
 * @param bool $envoyer_mail Envoyer les notifications de résultat
 * @return string UID de la tentative enregistrée (vide si $inserer = false)
 */
function traiter_tentative(
    int $user_id,
    int $enigme_id,
    string $reponse,
    string $resultat,
    bool $inserer = true,
    bool $email_echec = false,
    bool $envoyer_mail = true
): string
{
    global $wpdb;
    $table = $wpdb->prefix . 'enigme_tentatives';

    if ($resultat === 'bon' && $inserer) {
        $existe = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND enigme_id = %d AND resultat = 'bon'",
                $user_id,
                $enigme_id
            )
        );

        if ($existe > 0) {
            return '';
        }
    }

    $cout = (int) get_field('enigme_tentative_cout_points', $enigme_id);
    if ($cout > 0 && $inserer) {
        $reason = sprintf("Tentative de réponse pour l'énigme #%d", $enigme_id);
        deduire_points_utilisateur($user_id, $cout, $reason, 'tentative', $enigme_id);
    }

    $uid = '';
    if ($inserer) {
        $uid = inserer_tentative($user_id, $enigme_id, $reponse, $resultat, $cout);
    }

    if ($resultat === 'bon') {
        enigme_mettre_a_jour_statut_utilisateur($enigme_id, $user_id, 'resolue');
        do_action('enigme_resolue', $user_id, $enigme_id);
    } elseif ($resultat === 'faux') {
        enigme_mettre_a_jour_statut_utilisateur($enigme_id, $user_id, 'echouee');
    } else {
        enigme_mettre_a_jour_statut_utilisateur($enigme_id, $user_id, 'en_cours');
    }

    if ($envoyer_mail && ($resultat === 'bon' || ($email_echec && $resultat === 'faux'))) {
        envoyer_mail_resultat_joueur($user_id, $enigme_id, $resultat);
    }

    return $uid;
}
