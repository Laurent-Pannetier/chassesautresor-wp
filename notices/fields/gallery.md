🧹 Notice – Insertion d’un champ ACF gallery avec édition via panneau latéral

✅ Objectif

Utiliser un champ ACF gallery pour stocker plusieurs images

Éditer ce champ via un panneau latéral dédié (pas de media modal inline)

Intégration propre, modulaire, sans duplication

Basée sur acf_form() avec rechargement de la page

💪 Étapes d’implémentation

1. Créer le champ dans ACF

Type : Galerie (gallery)

Nom du champ : ex. mon_cpt_images

Groupe de champs : ajouter à la racine, pas dans un group

Retour : ID (recommandé pour cohérence)

Emplacement : CPT = mon_cpt

2. HTML du résumé (edition-*.php)

<?php
$images = get_field('mon_cpt_images', $post_id);
$has_images = is_array($images) && count($images) > 0;
?>

<li class="champ-<?= esc_attr($cpt); ?> champ-img <?= $has_images ? 'champ-rempli' : 'champ-vide'; ?>"
    data-champ="mon_cpt_images"
    data-cpt="<?= esc_attr($cpt); ?>"
    data-post-id="<?= esc_attr($post_id); ?>">

  Une galerie d’images

  <button type="button"
          class="champ-modifier ouvrir-panneau-images"
          data-champ="mon_cpt_images"
          data-cpt="<?= esc_attr($cpt); ?>"
          data-post-id="<?= esc_attr($post_id); ?>">
    ✏️
  </button>
</li>

3. Fichier panneau : template-parts/mon-cpt/panneaux/panneau-images-mon-cpt.php

<?php
defined('ABSPATH') || exit;

$post_id = $args['post_id'] ?? null;
if (!$post_id || get_post_type($post_id) !== 'mon_cpt') return;
?>

<div id="panneau-images-mon-cpt" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true">
  <div class="panneau-lateral__contenu">

    <div class="panneau-lateral__header">
      <button type="button" class="panneau-fermer" aria-label="Fermer le panneau">✖️</button>
      <h2>Modifier la galerie</h2>
    </div>

    <?php
    acf_form([
      'post_id'             => $post_id,
      'fields'              => ['mon_cpt_images'],
      'form'                => true,
      'submit_value'        => '📅 Enregistrer',
      'html_submit_button'  => '<div class="panneau-lateral__actions"><button type="submit" class="bouton-enregistrer-liens">%s</button></div>',
      'html_before_fields'  => '<div class="champ-wrapper">',
      'html_after_fields'   => '</div>',
      'return'              => get_permalink($post_id) . '#images-galerie',
      'updated_message'     => __('Galerie mise à jour.', 'chassesautresor')
    ]);
    ?>
  </div>
</div>

4. JS dans mon-cpt-edit.js

// ================================
// 🖼️ Panneau galerie (ACF gallery)
// ================================
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.ouvrir-panneau-images');
  if (!btn || btn.dataset.cpt !== 'mon_cpt') return;

  const panneau = document.getElementById('panneau-images-mon-cpt');
  if (!panneau) return;

  document.querySelectorAll('.panneau-lateral.ouvert, .panneau-lateral-liens.ouvert').forEach((p) => {
    p.classList.remove('ouvert');
    p.setAttribute('aria-hidden', 'true');
  });

  panneau.classList.add('ouvert');
  document.body.classList.add('panneau-ouvert');
  panneau.setAttribute('aria-hidden', 'false');
});

document.querySelector('#panneau-images-mon-cpt .panneau-fermer')?.addEventListener('click', () => {
  const panneau = document.getElementById('panneau-images-mon-cpt');
  panneau.classList.remove('ouvert');
  document.body.classList.remove('panneau-ouvert');
  panneau.setAttribute('aria-hidden', 'true');
});

5. Ne pas oublier

Élément

Obligatoire

acf_form_head()

✅ oui (avant get_header())

Inclusion du panneau via get_template_part()

✅ oui

JS dans le bon fichier *-edit.js

✅ oui

Comportement d'ouverture/fermeture harmonisé

✅ oui

aria-hidden et classe .ouvert

✅ requis pour accessibilité

