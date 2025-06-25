<?php

// 🚀 Empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

//
// 🧩 GESTION DES STATUTS ET DE L’ACCESSIBILITÉ DES ÉNIGMES
// 🧠 GESTION DES STATUTS DES CHASSES
// 🧭 CALCUL DU STATUT D’UN ORGANISATEUR
// 🧑‍💻 GESTION DES STATUTS DES JOUEURS (UTILISATEUR ↔ ÉNIGME)
//

// ==================================================
// 🧩 GESTION DES STATUTS ET DE L’ACCESSIBILITÉ DES ÉNIGMES
// ==================================================
/**
 * 
 * 🔹 enigme_get_statut_utilisateur        → Retourne le statut actuel de l’utilisateur pour une énigme.
 * 🔹 enigme_mettre_a_jour_statut_utilisateur() → Met à jour le statut d'un joueur dans la table personnalisée.
 * 🔹 enigme_pre_requis_remplis            → Vérifie les prérequis d’une énigme pour un utilisateur.
 * 🔹 enigme_verifier_verrouillage         → Détaille le verrouillage éventuel d’une énigme.
 * 🔹 traiter_statut_enigme                → Détermine le comportement global à adopter (formulaire, redirection…).
 * 🔹 enigme_est_visible_pour              → Vérifie si un utilisateur peut voir une énigme.
 * 🔹 mettre_a_jour_statuts_enigmes_de_la_chasse → Recalcule tous les statuts des énigmes liées à une chasse.
 * 🔹 enigme_mettre_a_jour_etat_systeme    → Calcule ou met à jour le champ `enigme_cache_etat_systeme`.
 * 🔹 enigme_mettre_a_jour_etat_systeme_automatiquement → Hook ACF (enregistrement admin ou front).
 * 🔹 forcer_recalcul_statut_enigme        → Recalcul AJAX côté front (édition directe).
 * 🔹 enigme_get_etat_systeme              → Retourne l’état système de l’énigme (champ ACF cache).
 * 🔹 utilisateur_peut_engager_enigme      → Vérifie si un joueur peut engager une énigme.
 */

/**
 * Récupère le statut actuel de l’utilisateur pour une énigme.
 *
 * Statuts possibles :
 * - non_souscrite : le joueur n'a jamais interagi avec l’énigme
 * - en_cours      : le joueur a commencé l’énigme
 * - resolue       : le joueur a trouvé la bonne réponse
 * - terminee      : l’énigme a été finalisée dans un autre contexte
 * - echouee       : le joueur a tenté et échoué
 * - abandonnee    : le joueur a abandonné explicitement ou par expiration
 *
 * @param int $enigme_id ID de l’énigme.
 * @param int $user_id   ID de l’utilisateur.
 * @return string Statut actuel (par défaut : 'non_souscrite').
 */
function enigme_get_statut_utilisateur(int $enigme_id, int $user_id): string
{
    if (!$enigme_id || !$user_id) {
        return 'non_commencee';
    }

    global $wpdb;
    $table = $wpdb->prefix . 'enigme_statuts_utilisateur';

    $statut = $wpdb->get_var($wpdb->prepare(
        "SELECT statut FROM $table WHERE user_id = %d AND enigme_id = %d",
        $user_id,
        $enigme_id
    ));

    return $statut ?: 'non_commencee';
}


/**
 * Met à jour le statut d'un joueur pour une énigme dans la table personnalisée `wp_enigme_statuts_utilisateur`.
 * La mise à jour ne s'effectue que si le nouveau statut est plus avancé que l'ancien.
 *
 * @param int $enigme_id ID de l'énigme.
 * @param int $user_id   ID de l'utilisateur.
 * @param string $nouveau_statut Nouveau statut ('non_commencee', 'en_cours', 'abandonnee', 'echouee', 'resolue', 'terminee').
 * @return bool True si la mise à jour est faite, false sinon.
 */
function enigme_mettre_a_jour_statut_utilisateur(int $enigme_id, int $user_id, string $nouveau_statut, bool $forcer = false): bool
{
    if (!$enigme_id || !$user_id || !$nouveau_statut) {
        return false;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'enigme_statuts_utilisateur';

    $priorites = [
        'non_commencee' => 0,
        'soumis'        => 1,
        'en_cours'      => 2,
        'abandonnee'    => 3,
        'echouee'       => 4,
        'resolue'       => 5,
        'terminee'      => 6,
    ];

    if (!isset($priorites[$nouveau_statut])) {
        cat_debug("❌ Statut utilisateur invalide : $nouveau_statut");
        return false;
    }

    $statut_actuel = $wpdb->get_var($wpdb->prepare(
        "SELECT statut FROM $table WHERE user_id = %d AND enigme_id = %d",
        $user_id,
        $enigme_id
    ));

    // Protection : interdiction de rétrograder un joueur ayant déjà résolu l’énigme
    if (!$forcer && in_array($statut_actuel, ['resolue', 'terminee'], true)) {
        cat_debug("🔒 Statut non modifié : $statut_actuel → tentative de mise à jour vers $nouveau_statut bloquée (UID: $user_id / Enigme: $enigme_id)");
        return false;
    }

    $niveau_actuel  = $priorites[$statut_actuel] ?? 0;
    $niveau_nouveau = $priorites[$nouveau_statut];

    if (!$forcer && $niveau_nouveau <= $niveau_actuel) {
        return false;
    }

    $data = [
        'statut'            => $nouveau_statut,
        'date_mise_a_jour'  => current_time('mysql'),
    ];

    $where = [
        'user_id'   => $user_id,
        'enigme_id' => $enigme_id,
    ];

    if ($statut_actuel !== null) {
        $wpdb->update($table, $data, $where, ['%s', '%s'], ['%d', '%d']);
    } else {
        $wpdb->insert($table, array_merge($where, $data), ['%d', '%d', '%s', '%s']);
    }

    return true;
}



/**
 * 🔍 Vérifie si les prérequis d'une énigme sont remplis pour un utilisateur donné.
 *
 * @param int $enigme_id ID de l'énigme à vérifier.
 * @param int $user_id   ID de l'utilisateur.
 * @return bool True si tous les prérequis sont remplis ou inexistants, false sinon.
 */
function enigme_pre_requis_remplis(int $enigme_id, int $user_id): bool
{
    $pre_requis = get_field('enigme_acces_pre_requis', $enigme_id);

    if (empty($pre_requis) || !is_array($pre_requis)) {
        return true; // ✅ Aucun prérequis → considéré comme rempli
    }

    foreach ($pre_requis as $enigme_requise) {
        $enigme_id_requise = is_object($enigme_requise) ? $enigme_requise->ID : (is_numeric($enigme_requise) ? (int)$enigme_requise : null);

        if ($enigme_id_requise) {
            $statut = get_user_meta($user_id, "statut_enigme_{$enigme_id_requise}", true);
            // Les statuts d'énigme sont stockés sans accent ("terminee")
            // dans les autres parties du code. Utiliser la même valeur ici
            // pour éviter un échec de vérification systématique des
            // prérequis lorsque l'utilisateur a pourtant terminé l'énigme.
            if ($statut !== 'terminee') {
                return false; // ❌ Prérequis non rempli
            }
        }
    }

    return true; // ✅ Tous les prérequis sont remplis
}

/**
 * ✅ Vérifie si l’énigme est verrouillée et retourne le motif.
 *
 * @param int $enigme_id ID de l'énigme.
 * @param int $user_id ID de l'utilisateur.
 * @return array Résultat avec :
 *  - 'est_verrouillee' (bool) : Statut de verrouillage.
 *  - 'motif' (string) : Raison du verrouillage.
 *  - 'date_deblocage' (string|null) : Date formatée si future.
 *  - 'timestamp_restant' (int|null) : Secondes restantes si applicable.
 *  - 'cout_points' (int|null) : Coût en points si concerné.
 *  - 'message_variante' (string|null) : Message en cas de variante.
 */
function enigme_verifier_verrouillage(int $enigme_id, int $user_id): array
{
    $resultat = [
        'est_verrouillee'   => false,
        'motif'             => 'aucun',
        'date_deblocage'    => null,
        'timestamp_restant' => null,
        'cout_points'       => null,
        'message_variante'  => null,
    ];

    if ($user_id === 0) {
        return array_merge($resultat, [
            'est_verrouillee' => true,
            'motif'           => 'utilisateur_non_connecte',
        ]);
    }

    $statut = enigme_get_statut_utilisateur($enigme_id, $user_id);

    switch ($statut) {
        case 'bloquee_date':
            $date_str = get_field('enigme_acces')['enigme_acces_date'] ?? null;
            if ($date_str) {
                $date_obj = convertir_en_datetime($date_str);
                if ($date_obj && $date_obj->getTimestamp() > time()) {
                    return array_merge($resultat, [
                        'est_verrouillee'   => true,
                        'motif'             => 'date_future',
                        'date_deblocage'    => $date_obj->format('d/m/Y à H\hi'),
                        'timestamp_restant' => $date_obj->getTimestamp() - time(),
                    ]);
                }
            }
            return array_merge($resultat, [
                'est_verrouillee' => true,
                'motif'           => 'date_non_definie',
            ]);

        case 'bloquee_pre_requis':
            return array_merge($resultat, [
                'est_verrouillee' => true,
                'motif'           => 'pre_requis',
            ]);

        case 'bloquee_chasse':
            return array_merge($resultat, [
                'est_verrouillee' => true,
                'motif'           => 'chasse_indisponible',
            ]);

        case 'non_souscrite':
            return array_merge($resultat, [
                'est_verrouillee' => true,
                'motif'           => 'non_souscrit',
            ]);

        case 'invalide':
            return array_merge($resultat, [
                'est_verrouillee' => true,
                'motif'           => 'erreur_configuration',
            ]);

        default:
            return $resultat;
    }
}



/**
 * Analyse le statut d’une énigme pour un utilisateur et détermine le comportement à adopter :
 * - redirection
 * - affichage ou non du formulaire
 * - affichage d’un message explicatif
 *
 * @param int $enigme_id
 * @param int|null $user_id
 *
 * @return array{
 *   etat: string,
 *   rediriger: bool,
 *   url: string|null,
 *   afficher_formulaire: bool,
 *   afficher_message: bool,
 *   message_html: string
 * }
 */
function traiter_statut_enigme(int $enigme_id, ?int $user_id = null): array
{
    $user_id = $user_id ?: get_current_user_id();
    $statut = enigme_get_statut_utilisateur($enigme_id, $user_id);
    $chasse_id = recuperer_id_chasse_associee($enigme_id);

    // 🔓 Bypass total : admin ou organisateur
    // 🛡️ Organisateur ou admin : pas de réponse possible
    if (
        current_user_can('manage_options') ||
        utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)
    ) {
        return [
            'etat' => $statut,
            'rediriger' => false,
            'url' => null,
            'afficher_formulaire' => false,
            'afficher_message' => false,
            'message_html' => '',
        ];
    }

    // ✅ Chasse terminée = accès libre à toutes les énigmes
    $cache_chasse = get_field('champs_caches', $chasse_id);
    $statut_chasse = $cache_chasse['chasse_cache_statut'] ?? null;
    if ($statut_chasse === 'termine') {
        return [
            'etat' => 'terminee',
            'rediriger' => false,
            'url' => null,
            'afficher_formulaire' => true,
            'afficher_message' => false,
            'message_html' => '',
        ];
    }

    // 🔁 Cas interdits : accès refusé
    if (in_array($statut, ['echouee', 'abandonnee'], true)) {
        return [
            'etat' => $statut,
            'rediriger' => true,
            'url' => $chasse_id ? get_permalink($chasse_id) : home_url('/'),
            'afficher_formulaire' => false,
            'afficher_message' => false,
            'message_html' => '',
        ];
    }

    // 🔁 Cas bloqués structurellement (pré-requis, date, etc.)
    if (in_array($statut, ['bloquee_date', 'bloquee_chasse', 'bloquee_pre_requis', 'invalide', 'cache_invalide'], true)) {
        return [
            'etat' => $statut,
            'rediriger' => true,
            'url' => $chasse_id ? get_permalink($chasse_id) : home_url('/'),
            'afficher_formulaire' => false,
            'afficher_message' => false,
            'message_html' => '',
        ];
    }

    // 🎯 Cas d'accès légitime (en cours, non_souscrite, resolue)
    $formulaire = in_array($statut, ['en_cours', 'non_souscrite'], true);
    $message = ($statut === 'resolue');
    $message_html = $message ? '<p class="message-statut">Vous avez déjà résolu cette énigme.</p>' : '';

    return [
        'etat' => $statut,
        'rediriger' => false,
        'url' => null,
        'afficher_formulaire' => $formulaire,
        'afficher_message' => $message,
        'message_html' => $message_html,
    ];
}



/**
 * Vérifie si un utilisateur est autorisé à afficher une énigme.
 * Utilise la logique de `traiter_statut_enigme()` pour autoriser ou refuser l’accès.
 *
 * @param int $user_id ID de l’utilisateur
 * @param int $enigme_id ID de l’énigme
 * @return bool True si l’énigme est visible pour cet utilisateur
 */
function enigme_est_visible_pour(int $user_id, int $enigme_id): bool
{
    $data = traiter_statut_enigme($enigme_id, $user_id);
    return !$data['rediriger'];
}



/**
 * 🔁 Recalcule le statut système de toutes les énigmes liées à une chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @return void
 */
function mettre_a_jour_statuts_enigmes_de_la_chasse(int $chasse_id): void
{

    if (get_post_type($chasse_id) !== 'chasse') return;
    $ids_enigmes = recuperer_enigmes_associees($chasse_id);
    foreach ($ids_enigmes as $enigme_id) {
        if (get_post_type($enigme_id) === 'enigme') {
            $resultat = enigme_mettre_a_jour_etat_systeme((int)$enigme_id);
        }
    }
}




/**
 * 🔁 Calcule ou met à jour le champ `enigme_cache_etat_systeme` d'une énigme.
 *
 * Ce champ reflète l'état global de l'énigme (accessible, bloquée, invalide...),
 * en tenant compte du statut de la chasse, de la condition d'accès et des réglages internes.
 *
 * @param int $enigme_id ID de l'énigme à traiter.
 * @param bool $mettre_a_jour Si true, met à jour ACF. Sinon, retourne uniquement.
 * @param string|null $statut_chasse_forcé Permet de passer un statut de chasse sans relecture ACF.
 * @return string Statut calculé.
 */
function enigme_mettre_a_jour_etat_systeme(int $enigme_id, bool $mettre_a_jour = true, ?string $statut_chasse_forcé = null): string
{
    if (get_post_type($enigme_id) !== 'enigme') {
        cat_debug("❌ [STATUT] Post #$enigme_id n'est pas une énigme");
        return 'cache_invalide';
    }
    $etat = 'accessible';

    // 🔎 Vérifie la chasse liée
    $chasse_id = recuperer_id_chasse_associee($enigme_id);
    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        $etat = 'bloquee_chasse';
        cat_debug("🧩 #$enigme_id → bloquee_chasse (aucune chasse valide liée)");
    } else {
        $statut_chasse = $statut_chasse_forcé ?? (get_field('champs_caches', $chasse_id)['chasse_cache_statut'] ?? null);
        cat_debug("🧩 #$enigme_id → chasse #$chasse_id statut = $statut_chasse");

        if (!in_array($statut_chasse, ['en_cours', 'payante', 'termine'], true)) {
            $etat = 'bloquee_chasse';
        }
    }

    // 🔐 Accès programmé / prérequis
    $condition = get_field('enigme_acces_condition', $enigme_id) ?? 'immediat';

    if ($etat === 'accessible' && $condition === 'date_programmee') {
        $date = get_field('enigme_acces_date', $enigme_id);
        $date_obj = convertir_en_datetime($date);
        if (!$date_obj || $date_obj->getTimestamp() > time()) {
            $etat = 'bloquee_date';
            cat_debug("🧩 #$enigme_id → bloquee_date (accès programmé futur ou vide)");
        }
    }

    // ❓ Vérifie si la réponse attendue est bien définie si validation = automatique
    $mode = get_field('enigme_mode_validation', $enigme_id);
    $reponse = get_field('enigme_reponse_bonne', $enigme_id);
    if ($etat === 'accessible' && $mode === 'automatique' && !$reponse) {
        $etat = 'invalide';
        cat_debug("🧩 #$enigme_id → invalide (automatique sans réponse)");
    }

    // ✅ Mise à jour ACF si demandé
    if ($mettre_a_jour) {
        $actuel = get_field('enigme_cache_etat_systeme', $enigme_id);
        if ($actuel !== $etat) {
            update_field('enigme_cache_etat_systeme', $etat, $enigme_id);
        } else {
            cat_debug("⏸️ [STATUT] Pas de changement pour #$enigme_id (déjà $etat)");
        }
    }

    return $etat;
}




/**
 * Hook automatique ACF : met à jour l’état système d’une énigme après enregistrement.
 *
 * @param int|string $post_id ID de l’énigme ou identifiant ACF (ex : 'options')
 * @return void
 */
function enigme_mettre_a_jour_etat_systeme_automatiquement($post_id): void
{
    if (!is_numeric($post_id) || get_post_type($post_id) !== 'enigme') return;
    if ($post_id === 'options' || wp_is_post_revision($post_id)) return;

    enigme_mettre_a_jour_etat_systeme((int) $post_id); // appelle la version unifiée
}


/**
 * 🔁 Recalcule le statut système d’une énigme via appel AJAX sécurisé.
 *
 * @hook wp_ajax_forcer_recalcul_statut_enigme
 * @return void
 */
add_action('wp_ajax_forcer_recalcul_statut_enigme', 'forcer_recalcul_statut_enigme');

function forcer_recalcul_statut_enigme()
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

    if (!$post_id || get_post_type($post_id) !== 'enigme') {
        wp_send_json_error('post_invalide');
    }

    enigme_mettre_a_jour_etat_systeme($post_id);
    wp_send_json_success('statut_enigme_recalcule');
}

/**
 * 🔍 Retourne l'état système de l'énigme (champ ACF cache).
 *
 * @param int $enigme_id ID de l’énigme
 * @return string Valeur du champ (accessible, bloquee_date, etc.)
 */
function enigme_get_etat_systeme(int $enigme_id): string
{
    return get_field('enigme_cache_etat_systeme', $enigme_id) ?: 'invalide';
}

/**
 * ✅ Vérifie si un joueur peut engager une énigme (accès + pas déjà engagé).
 *
 * @param int $enigme_id ID de l’énigme
 * @param int|null $user_id ID du joueur (par défaut : utilisateur courant)
 * @return bool True si engagement possible
 */
function utilisateur_peut_engager_enigme(int $enigme_id, ?int $user_id = null): bool
{
    $user_id = $user_id ?? get_current_user_id();

    $etat_systeme = enigme_get_etat_systeme($enigme_id);
    $statut = enigme_get_statut_utilisateur($enigme_id, $user_id);

    $statuts_autorises = ['non_commencee', 'abandonnee', 'echouee'];

    return $etat_systeme === 'accessible' && in_array($statut, $statuts_autorises, true);
}

// ==================================================
// ✅ GESTION DE LA COMPLÉTION DES CPT
// ==================================================

function organisateur_est_complet(int $organisateur_id): bool
{
    if (get_post_type($organisateur_id) !== 'organisateur') {
        return false;
    }

    $titre_ok = titre_est_valide($organisateur_id);

    $logo = get_field('profil_public_logo_organisateur', $organisateur_id);
    $logo_ok = !empty($logo);

    $description_field = get_field('description_longue', $organisateur_id);
    $description = trim((string) $description_field);
    $desc_ok = $description !== '';

    return $titre_ok && $logo_ok && $desc_ok;
}

function organisateur_mettre_a_jour_complet(int $organisateur_id): bool
{
    $complet = organisateur_est_complet($organisateur_id);
    update_field('organisateur_cache_complet', $complet ? 1 : 0, $organisateur_id);
    return $complet;
}

function chasse_est_complet(int $chasse_id): bool
{
    if (get_post_type($chasse_id) !== 'chasse') {
        return false;
    }

    $titre_ok = titre_est_valide($chasse_id);

    $desc_field = get_field('chasse_principale_description', $chasse_id);
    $desc = trim((string) $desc_field);
    $desc_ok = $desc !== '';

    $image = get_field('chasse_principale_image', $chasse_id);
    $image_id = is_array($image) ? ($image['ID'] ?? 0) : (int) $image;
    $image_ok = !empty($image_id) && $image_id !== 3902;

    return $titre_ok && $desc_ok && $image_ok;
}

function chasse_mettre_a_jour_complet(int $chasse_id): bool
{
    $complet = chasse_est_complet($chasse_id);
    update_field('chasse_cache_complet', $complet ? 1 : 0, $chasse_id);
    return $complet;
}

function enigme_est_complet(int $enigme_id): bool
{
    if (get_post_type($enigme_id) !== 'enigme') {
        return false;
    }

    $titre_ok = titre_est_valide($enigme_id);

    $images = get_field('enigme_visuel_image', $enigme_id);
    $placeholder = defined('ID_IMAGE_PLACEHOLDER_ENIGME') ? ID_IMAGE_PLACEHOLDER_ENIGME : 3925;
    $first_id = (is_array($images) && !empty($images[0]['ID'])) ? (int) $images[0]['ID'] : 0;
    $image_ok = $first_id && $first_id !== $placeholder;

    // 🔄 [NOVELTY] Require an expected answer if validation is automatic
    $mode = get_field('enigme_mode_validation', $enigme_id);
    $reponse = trim((string) get_field('enigme_reponse_bonne', $enigme_id));
    $reponse_ok = $mode !== 'automatique' || $reponse !== '';

    return $titre_ok && $image_ok && $reponse_ok;
}

function enigme_mettre_a_jour_complet(int $enigme_id): bool
{
    $complet = enigme_est_complet($enigme_id);
    update_field('enigme_cache_complet', $complet ? 1 : 0, $enigme_id);
    return $complet;
}

function mettre_a_jour_cache_complet_automatiquement($post_id): void
{
    if (!is_numeric($post_id)) {
        return;
    }

    $type = get_post_type($post_id);
    if ($type === 'organisateur') {
        organisateur_mettre_a_jour_complet((int) $post_id);
    } elseif ($type === 'chasse') {
        chasse_mettre_a_jour_complet((int) $post_id);
    } elseif ($type === 'enigme') {
        enigme_mettre_a_jour_complet((int) $post_id);
    }
}
add_action('acf/save_post', 'mettre_a_jour_cache_complet_automatiquement', 20);

/**
 * Vérifie la valeur du champ `_cache_complet` d'un post et la
 * synchronise si elle ne correspond pas à la réalité.
 *
 * Cette vérification est légère et peut être appelée à chaque
 * affichage d'un post (ex : pages single) pour s'assurer que les
 * panneaux d'édition ne s'ouvrent pas inutilement.
 *
 * @param int $post_id ID du post à contrôler.
 * @return void
 */
function verifier_ou_mettre_a_jour_cache_complet(int $post_id): void
{
    if (!is_numeric($post_id)) {
        return;
    }

    static $deja = [];
    if (in_array($post_id, $deja, true)) {
        return;
    }
    $deja[] = $post_id;

    $type = get_post_type($post_id);

    switch ($type) {
        case 'organisateur':
            $cache = (bool) get_field('organisateur_cache_complet', $post_id);
            $reel  = organisateur_est_complet($post_id);
            if ($cache !== $reel) {
                update_field('organisateur_cache_complet', $reel ? 1 : 0, $post_id);
            }
            break;

        case 'chasse':
            $cache = (bool) get_field('chasse_cache_complet', $post_id);
            $reel  = chasse_est_complet($post_id);
            if ($cache !== $reel) {
                update_field('chasse_cache_complet', $reel ? 1 : 0, $post_id);
            }
            break;

        case 'enigme':
            $cache = (bool) get_field('enigme_cache_complet', $post_id);
            $reel  = enigme_est_complet($post_id);
            if ($cache !== $reel) {
                update_field('enigme_cache_complet', $reel ? 1 : 0, $post_id);
            }
            break;
    }
}







// ==================================================
// 🧠 GESTION DES STATUTS DES CHASSES
// ==================================================
/**
 * 🔹 verifier_ou_recalculer_statut_chasse() → Vérifie et met à jour le statut ACF d'une chasse à l'affichage si nécessaire.
 * 🔹 mettre_a_jour_statuts_chasse() → Met à jour les statuts de validation et de visibilité d'une chasse.
 * 🔹 forcer_recalcul_statut_chasse() → Forcer un recalcul du statut via une requête AJAX.
 * 🔹 mettre_a_jour_statut_si_chasse() → Déclenche la mise à jour automatique du statut après sauvegarde ACF en admin.
 * 🔹 recuperer_statut_chasse() → Retourne dynamiquement le statut pour mise à jour du badge via JS.
 * 🔹 forcer_statut_selon_validation_chasse() → Applique le statut WordPress selon la validation lors de la sauvegarde admin.
 * 🔹 forcer_statut_apres_acf() → Corrige le post_status après sauvegarde ACF pour éviter les incohérences.
 */



/**
 * Vérifie et met à jour le statut d'une chasse si le statut cache semble obsolète.
 *
 * À utiliser lors de l'affichage de la fiche chasse.
 *
 * @param int $chasse_id
 * @return void
 */
function verifier_ou_recalculer_statut_chasse($chasse_id): void
{
    if (get_post_type($chasse_id) !== 'chasse') return;

    static $chasses_traitees = [];

    if (in_array($chasse_id, $chasses_traitees, true)) return;
    $chasses_traitees[] = $chasse_id;


    $cache = get_field('champs_caches', $chasse_id);
    $statut = $cache['chasse_cache_statut'] ?? null;

    // Si le statut est manquant ou invalide, on le recalcule
    $statuts_valides = ['revision', 'a_venir', 'en_cours', 'payante', 'termine'];
    if (!in_array($statut, $statuts_valides, true)) {
        mettre_a_jour_statuts_chasse($chasse_id);
        return;
    }

    // On pourrait aller plus loin : vérifier si la date est dépassée
    $carac = get_field('caracteristiques', $chasse_id);
    $now = current_time('timestamp');
    $date_fin = !empty($carac['chasse_infos_date_fin']) ? strtotime($carac['chasse_infos_date_fin']) : null;

    if (!empty($carac['chasse_infos_duree_illimitee'])) return;

    if ($statut !== 'termine' && $date_fin && $date_fin < $now) {
        mettre_a_jour_statuts_chasse($chasse_id);
    }
}


/**
 * Met à jour le statut fonctionnel d'une chasse (champ ACF `chasse_cache_statut`).
 *
 * Appelée lors de toute modification importante.
 * Si la chasse devient "termine", planifie (ou déclenche) les déplacements de PDF.
 *
 * @param int $chasse_id ID du post de type "chasse".
 */
function mettre_a_jour_statuts_chasse($chasse_id)
{
    if (get_post_type($chasse_id) !== 'chasse') return;

    $carac = get_field('caracteristiques', $chasse_id);
    $cache = get_field('champs_caches', $chasse_id);
    if (!$carac || !$cache) {
        cat_debug("⚠️ Données manquantes pour chasse #$chasse_id : caractéristiques ou champs_caches");
        return;
    }

    $maintenant = current_time('timestamp');

    $statut_validation = $cache['chasse_cache_statut_validation'] ?? 'creation';
    $date_debut_obj    = convertir_en_datetime($carac['chasse_infos_date_debut'] ?? null);
    $date_debut        = $date_debut_obj ? $date_debut_obj->getTimestamp() : null;
    $date_fin_obj      = convertir_en_datetime($carac['chasse_infos_date_fin'] ?? null);
    $date_fin          = $date_fin_obj ? $date_fin_obj->getTimestamp() : null;
    $date_obj          = convertir_en_datetime($cache['chasse_cache_date_decouverte'] ?? null);
    $date_decouverte   = $date_obj ? $date_obj->getTimestamp() : null;
    $cout_points       = intval($carac['chasse_infos_cout_points'] ?? 0);
    $mode_continue     = empty($carac['chasse_infos_duree_illimitee']);

    $statut = 'revision';

    if ($statut_validation === 'valide') {
        if ($date_decouverte) {
            $statut = 'termine';
        } elseif ($mode_continue && $date_fin && $date_fin < $maintenant) {
            $statut = 'termine';
        } elseif ($date_debut && $date_debut <= $maintenant) {
            $statut = ($cout_points > 0) ? 'payante' : 'en_cours';
        } elseif ($date_debut && $date_debut > $maintenant) {
            $statut = 'a_venir';
        } else {
            $statut = $cache['chasse_cache_statut'] ?? 'revision';
        }
    }

    $ancien = $cache['chasse_cache_statut'] ?? '(inconnu)';

    // ✅ Si terminée, déclenche les planifications PDF
    if ($statut === 'termine') {
        $liste_enigmes = recuperer_enigmes_associees($chasse_id);

        foreach ($liste_enigmes as $enigme_id) {
            planifier_ou_deplacer_pdf_solution_immediatement($enigme_id);
        }
    }

    mettre_a_jour_sous_champ_group($chasse_id, 'champs_caches', 'chasse_cache_statut', $statut);

    if (function_exists('synchroniser_cache_enigmes_chasse')) {
        synchroniser_cache_enigmes_chasse($chasse_id, true, true);
    }

    mettre_a_jour_statuts_enigmes_de_la_chasse($chasse_id, $statut);
}



/**
 * 🔁 Forcer le recalcul du statut d'une chasse via AJAX.
 *
 * Utilisé pour recalculer le statut après une mise à jour front-end
 * sans attendre la sauvegarde naturelle de WordPress/ACF.
 *
 * @hook wp_ajax_forcer_recalcul_statut_chasse
 * @return void
 */
add_action('wp_ajax_forcer_recalcul_statut_chasse', 'forcer_recalcul_statut_chasse');

/**
 * Forcer le recalcul du statut d'une chasse (via appel AJAX séparé, après modification d’un champ).
 */
function forcer_recalcul_statut_chasse()
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

    if (!$post_id || get_post_type($post_id) !== 'chasse') {
        wp_send_json_error('post_invalide');
    }

    mettre_a_jour_statuts_chasse($post_id);
    wp_send_json_success('statut_recalcule');
}


/**
 * 🔄 Mettre à jour le statut d'une chasse après enregistrement ACF en admin.
 *
 * Accroché au hook `acf/save_post` pour recalculer automatiquement
 * le statut fonctionnel dès qu'un organisateur modifie ses champs en back-office.
 *
 * @param int $post_id ID du post enregistré par ACF.
 * @return void
 */
add_action('acf/save_post', 'mettre_a_jour_statut_si_chasse', 20);

function mettre_a_jour_statut_si_chasse($post_id)
{
    if (!is_numeric($post_id)) return;

    if (get_post_type($post_id) === 'chasse') {
        // 🔁 Supprimer le champ pour forcer une relecture propre (évite valeurs en cache)
        delete_transient("acf_field_{$post_id}_champs_caches");

        cat_debug("🔁 Recalcul du statut via acf/save_post pour la chasse $post_id");
        mettre_a_jour_statuts_chasse($post_id);
    }
}


/**
 * 🔎 Récupère le statut public actuel d'une chasse (via AJAX).
 *
 * Utilisé pour mettre à jour dynamiquement le badge de statut en front,
 * après une modification qui déclenche un recalcul.
 *
 * @hook wp_ajax_recuperer_statut_chasse
 * @return void
 */
add_action('wp_ajax_recuperer_statut_chasse', 'recuperer_statut_chasse');

function recuperer_statut_chasse()
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

    if (!$post_id || get_post_type($post_id) !== 'chasse') {
        wp_send_json_error('post_invalide');
    }

    $statut = get_field('champs_caches_chasse_cache_statut', $post_id);
    if (!$statut) {
        wp_send_json_error('statut_indisponible');
    }

    $statut_str = is_string($statut) ? $statut : '';
    wp_send_json_success([
        'statut' => $statut_str,
        'statut_label' => ucfirst(str_replace('_', ' ', $statut_str))
    ]);
}


/**
 * 🔁 Met à jour le champ de validation et force le statut du post en cohérence.
 *
 * Si $nouvelle_validation est fourni, il est appliqué à chasse_cache_statut_validation
 * avant de recalculer et mettre à jour le post_status correspondant.
 *
 * @param int    $post_id              ID du post chasse.
 * @param string|null $nouvelle_validation  Valeur à forcer (valide, banni, etc.). Optionnel.
 * @return void
 */
function forcer_statut_apres_acf($post_id, $nouvelle_validation = null)
{
    if (!is_numeric($post_id) || get_post_type($post_id) !== 'chasse') return;

    // Lecture et mise à jour facultative
    $champs = get_field('champs_caches', $post_id) ?: [];

    if ($nouvelle_validation !== null) {
        $champs['chasse_cache_statut_validation'] = sanitize_text_field($nouvelle_validation);
        update_field('champs_caches', $champs, $post_id);
    }

    $validation = $champs['chasse_cache_statut_validation'] ?? null;
    if (!$validation) return;

    $statut_voulu = match ($validation) {
        'valide'     => 'publish',
        'banni'      => 'draft',
        default      => 'pending',
    };

    if (get_post_status($post_id) !== $statut_voulu) {
        wp_update_post([
            'ID'          => $post_id,
            'post_status' => $statut_voulu,
        ]);
    }
}
add_action('acf/save_post', 'forcer_statut_apres_acf', 99);



/**
 * 🔹 is_canevas_creation → Vérifie si l’utilisateur est en train de créer son espace organisateur (aucun CPT associé, et sur la page dédiée).
 */

/**
 * Détermine si l’utilisateur est dans le parcours de création d’un organisateur (canevas).
 *
 * @return bool
 */
function is_canevas_creation()
{
    if (!is_user_logged_in()) {
        return false;
    }

    if (!is_page('devenir-organisateur')) {
        return false;
    }

    return !get_organisateur_from_user(get_current_user_id());
}


/**
 * 🔐 Vérifie la cohérence entre post_status natif et statut de validation ACF.
 *
 * Si une incohérence est détectée, elle est loguée.
 * Le changement automatique de statut est désactivé en phase de développement.
 *
 * @hook save_post_chasse
 * @param int     $post_id ID du post.
 * @param WP_Post $post    Objet post complet.
 * @param bool    $update  True si c’est une mise à jour (false si création).
 */
add_action('save_post_chasse', 'forcer_statut_selon_validation_chasse', 20, 3);

function forcer_statut_selon_validation_chasse($post_id, $post, $update)
{
    // Éviter boucle infinie
    remove_action('save_post_chasse', 'forcer_statut_selon_validation_chasse', 20);

    // Ne pas agir sur autosave ou révisions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    $cache = get_field('champs_caches', $post_id);
    if (!$cache || !isset($cache['chasse_cache_statut_validation'])) return;

    $validation = $cache['chasse_cache_statut_validation'];
    $statut_wp = get_post_status($post_id);

    $statut_attendu = match ($validation) {
        'valide'   => 'publish',
        'banni'    => 'draft',
        default    => 'pending',
    };

    if ($statut_wp !== $statut_attendu) {
        cat_debug("⚠️ Décalage statut WP vs ACF pour chasse $post_id → WP = $statut_wp / ACF = $validation");

        // ⛔ EN DÉVELOPPEMENT : synchronisation désactivée
        // ✅ À ACTIVER EN PROD :
        /*
    wp_update_post([
      'ID'          => $post_id,
      'post_status' => $statut_attendu,
    ]);
    */
    }
}


// ==================================================
// 🧑‍💻 GESTION DES STATUTS DES JOUEURS (UTILISATEUR ↔ ÉNIGME)
// ==================================================
/**
 * 🔹 get_statut_utilisateur_enigme() → Retourne le statut du joueur pour une énigme donnée.
 * 🔹 est_enigme_resolue_par_utilisateur() → Booléen : l’utilisateur a-t-il résolu l’énigme ?
 */

/**
 * Retourne le statut du joueur pour une énigme donnée (avec cache interne).
 *
 * @param int $user_id
 * @param int $enigme_id
 * @return string|null Le statut ('non_commencee', 'resolue', etc.) ou null si absent
 */
function get_statut_utilisateur_enigme($user_id, $enigme_id)
{
    static $cache = [];
    $key = $user_id . '-' . $enigme_id;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    global $wpdb;
    $table = $wpdb->prefix . 'enigme_statuts_utilisateur';

    $statut = $wpdb->get_var($wpdb->prepare(
        "SELECT statut FROM $table WHERE user_id = %d AND enigme_id = %d",
        $user_id,
        $enigme_id
    ));

    $cache[$key] = $statut ?: null;
    return $cache[$key];
}

/**
 * Vérifie si l'utilisateur a résolu une énigme.
 *
 * @param int $user_id
 * @param int $enigme_id
 * @return bool
 */
function est_enigme_resolue_par_utilisateur($user_id, $enigme_id)
{
    return get_statut_utilisateur_enigme($user_id, $enigme_id) === 'resolue';
}
