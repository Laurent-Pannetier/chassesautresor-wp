<?php
defined('ABSPATH') || exit;

$show_header    = $args['show_header'] ?? true;
$header_text    = $args['header_text'] ?? __('Chasses', 'chassesautresor-com');
$mode           = $args['mode'] ?? 'liste';
$grid_class     = $args['grid_class'] ?? ($mode === 'carte' ? 'cards-grid' : 'grille-liste');
$before_items   = $args['before_items'] ?? '';
$after_items    = $args['after_items'] ?? '';
$query          = $args['query'] ?? null;
$chasse_ids     = $args['chasse_ids'] ?? null;
$highlight_label = $args['highlight_label'] ?? '';
$show_progression = $args['show_progression'] ?? true;

if ($query instanceof WP_Query) {
    $chasse_ids = array_map(
        static function ($post) {
            return is_object($post) ? $post->ID : $post;
        },
        $query->posts
    );
} elseif (!is_array($chasse_ids)) {
    return;
}

// 🔒 Filtrer les chasses visibles selon leur statut et l'utilisateur courant
$user_id    = get_current_user_id();
$chasse_ids = array_values(array_filter($chasse_ids, function ($chasse_id) use ($user_id) {
  return chasse_est_visible_pour_utilisateur((int) $chasse_id, $user_id);
}));

?>

<?php if ($show_header) : ?>
<h2><?php echo esc_html($header_text); ?></h2>
<div class="separateur-2"></div>
<?php endif; ?>
<div class="<?php echo esc_attr($grid_class); ?>">
<?php echo $before_items; ?>
  <?php foreach ($chasse_ids as $chasse_id) : ?>
    <?php
    $chasse_id = (int) $chasse_id;

    if ('carte' === $mode) {
        get_template_part('template-parts/chasse/chasse-card-compact', null, [
            'chasse_id' => $chasse_id,
            'show_progression' => $show_progression,
        ]);
        continue;
    }

    if ('a_la_une' === $mode) {
        get_template_part('template-parts/chasse/chasse-featured', null, [
            'chasse_id'       => $chasse_id,
            'highlight_label' => $highlight_label,
        ]);
        continue;
    }

    $est_orga     = est_organisateur();
    $wp_status    = get_post_status($chasse_id);
    $voir_bordure = $est_orga &&
      utilisateur_est_organisateur_associe_a_chasse(get_current_user_id(), $chasse_id) &&
      $wp_status !== 'publish';
    $classe_completion = '';
    if ($voir_bordure) {
      verifier_ou_mettre_a_jour_cache_complet($chasse_id);
      $complet          = (bool) get_field('chasse_cache_complet', $chasse_id);
      $classe_completion = $complet ? 'carte-complete' : 'carte-incomplete';
    }
    get_template_part('template-parts/chasse/chasse-card', null, [
      'chasse_id'        => $chasse_id,
      'completion_class' => $classe_completion,
    ]);
    ?>
  <?php endforeach; ?>
<?php echo $after_items; ?>
</div>
