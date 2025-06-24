<?php

/**
 * Template Part: Panneau d'édition frontale d'une énigme
 * Requiert : $args['enigme_id']
 */

defined('ABSPATH') || exit;

$enigme_id = $args['enigme_id'] ?? null;
if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') {
  return;
}

$peut_modifier = utilisateur_peut_voir_panneau($enigme_id);
$titre = get_the_title($enigme_id);
$titre_defaut = TITRE_DEFAUT_ENIGME;
$isTitreParDefaut = strtolower(trim($titre)) === strtolower($titre_defaut);

$visuel = get_field('enigme_visuel_image', $enigme_id); // champ "gallery" → tableau d’IDs
$has_images = is_array($visuel) && count($visuel) > 0;
$legende = get_field('enigme_visuel_legende', $enigme_id);
$texte = get_field('enigme_visuel_texte', $enigme_id);
$reponse = get_field('enigme_reponse_bonne', $enigme_id);
$casse = get_field('enigme_reponse_casse', $enigme_id);
$tentative = get_field('enigme_tentative', $enigme_id);
$max = $tentative['enigme_tentative_max'] ?? 5;
$cout = $tentative['enigme_tentative_cout_points'] ?? '';
$mode_validation = get_field('enigme_mode_validation', $enigme_id) ?? 'aucune';
$style = get_field('enigme_style_affichage', $enigme_id);
$solution = get_field('enigme_solution', $enigme_id);
$date_raw = get_field('enigme_acces_date', $enigme_id);
$date_deblocage = $date_raw ? substr($date_raw, 0, 10) : '';


$chasse = get_field('enigme_chasse_associee', $enigme_id);
$chasse_id = is_array($chasse) ? $chasse[0] : null;
$chasse_title = $chasse_id ? get_the_title($chasse_id) : '';

$variantes = get_field('enigme_reponse_variantes', $enigme_id);
$nb_variantes = 0;

if (is_array($variantes)) {
  foreach ($variantes as $key => $variante) {
    foreach ($variante as $champ => $valeur) {
      if (strpos($champ, 'texte_') === 0) {
        $suffixe = substr($champ, 6);
        $texte = trim($valeur);
        $message = trim($variante["message_$suffixe"] ?? '');
        if ($texte && $message) {
          $nb_variantes++;
        }
      }
    }
  }
}
$has_variantes = ($nb_variantes > 0);


?>

<?php if ($peut_modifier) : ?>
  <section class="edition-panel edition-panel-enigme edition-panel-modal" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
    <div id="erreur-global"
      style="display:none; background:red; color:white; padding:5px; text-align:center; font-size:0.9em;"></div>

    <div class="edition-panel-header">
      <h2><i class="fa-solid fa-sliders"></i> Paramètres</h2>

      <!-- ✅ Ajout du champ Style ici -->
      <div class="champ-enigme champ-style" data-champ="enigme_style_affichage" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>" style="margin-top: 8px;">
        <label for="select-style-affichage" style="font-weight: normal; font-size: 0.9em;">Style d'affichage :</label>
        <select id="select-style-affichage" class="champ-input" style="margin-left: 10px;">
          <option value="defaut" <?= $style === 'defaut' ? 'selected' : ''; ?>>Défaut</option>
          <option value="pirate" <?= $style === 'pirate' ? 'selected' : ''; ?>>Pirate</option>
          <option value="vintage" <?= $style === 'vintage' ? 'selected' : ''; ?>>Vintage</option>
        </select>
      </div>
      <button type="button" class="panneau-fermer" aria-label="Fermer les paramètres">✖</button>
    </div>

    <div class="edition-tabs">
      <button class="edition-tab active" data-target="enigme-tab-param">Paramètres</button>
      <button class="edition-tab" data-target="enigme-tab-stats">Statistiques</button>
      <button class="edition-tab" data-target="enigme-tab-soumission">Soumission</button>
      <button class="edition-tab" data-target="enigme-tab-indices">Indices</button>
      <button class="edition-tab" data-target="enigme-tab-solution">Solution</button>
    </div>

<div id="enigme-tab-param" class="edition-tab-content active">
      <i class="fa-solid fa-sliders tab-watermark" aria-hidden="true"></i>
      <div class="edition-panel-header">
        <h2><i class="fa-solid fa-sliders"></i> Paramètres</h2>
      </div>
      <div class="edition-panel-body edition-panel-section">
      <div class="resume-blocs-grid deux-col-wrapper">
        <div class="resume-bloc resume-obligatoire deux-col-bloc">

          <h3>Champs obligatoires</h3>
          <ul class="resume-infos">
            <li class="champ-enigme champ-titre <?= ($isTitreParDefaut ? 'champ-vide' : 'champ-rempli'); ?>"
              data-champ="post_title"
              data-cpt="enigme"
              data-post-id="<?= esc_attr($enigme_id); ?>">

              <div class="champ-affichage">
                <label for="champ-titre-enigme">Titre de l’énigme</label>
                <button type="button"
                  class="champ-modifier"
                  aria-label="Modifier le titre">
                  ✏️
                </button>
              </div>

              <div class="champ-edition" style="display: none;">
                <input type="text"
                  class="champ-input"
                  maxlength="80"
                  value="<?= esc_attr($titre); ?>"
                  id="champ-titre-enigme">
                <button type="button" class="champ-enregistrer">✓</button>
                <button type="button" class="champ-annuler">✖</button>
              </div>

              <div class="champ-feedback"></div>
            </li>

            <?php
            $has_images_utiles = enigme_a_une_image($enigme_id);
            ?>
            <li class="champ-enigme champ-img <?= $has_images_utiles ? 'champ-rempli' : 'champ-vide'; ?>"
              data-champ="enigme_visuel_image"
              data-cpt="enigme"
              data-post-id="<?= esc_attr($enigme_id); ?>"
              data-rempli="<?= $has_images_utiles ? '1' : '0'; ?>">

              Image(s)

              <button
                type="button"
                class="champ-modifier ouvrir-panneau-images"
                data-champ="enigme_visuel_image"
                data-cpt="enigme"
                data-post-id="<?= esc_attr($enigme_id); ?>">
                ✏️
                </button>

            </li>


          </ul>
        </div>

        <!-- SECTION 2 : Champs recommandés -->
        <div class="resume-bloc resume-facultatif deux-col-bloc">
          <h3>Facultatif mais recommandé</h3>
          <ul class="resume-infos">

            <li class="champ-enigme champ-wysiwyg" data-champ="enigme_visuel_texte" data-cpt="enigme"
              data-post-id="<?= esc_attr($enigme_id); ?>">
              Un texte principal
              <button type="button" class="champ-modifier ouvrir-panneau-description" data-champ="enigme_visuel_texte"
                data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
                ✏️
              </button>
            </li>

            <li class="champ-enigme champ-texte" data-champ="enigme_visuel_legende" data-cpt="enigme"
              data-post-id="<?= esc_attr($enigme_id); ?>">

              <div class="champ-affichage">
                Un sous-titre
                <button type="button" class="champ-modifier" aria-label="Modifier la légende">✏️</button>
              </div>

              <div class="champ-edition" style="display: none;">
                <input type="text" class="champ-input" maxlength="100" value="<?= esc_attr($legende); ?>"
                  placeholder="Ajouter une légende (max 100 caractères)">
                <button type="button" class="champ-enregistrer">✓</button>
                <button type="button" class="champ-annuler">✖</button>
              </div>

              <div class="champ-feedback"></div>
            </li>
          </ul>
        </div>

        <!-- Caractéristiques -->
        <div class="resume-bloc resume-technique">
          <h3>Caractéristiques</h3>
          <div class="resume-infos">

            <!-- Mode de validation -->
            <div class="champ-enigme champ-mode-validation" data-champ="enigme_mode_validation" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
              <fieldset>
                <legend>Validation de l’énigme</legend>
                <label><input type="radio" name="acf[enigme_mode_validation]" value="aucune" <?= $mode_validation === 'aucune' ? 'checked' : ''; ?>> Aucune validation</label>
                <label><input type="radio" name="acf[enigme_mode_validation]" value="manuelle" <?= $mode_validation === 'manuelle' ? 'checked' : ''; ?>> Validation manuelle</label>
                <label><input type="radio" name="acf[enigme_mode_validation]" value="automatique" <?= $mode_validation === 'automatique' ? 'checked' : ''; ?>> Validation automatique</label>
                <div class="champ-explication champ-explication-validation" aria-live="polite"></div>
              </fieldset>
            </div>

            <!-- Accès à l'énigme -->
            <fieldset class="groupe-champ champ-groupe-acces">
              <legend>Condition d’accès</legend>

              <?php
              $condition = get_field('enigme_acces_condition', $enigme_id) ?? 'immediat';
              $enigmes_possibles = enigme_get_liste_prerequis_possibles($enigme_id);

              $options = [
                'immediat'        => 'Immédiat',
                'date_programmee' => 'Date programmée',
              ];

              if (!empty($enigmes_possibles)) {
                $options['pre_requis'] = 'Pré-requis';
              }
              ?>
              <div class="champ-enigme champ-access"
                data-champ="enigme_acces_condition"
                data-cpt="enigme"
                data-post-id="<?= esc_attr($enigme_id); ?>">

                <?php foreach ($options as $val => $label) : ?>
                  <label style="display:inline-block; margin-right: 15px;">
                    <input type="radio" name="acf[enigme_acces_condition]"
                      value="<?= esc_attr($val); ?>"
                      <?= $condition === $val ? 'checked' : ''; ?>>
                    <?= esc_html($label); ?>
                  </label>
                <?php endforeach; ?>

                <div class="champ-feedback"></div>
              </div>

              <div class="champ-enigme champ-date cache" data-champ="enigme_acces_date" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>" id="champ-enigme-date">
                <label for="enigme-date-deblocage">Date de déblocage</label>
                <input type="date"
                  id="enigme-date-deblocage"
                  name="enigme-date-deblocage"
                  value="<?= esc_attr($date_deblocage); ?>"
                  class="champ-inline-date champ-date-edit" />
                <div class="champ-feedback champ-date-feedback" style="display:none;"></div>
              </div>

              <div class="champ-enigme champ-pre-requis cache"
                data-champ="enigme_acces_pre_requis"
                data-cpt="enigme"
                data-post-id="<?= esc_attr($enigme_id); ?>"
                id="champ-enigme-pre-requis"
                data-vide="<?= empty($enigmes_possibles) ? '1' : '0'; ?>">

                <label>Pré-requis</label>

                <?php
                $enigmes_possibles = enigme_get_liste_prerequis_possibles($enigme_id);
                $prerequis_actuels = get_field('enigme_acces_pre_requis', $enigme_id, false) ?? [];
                if (!is_array($prerequis_actuels)) {
                  $prerequis_actuels = [$prerequis_actuels];
                }

                ?>
                <ul class="liste-pre-requis">

                  <small class="champ-aide">
                    Seules les autres énigmes de cette chasse, avec une validation manuelle ou automatique, peuvent être sélectionnées.
                  </small>

                  <?php if (empty($enigmes_possibles)) : ?>
                    <li><em>Aucune autre énigme disponible comme prérequis.</em></li>
                  <?php else : ?>
                    <?php foreach ($enigmes_possibles as $id => $titre) :
                      $checked = in_array($id, $prerequis_actuels);
                    ?>
                      <li>
                        <label>
                          <input type="checkbox" value="<?= esc_attr($id); ?>" <?= $checked ? 'checked' : ''; ?>>
                          <?= esc_html($titre); ?>
                        </label>
                      </li>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </ul>


                <div class="champ-feedback"></div>
              </div>
            </fieldset>

            <!-- Tentatives -->
            <fieldset class="groupe-champ champ-groupe-tentatives">
              <legend>Gestion des tentatives</legend>

              <div class="champ-enigme champ-cout-points <?= empty($cout) ? 'champ-vide' : 'champ-rempli'; ?>" data-champ="enigme_tentative.enigme_tentative_cout_points" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
                <div class="champ-edition" style="display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap;">

                  <!-- Coût en points -->
                  <div style="display: flex; flex-direction: column;">
                    <label>Coût <span class="txt-small">(points)</span>
                      <button type="button" class="bouton-aide-points open-points-modal" aria-label="En savoir plus sur les points">
                        <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                      </button>
                    </label>
                    <input type="number" class="champ-input champ-cout" min="0" step="1" value="<?= esc_attr($cout); ?>" placeholder="0" />
                  </div>

                  <!-- Option gratuit -->
                  <div class="champ-option-gratuit" style="margin-left: 5px;">
                    <?php
                    $cout_normalise = trim((string)$cout); // on nettoie
                    $is_gratuit = $cout_normalise === '' || $cout_normalise === '0' || (int)$cout === 0;
                    ?>
                    <input type="checkbox" id="cout-gratuit-enigme" name="cout-gratuit-enigme"
                      <?= $is_gratuit ? 'checked' : ''; ?>>

                    <label for="cout-gratuit-enigme">Gratuit</label>
                  </div>

                  <!-- Nombre max de tentatives -->
                  <div class="champ-enigme champ-nb-tentatives <?= empty($max) ? 'champ-vide' : 'champ-rempli'; ?>" data-champ="enigme_tentative.enigme_tentative_max" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
                    <label for="enigme-nb-tentatives">Nombre max de tentatives/jour</label>
                    <input type="number" id="enigme-nb-tentatives" class="champ-input" min="1" step="1" value="<?= esc_attr($max); ?>" placeholder="5" />
                    <p class="message-tentatives txt-small" style="margin-top: 4px;"></p>
                    <div class="champ-feedback"></div>
                  </div>

                </div>
                <div class="champ-feedback"></div>
              </div>
            </fieldset>

            <!-- Réponse automatique -->
            <fieldset class="groupe-champ champ-groupe-reponse-automatique">
              <legend>Réponse attendue</legend>
              <div class="champ-enigme champ-bonne-reponse <?= empty($reponse) ? 'champ-vide' : 'champ-rempli'; ?>"
                data-champ="enigme_reponse_bonne"
                data-cpt="enigme"
                data-post-id="<?= esc_attr($enigme_id); ?>">

                <label for="champ-bonne-reponse">Bonne réponse attendue</label>

                <input type="text"
                  id="champ-bonne-reponse"
                  name="champ-bonne-reponse"
                  class="champ-input champ-texte-edit"
                  value="<?= esc_attr($reponse); ?>"
                  placeholder="Ex : soleil" />

                <div class="champ-feedback"></div>
              </div>

              <div class="champ-enigme champ-casse <?= $casse ? 'champ-rempli' : 'champ-vide'; ?>"
                data-champ="enigme_reponse_casse"
                data-cpt="enigme"
                data-post-id="<?= esc_attr($enigme_id); ?>">
                <label><input type="checkbox" <?= $casse ? 'checked' : ''; ?>> Respecter la casse</label>
                <div class="champ-feedback"></div>
              </div>

              <div class="champ-enigme champ-variantes-resume"
                data-champ="enigme_reponse_variantes"
                data-cpt="enigme"
                data-post-id="<?= esc_attr($enigme_id); ?>">

                <?php
                $label = $has_variantes
                  ? ($nb_variantes === 1 ? '1 variante ✏️' : $nb_variantes . ' variantes ✏️')
                  : '➕ Créer des variantes';
                ?>
                <button type="button"
                  class="champ-modifier ouvrir-panneau-variantes"
                  aria-label="<?= $has_variantes ? 'Éditer les variantes' : 'Créer des variantes'; ?>"
                  data-cpt="enigme"
                  data-post-id="<?= esc_attr($enigme_id); ?>">
                  <?= esc_html($label); ?>
                </button>
              </div>

            </fieldset>

        </div>
      </div>
    </div>

    </div> <!-- .edition-panel-body -->
    <?php if (utilisateur_peut_supprimer_enigme($enigme_id)) : ?>
      <div class="edition-panel-footer">
        <button type="button" id="bouton-supprimer-enigme" class="bouton-texte secondaire">❌ Suppression énigme</button>
      </div>
    <?php endif; ?>
    </div> <!-- #enigme-tab-param -->

    <div id="enigme-tab-stats" class="edition-tab-content" style="display:none;">
      <i class="fa-solid fa-chart-column tab-watermark" aria-hidden="true"></i>
      <div class="edition-panel-header">
        <h2><i class="fa-solid fa-chart-column"></i> Statistiques</h2>
      </div>
      <p class="edition-placeholder">La section « Statistiques » sera bientôt disponible.</p>
    </div>

<div id="enigme-tab-soumission" class="edition-tab-content" style="display:none;">
  <i class="fa-solid fa-paper-plane tab-watermark" aria-hidden="true"></i>
  <div class="edition-panel-header">
    <h2><i class="fa-solid fa-paper-plane"></i> Soumission</h2>
  </div>
<?php
  $page_tentatives = max(1, intval($_GET['page_tentatives'] ?? 1));
  $par_page = 25;
  $offset = ($page_tentatives - 1) * $par_page;
  $tentatives = recuperer_tentatives_enigme($enigme_id, $par_page, $offset);
  $total_tentatives = compter_tentatives_enigme($enigme_id);
  if (empty($tentatives)) : ?>
  <p>Aucune tentative de soumission.</p>
<?php else : ?>
  <table class="table-tentatives">
    <thead>
      <tr>
        <th>Utilisateur</th>
        <th>Réponse</th>
        <th>Résultat</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($tentatives as $tent) : ?>
        <tr>
          <td><?= esc_html(get_userdata($tent->user_id)?->display_name ?? 'Inconnu'); ?></td>
          <td><?= esc_html($tent->reponse_saisie); ?></td>
          <td><?= esc_html($tent->resultat); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="pager" style="margin-top:10px;">
    <?php if ($page_tentatives > 1) : ?>
      <a href="<?= esc_url(add_query_arg('page_tentatives', $page_tentatives - 1)); ?>">&laquo; Préc.</a>
    <?php endif; ?>
    <?php if ($offset + $par_page < $total_tentatives) : ?>
      <a href="<?= esc_url(add_query_arg('page_tentatives', $page_tentatives + 1)); ?>" style="margin-left:10px;">Suiv. &raquo;</a>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>

<div id="enigme-tab-indices" class="edition-tab-content" style="display:none;">
  <i class="fa-regular fa-lightbulb tab-watermark" aria-hidden="true"></i>
  <div class="edition-panel-header">
    <h2><i class="fa-regular fa-lightbulb"></i> Indices</h2>
  </div>
  <p class="edition-placeholder">La section « Indices » sera bientôt disponible.</p>
</div>

<div id="enigme-tab-solution" class="edition-tab-content" style="display:none;">
  <i class="fa-solid fa-key tab-watermark" aria-hidden="true"></i>
  <div class="edition-panel-header">
    <h2><i class="fa-solid fa-key"></i> Solution</h2>
  </div>

            <fieldset class="groupe-champ champ-groupe-solution">
              <legend>Publication de la solution</legend>

              <div class="champ-enigme champ-solution champ-solution-mode" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">

                <?php
                $solution_mode = get_field('enigme_solution_mode', $enigme_id) ?? 'pdf';
                $fichier = get_field('enigme_solution_fichier', $enigme_id);
                $fichier_url = is_array($fichier) ? $fichier['url'] : '';
                $delai = get_field('enigme_solution_delai', $enigme_id) ?? 7;
                $heure = get_field('enigme_solution_heure', $enigme_id) ?? '18:00';
                ?>

                <!-- ✅ Ligne groupée : radio + fichier + bouton texte -->
                <div style="display: flex; flex-wrap: wrap; align-items: flex-start; gap: 1rem;">

                  <!-- Radios -->
                  <div class="champ-solution-mode" style="display: flex; flex-direction: column; min-width: 160px;">
                    <label>
                      <input type="radio" name="acf[enigme_solution_mode]" value="pdf" <?= $solution_mode === 'pdf' ? 'checked' : ''; ?>>
                      Télécharger un PDF
                    </label>

                    <label style="margin-top: 5px;">
                      <input type="radio" name="acf[enigme_solution_mode]" value="texte" <?= $solution_mode === 'texte' ? 'checked' : ''; ?>>
                      Rédiger la solution
                    </label>
                  </div>

                  <!-- Upload fichier PDF -->
                  <div class="champ-solution-fichier" style="<?= $solution_mode === 'pdf' ? '' : 'display:none;' ?> min-width: 200px;">
                    <?php if ($fichier_url) : ?>
                      <p style="margin-bottom: 4px;">Fichier actuel : <a href="<?= esc_url($fichier_url); ?>" target="_blank"><?= basename($fichier_url); ?></a></p>
                    <?php endif; ?>
                    <input type="file" id="solution-pdf-upload" accept="application/pdf">
                    <div class="champ-feedback" style="margin-top: 5px;"></div>
                  </div>

                  <!-- Bouton WYSIWYG -->
                  <div class="champ-solution-texte" style="<?= $solution_mode === 'texte' ? '' : 'display:none;' ?>">
                    <button type="button" id="ouvrir-panneau-solution" class="bouton-ouvrir-wysiwyg">
                      ✏️ Ouvrir l’éditeur de solution
                    </button>
                  </div>

                </div>

                <!-- ✅ Ligne publication -->
                <div class="champ-solution-timing" style="margin-top: 15px;">
                  <label for="solution-delai" style="margin-right: 8px;">Publication :</label>

                  <input type="number"
                    min="0"
                    max="60"
                    step="1"
                    value="<?= esc_attr($delai); ?>"
                    id="solution-delai"
                    class="champ-input champ-delai-inline">

                  <span>jours après la fin de la chasse, à</span>

                  <select id="solution-heure" class="champ-select-heure">
                    <?php foreach (range(0, 23) as $h) :
                      $formatted = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00'; ?>
                      <option value="<?= $formatted; ?>" <?= $formatted === $heure ? 'selected' : ''; ?>><?= $formatted; ?></option>
                    <?php endforeach; ?>
                  </select>
                  <span>heure.</span>
                </div>

              </div>
            </fieldset>

          </div>
        </div>
      </div>
    </div>
    </div> <!-- #enigme-tab-solution -->
  </section>
<?php endif; ?>