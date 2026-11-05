<?php
defined('ABSPATH') || exit;

$post_id = $args['post_id'] ?? null;
$user_id = $args['user_id'] ?? get_current_user_id();
if (!$post_id) {
    return;
}

$enigme_solution = solution_recuperer_par_objet($post_id, 'enigme');
if (
    $enigme_solution
    && solution_peut_etre_affichee($post_id)
    && utilisateur_peut_voir_solution_enigme($post_id, $user_id)
) {
    echo '<section class="solution-enigme">';
    echo '<h4>' . esc_html__('Solution de l\'énigme', 'chassesautresor-com') . '</h4>';
    echo solution_contenu_html($enigme_solution);
    echo '</section>';
}

$chasse_id = (int) recuperer_id_chasse_associee($post_id);
if (
    $chasse_id
    && solution_chasse_peut_etre_affichee($chasse_id)
    && utilisateur_peut_voir_solution_chasse($chasse_id, $user_id)
) {
    $chasse_solution = solution_recuperer_par_objet($chasse_id, 'chasse');
    if ($chasse_solution) {
        echo '<section class="solution-chasse">';
        echo '<details>';
        echo '<summary>' . esc_html__('Voir la solution de la chasse', 'chassesautresor-com') . '</summary>';
        echo '<h4>' . esc_html__('Solution de la chasse', 'chassesautresor-com') . '</h4>';
        echo solution_contenu_html($chasse_solution);
        echo '</details>';
        echo '</section>';
    }
}
?>
