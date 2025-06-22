<?php
defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
  return;
}

$titre = get_the_title($chasse_id);
$champTitreParDefaut = 'nouvelle chasse';
$isTitreParDefaut = strtolower(trim($titre)) === strtolower($champTitreParDefaut);


// Champs ACF principaux
$caracteristiques = get_field('caracteristiques', $chasse_id);
$champs_caches = get_field('champs_caches', $chasse_id);

// Champs individuels
$lot = $caracteristiques['chasse_infos_recompense_texte'] ?? '';
$titre_recompense = $caracteristiques['chasse_infos_recompense_titre'] ?? '';
$valeur_recompense = $caracteristiques['chasse_infos_recompense_valeur'] ?? '';
$cout_points = $caracteristiques['chasse_infos_cout_points'] ?? 0;
$date_debut = $caracteristiques['chasse_infos_date_debut'] ?? null;
$date_fin = $caracteristiques['chasse_infos_date_fin'] ?? null;
$illimitee = $caracteristiques['chasse_infos_duree_illimitee'] ?? false;
$nb_max = $caracteristiques['chasse_infos_nb_max_gagants'] ?? 0;

// Champs cachés
$date_decouverte = $champs_caches['chasse_cache_date_decouverte'] ?? null;
$current_stored_statut = $champs_caches['chasse_cache_statut'] ?? null;

// Données supplémentaires
$description = get_field('chasse_principale_description', $chasse_id);
$texte_complet = wp_strip_all_tags($description);
$extrait = wp_trim_words($texte_complet, 60, '...');
$est_tronque = ($extrait !== $texte_complet);

$image_raw = get_field('chasse_principale_image', $chasse_id);
$image_id = is_array($image_raw) ? ($image_raw['ID'] ?? null) : $image_raw;
$image_url = $image_id ? wp_get_attachment_image_src($image_id, 'large')[0] : null;

// Enigmes
$enigmes_associees = recuperer_enigmes_associees($chasse_id);
$total_enigmes = count($enigmes_associees);
$nb_joueurs = 0;

// Dates
$date_debut_formatee = formater_date($date_debut);
$date_fin_formatee = $illimitee ? 'Illimitée' : ($date_fin ? formater_date($date_fin) : 'Non spécifiée');

// Edition
$edition_active = utilisateur_peut_modifier_post($chasse_id);

// Organisateur
$organisateur_id = get_organisateur_from_chasse($chasse_id);
$organisateur_nom = $organisateur_id ? get_the_title($organisateur_id) : get_the_author();


if (current_user_can('administrator')) {
  $chasse_id = get_the_ID();

  error_log("📦 [TEST] Statut stocké (admin) : " . get_field('champs_caches')['chasse_cache_statut']);

  verifier_ou_recalculer_statut_chasse($chasse_id);


  mettre_a_jour_statuts_chasse($chasse_id);

  error_log("✅ [TEST] Recalcul exécuté via mettre_a_jour_statuts_chasse($chasse_id)");
}


?>


<section class="chasse-section-intro">

  <div class="chasse-fiche-container flex-row">
    <?php
    $cache = get_field('champs_caches', $chasse_id);
    $statut = get_field('champs_caches')['chasse_cache_statut'] ?? 'revision';
    if ($statut !== 'revision') :
    ?>
      <span class="badge-statut statut-<?= esc_attr($statut); ?>" data-post-id="<?= esc_attr($chasse_id); ?>">
        <?= ucfirst(str_replace('_', ' ', $statut)); ?>
      </span>
    <?php endif; ?>

    <!-- 🔧 Bouton panneau édition -->
    <?php if ($edition_active) : ?>
      <div class="header-actions-droite">
        <button id="toggle-mode-edition-chasse" type="button"
          class="bouton-edition-toggle"
          data-cpt="chasse"
          aria-label="Activer le mode édition">
          <i class="fa-solid fa-sliders"></i>
        </button>
      </div>
    <?php endif; ?>

    <!-- 📷 Image principale -->
    <div class="champ-chasse champ-img <?= empty($image_url) ? 'champ-vide' : 'champ-rempli'; ?>"
      data-champ="chasse_principale_image"
      data-cpt="chasse"
      data-post-id="<?= esc_attr($chasse_id); ?>">

      <div class="champ-affichage">
        <div class="header-chasse__image">
          <img src="<?= esc_url($image_url); ?>"
            alt="Image de la chasse"
            class="chasse-image visuel-cpt"
            data-cpt="chasse"
            data-post-id="<?= esc_attr($chasse_id); ?>"
            style="width:100%; height:auto;" />
        </div>
      </div>

      <input type="hidden" class="champ-input" value="<?= esc_attr($image_id); ?>">
      <div class="champ-feedback"></div>
    </div>


    <!-- 📟 Informations -->
    <div class="chasse-details-wrapper">

      <!-- Titre dynamique -->
      <h1 class="titre-objet header-chasse"
        data-cpt="chasse"
        data-post-id="<?= esc_attr($chasse_id); ?>">
        <?= esc_html($titre); ?>
      </h1>

      <?php if ($organisateur_id): ?>
        <p class="txt-small auteur-organisateur">
          Par <a href="<?= get_permalink($organisateur_id); ?>"><?= esc_html($organisateur_nom); ?></a>
        </p>
      <?php endif; ?>

      <div class="meta-row svg-xsmall">
        <div class="meta-regular">
          <?php echo get_svg_icon('enigme'); ?> <?= esc_html($total_enigmes); ?> énigme<?= ($total_enigmes > 1 ? 's' : ''); ?> —
          <?php echo get_svg_icon('participants'); ?><?= esc_html($nb_joueurs); ?> joueur<?= ($nb_joueurs > 1 ? 's' : ''); ?>
        </div>
        <div class="meta-etiquette">
          <?php echo get_svg_icon('calendar'); ?>
          <span class="chasse-date-plage">
            Du <span class="date-debut"><?= esc_html($date_debut_formatee); ?></span> →
            <span class="date-fin"><?= esc_html($date_fin_formatee); ?></span>
          </span>

        </div>
      </div>

      <div class="separateur-3">
        <div class="trait-gauche"></div>
        <div class="icone-svg"></div>
        <div class="trait-droite"></div>
      </div>

      <div class="bloc-metas-inline">

        <div class="prix chasse-prix" data-cpt="chasse" data-post-id="<?= esc_attr($chasse_id); ?>">
          <span class="cout-affichage" data-cout="<?= esc_attr((int)$cout_points); ?>">
            <?php if ((int)$cout_points === 0) : ?>
              <?php echo get_svg_icon('free'); ?>
              <span class="texte-cout">Gratuit</span>
            <?php else : ?>
              <?php echo get_svg_icon('unlock'); ?>
              <span class="valeur-cout"><?= esc_html($cout_points); ?></span>
              <span class="prix-devise">pts</span>
            <?php endif; ?>
          </span>
        </div>
      </div>
      <?php if (!empty($titre_recompense) && (float) $valeur_recompense > 0) : ?>
        <div class="chasse-lot" aria-live="polite">
          <?php echo get_svg_icon('trophee'); ?>
          <?= esc_html($titre_recompense); ?> — <?= esc_html($valeur_recompense); ?> €
        </div>
      <?php endif; ?>

      <div class="bloc-discret">
        <?php if ($extrait) : ?>
          <p class="chasse-intro-extrait liste-elegante">
            <strong>Présentation :</strong> <?= esc_html($extrait); ?>
            <?php if ($est_tronque) : ?>
              <a href="#chasse-description">Voir les détails</a>
            <?php endif; ?>
          </p>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>

<?php if ($edition_active) : ?>
  <!-- 
    Templates SVG invisibles pour utilisation dynamique en JavaScript.
    Affichés uniquement en mode édition pour éviter de surcharger la page publique.
  -->
  <div id="svg-icons" style="display: none;">
    <template id="icon-free">
      <?php echo get_svg_icon('free'); ?>
    </template>
    <template id="icon-unlock">
      <?php echo get_svg_icon('unlock'); ?>
    </template>
  </div>
<?php endif; ?>

<?php
// Inclure le panneau si édition active
if ($edition_active) {
  get_template_part('template-parts/chasse/chasse-edition-main', null, [
    'chasse_id' => $chasse_id
  ]);
}
?>