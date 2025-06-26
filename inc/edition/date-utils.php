<?php
defined('ABSPATH') || exit;

/**
 * Valide la cohérence des dates d'une chasse.
 *
 * @param DateTime      $debut      Date de début.
 * @param DateTime|null $fin        Date de fin ou null si illimitée.
 * @param bool          $illimitee  Mode illimité ou non.
 * @return string|null  Code erreur ou null si OK.
 */
function cat_valider_dates_chasse(DateTime $debut, ?DateTime $fin, bool $illimitee): ?string
{
    $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
    $now  = new DateTime('now', $timezone);
    $min  = (clone $now)->modify('-10 years');
    $max  = (clone $now)->modify('+5 years');

    if ($debut < $min || $debut > $max) {
        return 'date_debut_invalide';
    }

    if (!$illimitee) {
        if (!$fin) {
            return 'date_fin_manquante';
        }
        if ($fin <= $debut) {
            return 'date_fin_avant_debut';
        }
    }

    return null;
}

/**
 * Enregistre de manière groupée les dates dans le groupe ACF.
 *
 * @param int           $post_id    ID de la chasse.
 * @param DateTime      $debut      Date de début.
 * @param DateTime|null $fin        Date de fin ou null si illimitée.
 * @param bool          $illimitee  Mode illimité.
 * @return bool                    Succès de la mise à jour.
 */
function cat_enregistrer_dates_chasse(int $post_id, DateTime $debut, ?DateTime $fin, bool $illimitee): bool
{
    $ok1 = mettre_a_jour_sous_champ_group(
        $post_id,
        'caracteristiques',
        'chasse_infos_date_debut',
        $debut->format('Y-m-d H:i:s')
    );
    $ok2 = mettre_a_jour_sous_champ_group(
        $post_id,
        'caracteristiques',
        'chasse_infos_duree_illimitee',
        $illimitee ? 1 : 0
    );
    $ok3 = mettre_a_jour_sous_champ_group(
        $post_id,
        'caracteristiques',
        'chasse_infos_date_fin',
        $illimitee ? '' : $fin->format('Y-m-d')
    );

    return $ok1 && $ok2 && $ok3;
}
