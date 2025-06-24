<?php
namespace Chasses\Enigme;

defined('ABSPATH') || exit;

/**
 * Fonctions liées aux tentatives d'énigmes.
 */

function inserer_tentative($user_id, $enigme_id, $reponse, $resultat = 'attente', $points_utilises = 0): string {
    global $wpdb;
    $table = $wpdb->prefix . 'enigme_tentatives';
    $uid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('tent_', true);

    $wpdb->insert($table, [
        'tentative_uid'   => $uid,
        'user_id'         => $user_id,
        'enigme_id'       => $enigme_id,
        'reponse_saisie'  => $reponse,
        'resultat'        => $resultat,
        'points_utilises' => $points_utilises,
        'ip'              => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    return $uid;
}

function get_tentative_by_uid(string $uid): ?object {
    global $wpdb;
    $table = $wpdb->prefix . 'enigme_tentatives';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE tentative_uid = %s", $uid));
}

function traiter_tentative_manuelle(string $uid, string $resultat): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'enigme_tentatives';

    error_log("👣 Tentative traitement UID=$uid par IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'inconnue'));

    $tentative = get_tentative_by_uid($uid);
    if (!$tentative) {
        error_log("❌ Tentative introuvable");
        return false;
    }

    if ($tentative->resultat !== 'attente') {
        error_log("⛔ Tentative déjà traitée → statut actuel = " . $tentative->resultat);
        return false;
    }

    $user_id   = (int) $tentative->user_id;
    $enigme_id = (int) $tentative->enigme_id;

    $statut_user = $wpdb->get_var($wpdb->prepare(
        "SELECT statut FROM {$wpdb->prefix}enigme_statuts_utilisateur WHERE user_id = %d AND enigme_id = %d",
        $user_id,
        $enigme_id
    ));

    if ($statut_user === 'resolue') {
        error_log("⛔ Statut utilisateur déjà 'resolue' → refus de traitement UID=$uid");
        return false;
    }

    $current_user_id      = get_current_user_id();
    $chasse_id            = recuperer_id_chasse_associee($enigme_id);
    $organisateur_id      = get_organisateur_from_chasse($chasse_id);
    $organisateur_user_ids = (array) get_field('utilisateurs_associes', $organisateur_id);

    if (!current_user_can('manage_options') && !in_array($current_user_id, array_map('intval', $organisateur_user_ids), true)) {
        error_log("⛔ Accès interdit au traitement pour UID=$uid");
        return false;
    }

    $wpdb->update(
        $table,
        ['resultat' => $resultat, 'traitee' => 1],
        ['tentative_uid' => $uid],
        ['%s', '%d'],
        ['%s']
    );

    $nouveau_statut = $resultat === 'bon' ? 'resolue' : 'echouee';
    enigme_mettre_a_jour_statut_utilisateur($enigme_id, $user_id, $nouveau_statut);
    envoyer_mail_resultat_joueur($user_id, $enigme_id, $resultat);

    error_log("✅ Tentative UID=$uid traitée comme $resultat → statut joueur mis à jour en $nouveau_statut");
    return true;
}

function recuperer_infos_tentative(string $uid): array {
    $tentative = get_tentative_by_uid($uid);
    if (!$tentative) {
        return ['etat_tentative' => 'inexistante'];
    }

    $etat_tentative = get_etat_tentative($uid);
    $resultat       = $tentative->resultat ?? '';
    $traitee        = (int) ($tentative->traitee ?? 0) === 1;

    return [
        'etat_tentative'       => $etat_tentative,
        'statut_initial'       => $resultat ?: 'invalide',
        'statut_final'         => $resultat,
        'resultat'             => $resultat,
        'deja_traitee'         => ($etat_tentative !== 'attente'),
        'traitee'              => $traitee,
        'vient_d_etre_traitee' => $traitee && $etat_tentative !== 'attente',
        'tentative'            => $tentative,
        'nom_user'             => get_userdata($tentative->user_id)?->display_name ?? 'Utilisateur inconnu',
        'permalink'            => get_permalink($tentative->enigme_id),
        'statistiques'         => [
            'total_user'   => 0,
            'total_enigme' => 0,
            'total_chasse' => 0,
        ],
    ];
}

function get_etat_tentative(string $uid): string {
    global $wpdb;
    $table = $wpdb->prefix . 'enigme_tentatives';
    $resultat = $wpdb->get_var($wpdb->prepare("SELECT resultat FROM $table WHERE tentative_uid = %s", $uid));

    if ($resultat === null) return 'inexistante';
    if ($resultat === 'attente') return 'attente';
    if ($resultat === 'bon') return 'validee';
    if ($resultat === 'faux') return 'refusee';
    return 'invalide';
}

function recuperer_tentatives_enigme(int $enigme_id, int $limit = 25, int $offset = 0): array {
    global $wpdb;
    $table = $wpdb->prefix . 'enigme_tentatives';
    $query = $wpdb->prepare(
        "SELECT * FROM $table WHERE enigme_id = %d ORDER BY tentative_uid DESC LIMIT %d OFFSET %d",
        $enigme_id,
        $limit,
        $offset
    );
    $res = $wpdb->get_results($query);
    return $res ?: [];
}

function compter_tentatives_enigme(int $enigme_id): int {
    global $wpdb;
    $table = $wpdb->prefix . 'enigme_tentatives';
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE enigme_id = %d", $enigme_id));
}
