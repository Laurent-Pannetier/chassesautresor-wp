<?php
/**
 * Template Name: Confirmation Organisateur
 * Description: Valide la demande de creation d'un profil organisateur via un lien email.
 */

defined('ABSPATH') || exit;

$user_id = isset($_GET['user']) ? intval($_GET['user']) : 0;
$token   = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

$success = false;
if ($user_id && $token) {
    $cpt_id = confirmer_demande_organisateur($user_id, $token);
    if ($cpt_id) {
        $success = true;
    }
}

get_header();
?>
<div class="conteneur-confirmation">
<?php if ($success) : ?>
    <p>Votre inscription est confirmée. Vous pouvez maintenant vous connecter.</p>
<?php else : ?>
    <p>Token invalide ou expiré.</p>
<?php endif; ?>
</div>
<?php get_footer();
