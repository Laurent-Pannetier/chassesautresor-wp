<?php
defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
    return;
}

$organisateur_id = get_organisateur_from_chasse($chasse_id);
$org_status = $organisateur_id ? get_post_status($organisateur_id) : '';
$titre_bloc = $org_status === 'pending'
    ? "traitement d'une création de chasse"
    : "demande de validation nouvelle chasse";
?>
<section class="bloc-traitement-validation-chasse">
  <h2><?php echo esc_html($titre_bloc); ?></h2>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="form-traitement-validation-chasse">
    <?php wp_nonce_field('validation_admin_' . $chasse_id, 'validation_admin_nonce'); ?>
    <input type="hidden" name="action" value="traiter_validation_chasse">
    <input type="hidden" name="chasse_id" value="<?php echo esc_attr($chasse_id); ?>">
    <div class="boutons">
      <button type="submit" name="validation_admin_action" value="valider" class="btn btn-valider">✅ Valider la chasse</button>
      <button type="submit" name="validation_admin_action" value="correction" class="btn btn-correction">✍️ Correction</button>
      <button type="submit" name="validation_admin_action" value="bannir" class="btn btn-bannir">❌ Bannir</button>
      <button type="submit" name="validation_admin_action" value="supprimer" class="btn btn-warning" onclick="return confirm('Supprimer cette chasse ?');">🗑️ Supprimer</button>
    </div>
  </form>
</section>
