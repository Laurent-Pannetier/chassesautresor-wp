<?php
defined('ABSPATH') || exit;

/**
 * ============================================================
 * 🏷️  TITRES PAR DÉFAUT ET VALIDATION
 * ============================================================
 *
 * Centralise les valeurs par défaut utilisées lors de la création
 * automatique des posts (organisateur, chasse, énigme) et fournit
 * une fonction utilitaire pour vérifier si un titre a été modifié.
 */

// Valeurs par défaut des titres lors de la création des CPT
define('TITRE_DEFAUT_ORGANISATEUR', 'Votre nom d’organisateur');
define('TITRE_DEFAUT_CHASSE', 'Nouvelle chasse');
define('TITRE_DEFAUT_ENIGME', 'en création');
define('TITRE_DEFAUT_INDICE', 'Nouvel indice');
define('TITRE_DEFAUT_SOLUTION', 'Nouvelle solution');
define('INDICE_DEFAULT_PREFIX', 'clue-');

/**
 * Retourne le titre par défaut associé à un type de post donné.
 *
 * @param string $post_type Type de post (organisateur, chasse, énigme, indice, solution).
 * @return string Titre par défaut ou chaîne vide si inconnu.
 */
function get_titre_defaut(string $post_type): string {
    switch ($post_type) {
        case 'organisateur':
            return TITRE_DEFAUT_ORGANISATEUR;
        case 'chasse':
            return TITRE_DEFAUT_CHASSE;
        case 'enigme':
            return TITRE_DEFAUT_ENIGME;
        case 'indice':
            return TITRE_DEFAUT_INDICE;
        case 'solution':
            return TITRE_DEFAUT_SOLUTION;
        default:
            return '';
    }
}

/**
 * Indique si le titre d'un post est considéré comme rempli.
 *
 * Le titre est jugé invalide s'il est vide ou identique au titre par défaut
 * utilisé lors de la création du post.
 *
 * @param int $post_id ID du post à vérifier.
 * @return bool True si le titre est différent du titre par défaut et non vide.
 */
function titre_est_valide(int $post_id): bool {
    $titre = trim(get_post_field('post_title', $post_id));
    if ($titre === '') {
        return false;
    }

    $defaut = get_titre_defaut(get_post_type($post_id));
    if ($defaut !== '' && strcasecmp($titre, $defaut) === 0) {
        return false;
    }

    if (get_post_type($post_id) === 'indice') {
        $prefix = defined('INDICE_DEFAULT_PREFIX') ? INDICE_DEFAULT_PREFIX : '';
        if ($prefix !== '' && strpos($titre, $prefix) === 0) {
            return false;
        }
    }

    return true;
}
