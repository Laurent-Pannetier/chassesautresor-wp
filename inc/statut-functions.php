<?php

// 🚀 Empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/badge-functions.php';

if (!class_exists(ChassesAuTresor\Core\Progress\HuntProgressService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Progress/HuntProgressRepository.php';
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Progress/HuntProgressService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleCompletionService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Content/RiddleCompletionService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\OrganizerCompletionService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Content/OrganizerCompletionService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HuntCompletionService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Content/HuntCompletionService.php';
}

if (!function_exists('cat_get_hunt_progress_service')) {
    function cat_get_hunt_progress_service(): ChassesAuTresor\Core\Progress\HuntProgressService
    {
        global $wpdb;

        return new ChassesAuTresor\Core\Progress\HuntProgressService(
            new ChassesAuTresor\Core\Progress\HuntProgressRepository($wpdb)
        );
    }
}

if (!function_exists('enigme_get_bonnes_reponses')) {
    function enigme_get_bonnes_reponses(int $enigme_id): array
    {
        $raw = function_exists('get_field') ? get_field('enigme_reponse_bonne', $enigme_id) : '';

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter(array_map('strval', $decoded)));
            }

            if (function_exists('update_field')) {
                update_field('enigme_reponse_bonne', wp_json_encode([$raw]), $enigme_id);
            }

            return [$raw];
        }

        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }

        return [];
    }
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

    $statut = cat_get_hunt_progress_service()->getRiddleStatus($user_id, $enigme_id);

    if ($statut) {
        $statut = strtolower(remove_accents($statut));
    }

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

    $nouveau_statut = strtolower(remove_accents($nouveau_statut));

    return cat_get_hunt_progress_service()->advanceRiddleStatus(
        $user_id,
        $enigme_id,
        $nouveau_statut,
        current_time('mysql'),
        $forcer
    );
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

    $condition = get_field('enigme_acces_condition', $enigme_id) ?? 'immediat';

    $prerequisiteIds = [];
    foreach (is_array($pre_requis) ? $pre_requis : [] as $requiredRiddle) {
        if (is_object($requiredRiddle) && isset($requiredRiddle->ID)) {
            $prerequisiteIds[] = (int) $requiredRiddle->ID;
        } elseif (is_numeric($requiredRiddle)) {
            $prerequisiteIds[] = (int) $requiredRiddle;
        }
    }

    return cat_get_hunt_progress_service()->areRiddlePrerequisitesMet(
        $user_id,
        $prerequisiteIds,
        (string) $condition
    );
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
    $statut = $user_id > 0 ? enigme_get_statut_utilisateur($enigme_id, $user_id) : '';

    $unlockTimestamp = null;
    $formattedUnlockDate = null;
    if ($statut === 'bloquee_date') {
        $access = get_field('enigme_acces', $enigme_id);
        $dateString = is_array($access) ? ($access['enigme_acces_date'] ?? null) : null;
        $date = $dateString ? convertir_en_datetime($dateString) : null;
        if ($date) {
            $unlockTimestamp = $date->getTimestamp();
            $formattedUnlockDate = $date->format('d/m/Y à H\hi');
        }
    }

    return cat_get_hunt_progress_service()->getRiddleLockState(
        $user_id,
        $statut,
        $unlockTimestamp,
        $formattedUnlockDate
    );
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
    $user_id     = $user_id ?: get_current_user_id();
    $statut       = enigme_get_statut_utilisateur($enigme_id, $user_id);
    $chasse_id    = recuperer_id_chasse_associee($enigme_id);
    $post_status  = get_post_status($enigme_id);

    // 🔓 Accès total pour l'administrateur
    if (current_user_can('manage_options')) {
        return [
            'etat' => $statut,
            'rediriger' => false,
            'url' => null,
            'afficher_formulaire' => false,
            'afficher_message' => false,
            'message_html' => '',
        ];
    }

    // 🚫 Contenu brouillon : aucun accès hors administrateur
    if ($post_status === 'draft') {
        return [
            'etat' => $statut,
            'rediriger' => true,
            'url' => $chasse_id ? get_permalink($chasse_id) : home_url('/'),
            'afficher_formulaire' => false,
            'afficher_message' => false,
            'message_html' => '',
        ];
    }

    // ✅ Organisateur associé : accès standard
    if (utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)) {
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
    $statut_chasse = get_field('chasse_cache_statut', $chasse_id);
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

    // 🔒 Joueur non engagé dans la chasse ou l'énigme
    if (
        !utilisateur_est_engage_dans_chasse($user_id, $chasse_id) ||
        !utilisateur_est_engage_dans_enigme($user_id, $enigme_id)
    ) {
        return [
            'etat' => $statut,
            'rediriger' => true,
            'url' => $chasse_id ? get_permalink($chasse_id) : home_url('/'),
            'afficher_formulaire' => false,
            'afficher_message' => false,
            'message_html' => '',
        ];
    }

    $condition_acces = get_field('enigme_acces_condition', $enigme_id) ?? 'immediat';
    if ($condition_acces === 'pre_requis' && !enigme_pre_requis_remplis($enigme_id, $user_id)) {
        $statut = 'bloquee_pre_requis';
    }

    $state = cat_get_hunt_progress_service()->getRiddleParticipationState($statut);
    $state['url'] = $state['rediriger']
        ? ($chasse_id ? get_permalink($chasse_id) : home_url('/'))
        : null;

    return $state;
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
    $chasse_id = recuperer_id_chasse_associee($enigme_id);
    $hasValidHunt = $chasse_id > 0 && get_post_type($chasse_id) === 'chasse';
    $statut_chasse = $hasValidHunt
        ? (string) ($statut_chasse_forcé ?? get_field('chasse_cache_statut', $chasse_id))
        : '';
    $condition = get_field('enigme_acces_condition', $enigme_id) ?? 'immediat';
    $scheduledDate = $condition === 'date_programmee'
        ? convertir_en_datetime(get_field('enigme_acces_date', $enigme_id))
        : null;
    $mode = get_field('enigme_mode_validation', $enigme_id);
    $reponses = enigme_get_bonnes_reponses($enigme_id);

    $etat = cat_get_hunt_progress_service()->calculateRiddleSystemState(
        $hasValidHunt,
        $statut_chasse,
        (string) $condition,
        $scheduledDate ? $scheduledDate->getTimestamp() : null,
        (string) $mode,
        !empty($reponses)
    );

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
    $prerequisitesMet = $etat_systeme === 'bloquee_pre_requis'
        && function_exists('enigme_pre_requis_remplis')
        && enigme_pre_requis_remplis($enigme_id, $user_id);
    $statut = enigme_get_statut_utilisateur($enigme_id, $user_id);

    return cat_get_hunt_progress_service()->canEngageRiddle(
        $etat_systeme,
        $statut,
        $prerequisitesMet
    );
}

// ==================================================
// ✅ GESTION DE LA COMPLÉTION DES CPT
// ==================================================

function organisateur_est_complet(int $organisateur_id): bool
{
    if (get_post_type($organisateur_id) !== 'organisateur') {
        return false;
    }

    $logo = get_field('logo_organisateur', $organisateur_id);

    return (new ChassesAuTresor\Core\Content\OrganizerCompletionService())->isComplete(
        titre_est_valide($organisateur_id),
        !empty($logo),
        (string) get_field('description_longue', $organisateur_id)
    );
}

function organisateur_mettre_a_jour_complet(int $organisateur_id): bool
{
    $complet = organisateur_est_complet($organisateur_id);
    update_field('organisateur_cache_complet', $complet ? 1 : 0, $organisateur_id);
    return $complet;
}

function chasse_has_validatable_enigme(int $chasse_id): bool
{
    $enigme_ids = recuperer_ids_enigmes_pour_chasse($chasse_id);
    $validationModes = [];

    foreach ($enigme_ids as $eid) {
        $validationModes[] = (string) get_field('enigme_mode_validation', $eid);
    }

    return (new ChassesAuTresor\Core\Content\HuntCompletionService())->hasValidatableRiddle(
        $validationModes
    );
}

function chasse_est_complet(int $chasse_id): bool
{
    if (get_post_type($chasse_id) !== 'chasse') {
        return false;
    }

    $mode_fin = get_field('chasse_mode_fin', $chasse_id) ?: 'automatique';

    $image    = get_field('chasse_principale_image', $chasse_id);
    $image_id = is_array($image) ? ($image['ID'] ?? 0) : (int) $image;

    return (new ChassesAuTresor\Core\Content\HuntCompletionService())->isComplete(
        titre_est_valide($chasse_id),
        (string) get_field('chasse_principale_description', $chasse_id),
        (int) $image_id,
        3902,
        (string) $mode_fin,
        chasse_has_validatable_enigme($chasse_id)
    );
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
    $mode = get_field('enigme_mode_validation', $enigme_id);
    $reponses = enigme_get_bonnes_reponses($enigme_id);
    $condition_acces = get_field('enigme_acces_condition', $enigme_id) ?? 'immediat';
    $pre_requis = get_field('enigme_acces_pre_requis', $enigme_id);

    return (new ChassesAuTresor\Core\Content\RiddleCompletionService())->isComplete(
        $titre_ok,
        $first_id,
        $placeholder,
        (string) $mode,
        !empty($reponses),
        (string) $condition_acces,
        is_array($pre_requis) && !empty($pre_requis)
    );
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

        // ⚡ Synchronise la chasse parente pour que la complétion soit
        // immédiatement prise en compte sur la fiche énigme.
        $chasse_id = recuperer_id_chasse_associee((int) $post_id);
        if ($chasse_id) {
            chasse_mettre_a_jour_complet((int) $chasse_id);
        }
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
                chasse_clear_infos_affichage_cache($post_id);
            }
            break;

        case 'enigme':
            $cache = (bool) get_field('enigme_cache_complet', $post_id);
            $reel  = enigme_est_complet($post_id);
            if ($cache !== $reel) {
                update_field('enigme_cache_complet', $reel ? 1 : 0, $post_id);
                if (function_exists('recuperer_id_chasse_associee')) {
                    $chasse_id = recuperer_id_chasse_associee($post_id);
                    if ($chasse_id) {
                        chasse_clear_infos_affichage_cache((int) $chasse_id);
                    }
                }
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
    if (get_post_type($chasse_id) !== 'chasse') {
        return;
    }

    static $chasses_traitees = [];

    if (in_array($chasse_id, $chasses_traitees, true)) {
        return;
    }
    $chasses_traitees[] = $chasse_id;

    $statut           = (string) get_field('chasse_cache_statut', $chasse_id);
    $validation       = (string) get_field('chasse_cache_statut_validation', $chasse_id);
    $date_debut_obj   = convertir_en_datetime(get_field('chasse_infos_date_debut', $chasse_id) ?: null);
    $date_fin_obj     = convertir_en_datetime(get_field('chasse_infos_date_fin', $chasse_id) ?: null);
    $decouverte_obj   = convertir_en_datetime(get_field('chasse_cache_date_decouverte', $chasse_id) ?: null);
    $service          = new ChassesAuTresor\Core\Progress\HuntStatusService();

    if ($service->isStale(
        $statut,
        $validation,
        $date_debut_obj ? $date_debut_obj->getTimestamp() : null,
        $date_fin_obj ? $date_fin_obj->getTimestamp() : null,
        $decouverte_obj ? $decouverte_obj->getTimestamp() : null,
        (int) get_field('chasse_infos_cout_points', $chasse_id),
        !empty(get_field('chasse_infos_duree_illimitee', $chasse_id)),
        (int) current_time('timestamp')
    )) {
        mettre_a_jour_statuts_chasse($chasse_id);
        chasse_clear_infos_affichage_cache($chasse_id);
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

    $cache = [
        'validation' => get_field('chasse_cache_statut_validation', $chasse_id),
        'statut'     => get_field('chasse_cache_statut', $chasse_id),
        'date'       => get_field('chasse_cache_date_decouverte', $chasse_id),
    ];

    $carac = [
        'date_debut'      => get_field('chasse_infos_date_debut', $chasse_id),
        'date_fin'        => get_field('chasse_infos_date_fin', $chasse_id),
        'cout_points'     => get_field('chasse_infos_cout_points', $chasse_id),
        'duree_illimitee' => get_field('chasse_infos_duree_illimitee', $chasse_id),
    ];

    if (!$cache['validation']) {
        cat_debug("⚠️ Données manquantes pour chasse #$chasse_id : champs_caches");
        return;
    }

    $statut_validation = $cache['validation'] ?? 'creation';
    $date_debut_obj    = convertir_en_datetime($carac['date_debut'] ?? null);
    $date_debut        = $date_debut_obj ? $date_debut_obj->getTimestamp() : null;
    $date_fin_obj      = convertir_en_datetime($carac['date_fin'] ?? null);
    $date_fin          = $date_fin_obj ? $date_fin_obj->getTimestamp() : null;
    $date_obj          = convertir_en_datetime($cache['date'] ?? null);
    $date_decouverte   = $date_obj ? $date_obj->getTimestamp() : null;
    $cout_points       = intval($carac['cout_points'] ?? 0);
    $statut            = (new ChassesAuTresor\Core\Progress\HuntStatusService())->calculate(
        (string) $statut_validation,
        $date_debut,
        $date_fin,
        $date_decouverte,
        $cout_points,
        !empty($carac['duree_illimitee']),
        (int) current_time('timestamp'),
        (string) ($cache['statut'] ?? 'revision')
    );

    // ✅ Si terminée, déclenche les planifications PDF
    if ($statut === 'termine') {
        $liste_enigmes = recuperer_enigmes_associees($chasse_id);

        foreach ($liste_enigmes as $enigme_id) {
            planifier_ou_deplacer_pdf_solution_immediatement($enigme_id);
        }
    }

    update_field('chasse_cache_statut', $statut, $chasse_id);

    if (function_exists('synchroniser_cache_enigmes_chasse')) {
        synchroniser_cache_enigmes_chasse($chasse_id, true, true);
    }

    mettre_a_jour_statuts_enigmes_de_la_chasse($chasse_id, $statut);
    chasse_clear_infos_affichage_cache($chasse_id);
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

    $statut = get_field('chasse_cache_statut', $post_id);
    if (!$statut) {
        wp_send_json_error('statut_indisponible');
    }

    $statut_str = is_string($statut) ? $statut : '';
    $validation = get_field('chasse_cache_statut_validation', $post_id);
    $badge_infos = chasse_preparer_badge_statut($statut_str, is_string($validation) ? $validation : null);

    wp_send_json_success([
        'statut'       => $badge_infos['statut'],
        'statut_label' => $badge_infos['label'],
        'statut_icon'  => $badge_infos['icon_html'],
        'statut_tooltip' => $badge_infos['label'],
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
    $validation = get_field('chasse_cache_statut_validation', $post_id);

    if ($nouvelle_validation !== null) {
        update_field('chasse_cache_statut_validation', sanitize_text_field($nouvelle_validation), $post_id);
        $validation = sanitize_text_field($nouvelle_validation);
    }

    if (!$validation) return;

    $statut_voulu = (new ChassesAuTresor\Core\Content\HuntPublicationStatusService())->resolve(
        (string) $validation
    );

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

    $validation = get_field('chasse_cache_statut_validation', $post_id);
    if (!$validation) return;
    $statut_wp = get_post_status($post_id);

    $statut_attendu = (new ChassesAuTresor\Core\Content\HuntPublicationStatusService())->resolve(
        (string) $validation
    );

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

/**
 * Planifie une tâche récurrente pour vérifier le statut des chasses.
 *
 * @return void
 */
function schedule_cat_recalculate_chasse_statuses(): void
{
    if (!wp_next_scheduled('cat_recalculate_chasse_statuses')) {
        wp_schedule_event(time(), 'hourly', 'cat_recalculate_chasse_statuses');
    }
}
add_action('after_switch_theme', 'schedule_cat_recalculate_chasse_statuses');

/**
 * Vérifie périodiquement les statuts des chasses afin de les maintenir à jour.
 *
 * @return void
 */
function cat_recalculate_chasse_statuses(): void
{
    $chasses = get_posts([
        'post_type'      => 'chasse',
        'post_status'    => 'any',
        'fields'         => 'ids',
        'posts_per_page' => -1,
    ]);

    foreach ($chasses as $chasse_id) {
        verifier_ou_recalculer_statut_chasse((int) $chasse_id);
    }
}
add_action('cat_recalculate_chasse_statuses', 'cat_recalculate_chasse_statuses');


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

    $statut = cat_get_hunt_progress_service()->getRiddleStatus((int) $user_id, (int) $enigme_id);

    if ($statut) {
        $statut = strtolower(remove_accents($statut));
    }

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
    $statut = get_statut_utilisateur_enigme($user_id, $enigme_id);

    return in_array($statut, ['resolue', 'terminee'], true);
}
