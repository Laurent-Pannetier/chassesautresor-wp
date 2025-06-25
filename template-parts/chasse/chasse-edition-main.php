<?php

/**
 * Template Part: Panneau d'édition frontale d'une chasse
 * Requiert : $args['chasse_id']
 */

defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
  return;
}

$peut_modifier = utilisateur_peut_voir_panneau($chasse_id);
$peut_editer   = utilisateur_peut_editer_champs($chasse_id);

$image = get_field('chasse_principale_image', $chasse_id);
$description = get_field('chasse_principale_description', $chasse_id);
$titre = get_the_title($chasse_id);
$liens = get_field('chasse_principale_liens', $chasse_id);
$carac = get_field('caracteristiques', $chasse_id);

$recompense = $carac['chasse_infos_recompense_texte'] ?? '';
$valeur     = $carac['chasse_infos_recompense_valeur'] ?? '';
$cout       = $carac['chasse_infos_cout_points'] ?? '';
$date_debut = $carac['chasse_infos_date_debut'] ?? '';
$date_fin   = $carac['chasse_infos_date_fin'] ?? '';

// 🎯 Conversion des dates pour les champs <input>
$date_debut_obj = convertir_en_datetime($date_debut);
$date_debut_iso = $date_debut_obj ? $date_debut_obj->format('Y-m-d\TH:i') : '';

$date_fin_obj = convertir_en_datetime($date_fin);
$date_fin_iso = $date_fin_obj ? $date_fin_obj->format('Y-m-d') : '';
$illimitee  = $carac['chasse_infos_duree_illimitee'] ?? false;
$nb_max     = $carac['chasse_infos_nb_max_gagants'] ?? 1;

$champTitreParDefaut = 'nouvelle chasse'; // À adapter si besoin
$isTitreParDefaut = strtolower(trim($titre)) === strtolower($champTitreParDefaut);

?>

<?php if ($peut_modifier) : ?>
  <section class="edition-panel edition-panel-chasse edition-panel-modal" data-cpt="chasse" data-post-id="<?= esc_attr($chasse_id); ?>">
    <div id="erreur-global" style="display:none; background:red; color:white; padding:5px; text-align:center; font-size:0.9em;"></div>

    <div class="edition-panel-header">
      <h2><i class="fa-solid fa-sliders"></i> Paramètres</h2>
      <button type="button" class="panneau-fermer" aria-label="Fermer les paramètres">✖</button>
    </div>

    <div class="edition-tabs">
      <button class="edition-tab active" data-target="chasse-tab-param">Paramètres</button>
      <button class="edition-tab" data-target="chasse-tab-stats">Statistiques</button>
      <button class="edition-tab" data-target="chasse-tab-classement">Classement</button>
      <button class="edition-tab" data-target="chasse-tab-indices">Indices</button>
    </div>

    <div id="chasse-tab-param" class="edition-tab-content active">
      <i class="fa-solid fa-sliders tab-watermark" aria-hidden="true"></i>
      <div class="edition-panel-header">
        <h2><i class="fa-solid fa-sliders"></i> Paramètres</h2>
      </div>
      <div class="edition-panel-body">

      <div class="edition-panel-section edition-panel-section-ligne">
        <h3 class="section-title">
          <i class="fa-solid fa-gear" aria-hidden="true"></i>
          Paramètres de la chasse
        </h3>

        <div class="section-content">
          <div class="resume-blocs-grid deux-col-wrapper">

            <!-- SECTION 1 : Champs obligatoires -->
            <div class="resume-bloc resume-obligatoire deux-col-bloc">
              <h3>Champs obligatoires</h3>
              <ul class="resume-infos">

                <!-- Titre -->
                <li class="champ-chasse champ-titre <?= ($isTitreParDefaut ? 'champ-vide' : 'champ-rempli'); ?><?= $peut_editer ? '' : ' champ-desactive'; ?>"
                  data-champ="post_title"
                  data-cpt="chasse"
                  data-post-id="<?= esc_attr($chasse_id); ?>">

                  <div class="champ-affichage">
                    <label for="champ-titre-chasse">Titre de la chasse</label>
                    <?php if ($peut_editer) : ?>
                      <button type="button" class="champ-modifier" aria-label="Modifier le titre">
                        ✏️
                      </button>
                    <?php endif; ?>
                  </div>

                  <div class="champ-edition" style="display: none;">
                    <input type="text" class="champ-input" maxlength="70" value="<?= esc_attr($titre); ?>" id="champ-titre-chasse" <?= $peut_editer ? '' : 'disabled'; ?>>
                    <button type="button" class="champ-enregistrer">✓</button>
                    <button type="button" class="champ-annuler">✖</button>
                  </div>

                  <div class="champ-feedback"></div>
                </li>
                
                <!-- Description -->
                <li class="champ-chasse champ-description <?= empty($description) ? 'champ-vide' : 'champ-rempli'; ?><?= $peut_editer ? '' : ' champ-desactive'; ?>"
                  data-champ="chasse_principale_description"
                  data-cpt="chasse"
                  data-post-id="<?= esc_attr($chasse_id); ?>">
                  Une description
                  <?php if ($peut_editer) : ?>
                    <button type="button"
                      class="champ-modifier ouvrir-panneau-description"
                      data-cpt="chasse"
                      data-champ="chasse_principale_description"
                      data-post-id="<?= esc_attr($chasse_id); ?>"
                      aria-label="Modifier la description">✏️</button>
                  <?php endif; ?>
                </li>

                <!-- Image -->
                <li class="champ-chasse champ-img <?= empty($image) ? 'champ-vide' : 'champ-rempli'; ?><?= $peut_editer ? '' : ' champ-desactive'; ?>"
                  data-champ="chasse_principale_image"
                  data-cpt="chasse"
                  data-post-id="<?= esc_attr($chasse_id); ?>">
                  Une image principale
                  <?php if ($peut_editer) : ?>
                    <button type="button"
                      class="champ-modifier"
                      data-champ="chasse_principale_image"
                      data-cpt="chasse"
                      data-post-id="<?= esc_attr($chasse_id); ?>"
                      aria-label="Modifier l’image">✏️</button>
                  <?php endif; ?>
                </li>

              </ul>
            </div>

            <!-- SECTION 2 : Champs recommandés -->
            <div class="resume-bloc resume-facultatif deux-col-bloc">
              <h3>Facultatif mais recommandé</h3>
              <ul class="resume-infos">

                <!-- Récompense -->
                <li class="champ-chasse champ-rempli<?= $peut_editer ? '' : ' champ-desactive'; ?>" data-champ="caracteristiques_chasse_infos_recompense_valeur" data-cpt="chasse" data-post-id="<?= esc_attr($chasse_id); ?>">
                  Récompense
                  <?php if ($peut_editer) : ?>

                    <button type="button" class="champ-modifier ouvrir-panneau-recompense" data-champ="caracteristiques_chasse_infos_recompense_valeur" data-cpt="chasse" data-post-id="<?= esc_attr($chasse_id); ?>" aria-label="Modifier la récompense">✏️</button>

                  <?php endif; ?>
                </li>

                <!-- Liens -->
                <li class="champ-chasse resume-ligne champ-liens <?= empty($liens) ? 'champ-vide' : 'champ-rempli'; ?>"
                  data-champ="chasse_principale_liens"
                  data-cpt="chasse"
                  data-post-id="<?= esc_attr($chasse_id); ?>">

                  <span class="champ-label">Sites et réseaux dédiés à cette chasse</span>

                  <?php if ($peut_modifier) : ?>

                    <button type="button"
                      class="champ-modifier ouvrir-panneau-liens"
                      data-champ="chasse_principale_liens"
                      data-cpt="chasse"
                      data-post-id="<?= esc_attr($chasse_id); ?>"
                      aria-label="Configurer les liens publics">✏️</button>
                  <?php endif; ?>

                  <div class="champ-feedback"></div>
                </li>
              </ul>
            </div>

            <!-- SECTION 3 : Caractéristiques -->
            <div class="resume-bloc resume-technique">
              <h3>Caractéristiques</h3>
              <ul class="resume-infos">

                <!-- Date de début (édition inline) -->
                <li class="champ-chasse champ-date-debut<?= $peut_editer ? '' : ' champ-desactive'; ?>"
                  data-champ="caracteristiques.chasse_infos_date_debut"
                  data-cpt="chasse"
                  data-post-id="<?= esc_attr($chasse_id); ?>">

                  <label for="chasse-date-debut">Date et heure de début</label>
                  <input type="datetime-local"
                    id="chasse-date-debut"
                    name="chasse-date-debut"
                    value="<?= esc_attr($date_debut_iso); ?>"
                    class="champ-inline-date champ-date-edit" <?= $peut_editer ? '' : 'disabled'; ?> required />
                  <div id="erreur-date-debut" class="message-erreur" style="display:none; color:red; font-size:0.9em; margin-top:5px;"></div>

                </li>

                <!-- Date de fin -->
                <li class="champ-chasse champ-date-fin<?= $peut_editer ? '' : ' champ-desactive'; ?>"
                  data-champ="caracteristiques.chasse_infos_date_fin"
                  data-cpt="chasse"
                  data-post-id="<?= esc_attr($chasse_id); ?>">

                  <label for="chasse-date-fin">Date de fin</label>
                  <input type="date"
                    id="chasse-date-fin"
                    name="chasse-date-fin"
                    value="<?= esc_attr($date_fin_iso); ?>"
                    class="champ-inline-date champ-date-edit" <?= $peut_editer ? '' : 'disabled'; ?> />
                  <div id="erreur-date-fin" class="message-erreur" style="display:none; color:red; font-size:0.9em; margin-top:5px;"></div>

                  <div class="champ-option-illimitee">
                    <input type="checkbox"
                      id="duree-illimitee"
                      name="duree-illimitee"
                      data-champ="caracteristiques.chasse_infos_duree_illimitee"
                      <?= ($illimitee ? 'checked' : ''); ?> <?= $peut_editer ? '' : 'disabled'; ?>>
                    <label for="duree-illimitee">Durée illimitée</label>
                  </div>

                </li>


                <!-- Coût -->
                <li class="champ-chasse champ-cout-points <?= empty($cout) ? 'champ-vide' : 'champ-rempli'; ?><?= $peut_editer ? '' : ' champ-desactive'; ?>"
                  data-champ="caracteristiques.chasse_infos_cout_points"
                  data-cpt="chasse"
                  data-post-id="<?= esc_attr($chasse_id); ?>">

                  <div class="champ-edition" style="display: flex; align-items: center;">
                    <label>Coût <span class="txt-small">(points)</span>
                      <button type="button" class="bouton-aide-points open-points-modal" aria-label="En savoir plus sur les points"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></button>
                    </label>

                    <input type="number"
                      class="champ-input champ-cout"
                      min="0"
                      step="1"
                      value="<?= esc_attr($cout); ?>"
                      placeholder="0" <?= $peut_editer ? '' : 'disabled'; ?> />

                    <div class="champ-option-gratuit" style="margin-left: 15px;">
                      <input type="checkbox"
                        id="cout-gratuit"
                        name="cout-gratuit"
                        <?= ((int)$cout === 0) ? 'checked' : ''; ?> <?= $peut_editer ? '' : 'disabled'; ?>>
                      <label for="cout-gratuit">Gratuit</label>
                    </div>
                  </div>

                  <div class="champ-feedback"></div>
                </li>


                <!-- Nombre de gagnants -->
                <li class="champ-chasse champ-nb-gagnants <?= empty($nb_max) ? 'champ-vide' : 'champ-rempli'; ?><?= $peut_editer ? '' : ' champ-desactive'; ?>"
                  data-champ="caracteristiques.chasse_infos_nb_max_gagants"
                  data-cpt="chasse"
                  data-post-id="<?= esc_attr($chasse_id); ?>">

                  <label for="chasse-nb-gagnants">Nb gagnants</label>

                  <input type="number"
                    id="chasse-nb-gagnants"
                    name="chasse-nb-gagnants"
                    value="<?= esc_attr($nb_max); ?>"
                    min="1"
                    class="champ-inline-nb champ-nb-edit"
                    <?= ($peut_editer && $nb_max != 0) ? '' : 'disabled'; ?> />

                  <div class="champ-option-illimitee ">
                    <input type="checkbox"
                      id="nb-gagnants-illimite"
                      name="nb-gagnants-illimite"
                      <?= ($nb_max == 0 ? 'checked' : ''); ?> <?= $peut_editer ? '' : 'disabled'; ?>
                      data-champ="caracteristiques.chasse_infos_nb_max_gagants">
                    <label for="nb-gagnants-illimite">Illimité</label>
                  </div>

                  <div id="erreur-nb-gagnants" class="message-erreur" style="display:none; color:red; font-size:0.9em; margin-top:5px;"></div>
                </li>

              </ul>
            </div>

          </div>
        </div>
      </div>


    </div> <!-- .edition-panel-body -->
    </div> <!-- #chasse-tab-param -->

    <div id="chasse-tab-stats" class="edition-tab-content" style="display:none;">
      <i class="fa-solid fa-chart-column tab-watermark" aria-hidden="true"></i>
      <div class="edition-panel-header">
        <h2><i class="fa-solid fa-chart-column"></i> Statistiques</h2>
      </div>
      <p class="edition-placeholder">La section « Statistiques » sera bientôt disponible.</p>
    </div>

    <div id="chasse-tab-classement" class="edition-tab-content" style="display:none;">
      <i class="fa-solid fa-ranking-star tab-watermark" aria-hidden="true"></i>
      <div class="edition-panel-header">
        <h2><i class="fa-solid fa-ranking-star"></i> Classement</h2>
      </div>
      <p class="edition-placeholder">La section « Classement » sera bientôt disponible.</p>
    </div>

    <div id="chasse-tab-indices" class="edition-tab-content" style="display:none;">
      <i class="fa-regular fa-lightbulb tab-watermark" aria-hidden="true"></i>
      <div class="edition-panel-header">
        <h2><i class="fa-regular fa-lightbulb"></i> Indices</h2>
      </div>
      <p class="edition-placeholder">La section « Indices » sera bientôt disponible.</p>
    </div>

    <div class="edition-panel-footer"></div>
  </section>
<?php endif; ?>

<?php
// 📎 Panneaux contextuels (description, liens, etc.)
get_template_part('template-parts/chasse/panneaux/chasse-edition-description', null, [
  'chasse_id' => $chasse_id
]);
get_template_part('template-parts/chasse/panneaux/chasse-edition-recompense', null, [
  'chasse_id' => $chasse_id
]);
get_template_part('template-parts/chasse/panneaux/chasse-edition-liens', null, [
  'chasse_id' => $chasse_id
]);
?>