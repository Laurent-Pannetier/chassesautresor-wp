dans edition-functions.php
/**
 * 🔹 modifier_champ_enigme() → Gère l’enregistrement AJAX des champs ACF ou natifs du CPT énigme (post_title inclus).
 */
add_action('wp_ajax_modifier_champ_enigme', 'modifier_champ_enigme');

/**
 * 🔸 Enregistrement AJAX d’un champ ACF ou natif du CPT énigme.
 *
 * Autorise :
 * - Le champ natif `post_title`
 * - Les champs ACF simples
 * - Les champs imbriqués dans des groupes (`enigme_acces`, `enigme_tentative`, etc.)
 *
 * @hook wp_ajax_modifier_champ_enigme
 */
function modifier_champ_enigme() {
  if (!is_user_logged_in()) {
    wp_send_json_error('non_connecte');
  }

  $user_id = get_current_user_id();
  $champ   = sanitize_text_field($_POST['champ'] ?? '');
  $valeur  = wp_kses_post($_POST['valeur'] ?? '');
  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

  if (!$champ || !isset($_POST['valeur']) || !$post_id) {
    wp_send_json_error('⚠️ donnees_invalides');
  }

  if (get_post_type($post_id) !== 'enigme') {
    wp_send_json_error('⚠️ post_invalide');
  }

  $auteur = (int) get_post_field('post_author', $post_id);
  if ($auteur !== $user_id) {
    wp_send_json_error('⚠️ acces_refuse');
  }

  $champ_valide = false;
  $reponse = [ 'champ' => $champ, 'valeur' => $valeur ];

  // 🔹 post_title (champ natif WP)
  if ($champ === 'post_title') {
    $ok = wp_update_post([ 'ID' => $post_id, 'post_title' => $valeur ], true);
    if (is_wp_error($ok)) {
      wp_send_json_error('⚠️ echec_update_post_title');
    }
    wp_send_json_success($reponse);
  }

  // 🔹 Mode de validation (radio)
  if ($champ === 'enigme_mode_validation') {
    $ok = update_field('enigme_mode_validation', sanitize_text_field($valeur), $post_id);
    if ($ok) $champ_valide = true;
  }

  // 🔹 Texte réponse manuelle
  if ($champ === 'enigme_reponse_texte_manuelle') {
    $ok = update_field('enigme_reponse_texte_manuelle', $valeur, $post_id);
    if ($ok) $champ_valide = true;
  }

  // 🔹 Bonne réponse (champ texte)
  if ($champ === 'enigme_reponse_bonne') {
    $ok = update_field('enigme_reponse_bonne', sanitize_text_field($valeur), $post_id);
    if ($ok) $champ_valide = true;
  }

  // 🔹 Casse (true_false)
  if ($champ === 'enigme_reponse_casse') {
    $ok = update_field('enigme_reponse_casse', (int) $valeur, $post_id);
    if ($ok) $champ_valide = true;
  }

  // 🔹 Condition d’accès
  if ($champ === 'enigme_acces.condition') {
    $ok = mettre_a_jour_sous_champ_group($post_id, 'enigme_acces', 'enigme_acces_condition', sanitize_text_field($valeur));
    if ($ok) $champ_valide = true;
  }

  // 🔹 Date de déblocage
  if ($champ === 'enigme_acces.date') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
      wp_send_json_error('⚠️ format_date_invalide');
    }
    $ok = mettre_a_jour_sous_champ_group($post_id, 'enigme_acces', 'enigme_acces_date', $valeur);
    if ($ok) $champ_valide = true;
  }
  
    // 🔹 Coût en points (groupe enigme_tentative)
    if ($champ === 'enigme_tentative.enigme_tentative_cout_points') {
      $ok = mettre_a_jour_sous_champ_group($post_id, 'enigme_tentative', 'enigme_tentative_cout_points', (int) $valeur);
      if ($ok) $champ_valide = true;
    }
    
    // 🔹 Nombre max de tentatives (groupe enigme_tentative)
    if ($champ === 'enigme_tentative.enigme_tentative_max') {
      $ok = mettre_a_jour_sous_champ_group($post_id, 'enigme_tentative', 'enigme_tentative_max', (int) $valeur);
      if ($ok) $champ_valide = true;
    }

  // 🔹 Style d’affichage (select)
  if ($champ === 'enigme_style_affichage') {
    $ok = update_field('enigme_style_affichage', sanitize_text_field($valeur), $post_id);
    if ($ok) $champ_valide = true;
  }

  // 🔹 Cas générique fallback
  if (!$champ_valide) {
    $ok = update_field($champ, is_numeric($valeur) ? (int) $valeur : $valeur, $post_id);
    $valeur_meta = get_post_meta($post_id, $champ, true);
    $valeur_comparee = stripslashes_deep($valeur);
    if ($ok || trim((string) $valeur_meta) === trim((string) $valeur_comparee)) {
      $champ_valide = true;
    } else {
      wp_send_json_error('⚠️ echec_mise_a_jour_final');
    }
  }

  wp_send_json_success($reponse);
}



edition-enigme.php
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

$peut_modifier = utilisateur_peut_modifier_post($enigme_id);
$titre = get_the_title($enigme_id);
$titre_defaut = 'Nouvelle énigme';
$isTitreParDefaut = strtolower(trim($titre)) === strtolower($titre_defaut);

$visuel = get_field('enigme_visuel_image', $enigme_id); // champ "gallery" → tableau d’IDs
$legende = get_field('enigme_visuel_legende', $enigme_id);
$texte = get_field('enigme_visuel_texte', $enigme_id);
$reponse = get_field('enigme_reponse_bonne', $enigme_id);
$casse = get_field('enigme_reponse_casse', $enigme_id);
$tentative = get_field('enigme_tentative', $enigme_id);
$max = $tentative['enigme_tentative_max'] ?? 5;
$cout = $tentative['enigme_tentative_cout_points'] ?? '';
$texte_manuelle = get_field('enigme_reponse_texte_manuelle', $enigme_id);
$mode_validation = get_field('enigme_mode_validation', $enigme_id);
$style = get_field('enigme_style_affichage', $enigme_id);
$solution = get_field('enigme_solution', $enigme_id);
$acces = get_field('enigme_acces', $enigme_id);
$groupe = get_field('enigme_acces', $enigme_id);
$date_raw = $groupe['enigme_acces_date'] ?? '';
$date_deblocage = $date_raw ? substr($date_raw, 0, 10) : '';


$chasse = get_field('enigme_chasse_associee', $enigme_id);
$chasse_id = is_array($chasse) ? $chasse[0] : null;
$chasse_title = $chasse_id ? get_the_title($chasse_id) : '';


?>

<?php if ($peut_modifier) : ?>
  <section class="edition-panel edition-panel-enigme" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
    <div id="erreur-global"
      style="display:none; background:red; color:white; padding:5px; text-align:center; font-size:0.9em;"></div>

    <div class="edition-panel-header">
      <h2><i class="fa-solid fa-sliders"></i> Paramètres de l'énigme</h2>
      <button type="button" class="panneau-fermer" aria-label="Fermer les paramètres">✖</button>
    </div>

    <div class="edition-panel-body edition-panel-section">
      <div class="resume-blocs-grid deux-col-wrapper">
        <div class="resume-bloc resume-obligatoire deux-col-bloc">

          <h3>Champs obligatoires</h3>
          <ul class="resume-infos">
            <li class="champ-enigme champ-titre <?= ($isTitreParDefaut ? 'champ-vide' : 'champ-rempli'); ?>"
              data-champ="post_title" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
              Titre
              <button type="button" class="champ-modifier" data-champ="post_title" data-cpt="enigme"
                data-post-id="<?= esc_attr($enigme_id); ?>">
                ✏️
              </button>
            </li>
            <li class="champ-enigme champ-img <?= $has_images ? 'champ-rempli' : 'champ-vide'; ?>"
                data-champ="enigme_visuel_image"
                data-cpt="enigme"
                data-post-id="<?= esc_attr($enigme_id); ?>">
            
              Image(s)
            
              <button type="button"
                    class="champ-modifier ouvrir-panneau-images"
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
                <?php
                error_log('[DEBUG DATE] valeur brute = ' . print_r($date_deblocage, true));
                ?>
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
              </fieldset>
            </div>

            <!-- Réponse manuelle -->
            <fieldset class="groupe-champ champ-groupe-reponse-manuelle" style="display: none;">
              <legend>Soumission manuelle</legend>
              <div class="champ-enigme champ-manuelle" data-champ="enigme_reponse_texte_manuelle" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
                <label for="champ-texte-manuelle">Texte de soumission libre (manuel)</label>
                <textarea id="champ-texte-manuelle" class="champ-input" rows="3" placeholder="Le joueur verra ce champ s’il doit expliquer sa réponse..."><?= esc_textarea($texte_manuelle); ?></textarea>
                <div class="champ-feedback"></div>
              </div>
            </fieldset>

            <!-- Réponse automatique -->
            <fieldset class="groupe-champ champ-groupe-reponse-automatique" style="display: none;">
              <legend>Réponse attendue</legend>
              <div class="champ-enigme champ-reponse" data-champ="enigme_reponse_bonne" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
                <label for="champ-bonne-reponse">Bonne réponse</label>
                <input type="text" id="champ-bonne-reponse" class="champ-input" value="<?= esc_attr($reponse); ?>" placeholder="Ex : soleil">
                <div class="champ-feedback"></div>
              </div>

              <div class="champ-enigme champ-casse" data-champ="enigme_reponse_casse" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
                <label><input type="checkbox" <?= $casse ? 'checked' : ''; ?>> Respecter la casse</label>
                <div class="champ-feedback"></div>
              </div>

              <div class="champ-enigme champ-variantes" data-champ="enigme_reponse_variantes" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
                <p>Variantes à la réponse à configurer...</p>
                <div class="champ-feedback"></div>
              </div>
            </fieldset>

            <!-- Accès à l'énigme -->
            <fieldset class="groupe-champ champ-groupe-acces">
              <legend>Condition d’accès</legend>

              <div class="champ-enigme champ-access" data-champ="enigme_acces.condition" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
                <?php
                $condition = $acces['enigme_acces_condition'] ?? 'immediat';
                $options = [
                  'immediat'        => 'Immédiat',
                  'date_programmee' => 'Date programmée',
                  'pre_requis'      => 'Pré-requis',
                ];
                foreach ($options as $val => $label) :
                ?>
                  <label style="display:inline-block; margin-right: 15px;">
                    <input type="radio" name="acf[enigme_acces_condition]" value="<?= esc_attr($val); ?>" <?= $condition === $val ? 'checked' : ''; ?>>
                    <?= esc_html($label); ?>
                  </label>
                <?php endforeach; ?>
                <div class="champ-feedback"></div>
              </div>

              <div class="champ-enigme champ-date" data-champ="enigme_acces.date" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>" id="champ-enigme-date" style="display: none;">
                <label for="enigme-date-deblocage">Date de déblocage</label>
                <input type="date"
                  id="enigme-date-deblocage"
                  name="enigme-date-deblocage"
                  value="<?= esc_attr($date_deblocage); ?>"
                  class="champ-inline-date champ-date-edit" />
                <div class="champ-feedback champ-date-feedback" style="display:none;"></div>
              </div>

              <div class="champ-enigme champ-pre-requis" data-champ="enigme_acces.pre_requis" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>" id="champ-enigme-pre-requis" style="display: none;">
                <div class="champ-affichage">
                  Pré-requis
                  <button type="button" class="champ-modifier" data-champ="enigme_acces.pre_requis" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">✏️</button>
                </div>
                <div class="champ-feedback"></div>
              </div>
            </fieldset>

            <!-- Tentatives -->
            <fieldset class="groupe-champ champ-groupe-tentatives">
              <legend>Gestion des tentatives</legend>

              <div class="champ-enigme champ-cout-points <?= empty($cout) ? 'champ-vide' : 'champ-rempli'; ?>" data-champ="enigme_tentative.enigme_tentative_cout_points" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
                <div class="champ-edition" style="display: flex; align-items: center;">
                  <label>Coût <span class="txt-small">(points)</span>
                    <button type="button" class="bouton-aide-points open-points-modal" aria-label="En savoir plus sur les points">
                      <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                    </button>
                  </label>
                  <input type="number" class="champ-input champ-cout" min="0" step="1" value="<?= esc_attr($cout); ?>" placeholder="0" />
                  <div class="champ-option-gratuit" style="margin-left: 15px;">
                    <input type="checkbox" id="cout-gratuit-enigme" name="cout-gratuit-enigme" <?= ((int)$cout === 0) ? 'checked' : ''; ?>>
                    <label for="cout-gratuit-enigme">Gratuit</label>
                  </div>
                </div>
                <div class="champ-feedback"></div>
              </div>

              <div class="champ-enigme champ-nb-tentatives <?= empty($max) ? 'champ-vide' : 'champ-rempli'; ?>" data-champ="enigme_tentative.enigme_tentative_max" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
                <label for="enigme-nb-tentatives">Nombre max de tentatives/jour</label>
                <input type="number" id="enigme-nb-tentatives" class="champ-input" min="1" step="1" value="<?= esc_attr($max); ?>" placeholder="5" />
                <p class="message-tentatives txt-small" style="margin-top: 4px;"></p>
                <div class="champ-feedback"></div>
              </div>
            </fieldset>

            <!-- Style d'affichage -->
            <div class="champ-enigme champ-style" data-champ="enigme_style_affichage" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
              <label>Style d'affichage :</label>
              <select class="champ-input">
                <option value="defaut" <?= $style === 'defaut' ? 'selected' : ''; ?>>Défaut</option>
                <option value="pirate" <?= $style === 'pirate' ? 'selected' : ''; ?>>Pirate</option>
                <option value="vintage" <?= $style === 'vintage' ? 'selected' : ''; ?>>Vintage</option>
              </select>
              <div class="champ-feedback"></div>
            </div>

            <!-- Publication de la solution -->
            <div class="champ-enigme champ-solution" data-champ="enigme_solution" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>" style="display: none;">
              <p>Publication de la solution (mode + délai + explication) — à venir.</p>
              <div class="champ-feedback"></div>
            </div>
          </div>
        </div>

      </div>
    </div>
    <div class="edition-panel-footer"></div>
  </section>
<?php endif; ?>



enigme-edit.js
// ✅ enigme-edit.js
console.log('✅ enigme-edit.js chargé');

let boutonToggle;
let panneauEdition;

document.addEventListener('DOMContentLoaded', () => {
  boutonToggle = document.getElementById('toggle-mode-edition-enigme');
  panneauEdition = document.querySelector('.edition-panel-enigme');

  // ==============================
  // 🛠️ Contrôles panneau principal
  // ==============================
  boutonToggle?.addEventListener('click', () => {
    document.body.classList.toggle('edition-active-enigme');
  });
  

  panneauEdition?.querySelector('.panneau-fermer')?.addEventListener('click', () => {
    document.body.classList.remove('edition-active-enigme');
    document.activeElement?.blur();
  });
  

  // ==============================
  // 🧭 Déclencheur automatique
  // ==============================
  const params = new URLSearchParams(window.location.search);
  const doitOuvrir = params.get('edition') === 'open';
  if (doitOuvrir && boutonToggle) {
    boutonToggle.click();
    console.log('🔧 Ouverture auto du panneau édition énigme via ?edition=open');
  }

  // ==============================
  // 🟢 Initialisation des champs
  // ==============================
  document.querySelectorAll('.champ-enigme[data-champ]').forEach((bloc) => {
    const champ = bloc.dataset.champ;

    if (bloc.classList.contains('champ-img') && champ !== 'enigme_visuel_image') {
      if (typeof initChampImage === 'function') initChampImage(bloc);
    } else {
      if (typeof initChampTexte === 'function') initChampTexte(bloc);
    }
  });

  // ==============================
  // 🧰 Déclencheurs de résumé
  // ==============================
  document.querySelectorAll('.edition-panel-enigme .champ-modifier[data-champ]').forEach((btn) => {
    if (typeof initChampDeclencheur === 'function') initChampDeclencheur(btn);
  });

  // ==============================
  // 📜 Panneau description (wysiwyg)
  // ==============================
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.ouvrir-panneau-description');
    if (!btn || btn.dataset.cpt !== 'enigme') return;

    const panneau = document.getElementById('panneau-description-enigme');
    if (!panneau) return;

    document.querySelectorAll('.panneau-lateral.ouvert, .panneau-lateral-liens.ouvert').forEach((p) => {
      p.classList.remove('ouvert');
      p.setAttribute('aria-hidden', 'true');
    });

    panneau.classList.add('ouvert');
    document.body.classList.add('panneau-ouvert');
    panneau.setAttribute('aria-hidden', 'false');
  });

  document.querySelector('#panneau-description-enigme .panneau-fermer')?.addEventListener('click', () => {
    const panneau = document.getElementById('panneau-description-enigme');
    panneau.classList.remove('ouvert');
    document.body.classList.remove('panneau-ouvert');
    panneau.setAttribute('aria-hidden', 'true');
  });
  
    // Forcer mise à jour du message au chargement
    const blocCout = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_cout_points"]');
    if (blocCout && typeof window.onCoutPointsUpdated === 'function') {
      const champ = blocCout.dataset.champ;
      const valeur = parseInt(blocCout.querySelector('.champ-input')?.value || '0', 10);
      const postId = blocCout.dataset.postId;
      const cpt = blocCout.dataset.cpt;
    
      window.onCoutPointsUpdated(blocCout, champ, valeur, postId, cpt);
    }
  
  initConditionAcces();
  initEnregistrementConditionAcces();
  initChampNbTentatives();

});


// ================================
// 🖼️ Panneau images galerie (ACF gallery)
// ================================
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.ouvrir-panneau-images');
  if (!btn || btn.dataset.cpt !== 'enigme') return;

  const panneau = document.getElementById('panneau-images-enigme');
  if (!panneau) return;

  document.querySelectorAll('.panneau-lateral.ouvert, .panneau-lateral-liens.ouvert').forEach((p) => {
    p.classList.remove('ouvert');
    p.setAttribute('aria-hidden', 'true');
  });

  panneau.classList.add('ouvert');
  document.body.classList.add('panneau-ouvert');
  panneau.setAttribute('aria-hidden', 'false');
});

document.querySelector('#panneau-images-enigme .panneau-fermer')?.addEventListener('click', () => {
  const panneau = document.getElementById('panneau-images-enigme');
  panneau.classList.remove('ouvert');
  document.body.classList.remove('panneau-ouvert');
  panneau.setAttribute('aria-hidden', 'true');
});



// ================================
// 🧭 Affichage conditionnel – Champ d'accès à l'énigme
// ================================
function initConditionAcces() {
  const radios = document.querySelectorAll('input[name="acf[enigme_acces_condition]"]');
  const champDate = document.getElementById('champ-enigme-date');
  const champPreRequis = document.getElementById('champ-enigme-pre-requis');

  if (!radios.length || !champDate || !champPreRequis) return;

  function mettreAJourAffichageCondition() {
    const valeur = [...radios].find(r => r.checked)?.value;

    champDate.style.display = (valeur === 'date_programmee') ? '' : 'none';
    champPreRequis.style.display = (valeur === 'pre_requis') ? '' : 'none';
  }

  radios.forEach(radio => {
    radio.addEventListener('change', mettreAJourAffichageCondition);
  });

  mettreAJourAffichageCondition(); // au chargement
}


// ================================
// 📩 Enregistrement dynamique – Condition d'accès
// ================================
function initEnregistrementConditionAcces() {
  const radios = document.querySelectorAll('input[name="acf[enigme_acces_condition]"]');

  radios.forEach(radio => {
    radio.addEventListener('change', function () {
      const bloc = radio.closest('[data-champ]');
      const champ = bloc?.dataset.champ;
      const postId = bloc?.dataset.postId;

      if (!champ || !postId) return;

      fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'modifier_champ_enigme',
          champ,
          valeur: this.value,
          post_id: postId
        })
      })
      .then(r => r.json())
      .then(res => {
        if (!res.success) {
          console.error('❌ Erreur AJAX condition accès :', res.data);
        } else {
          console.log('✅ Condition d’accès enregistrée :', this.value);
          if (typeof window.mettreAJourResumeInfos === 'function') {
            window.mettreAJourResumeInfos();
          }
        }
      })
      .catch(err => {
        console.error('❌ Erreur réseau AJAX condition accès :', err);
      });
    });
  });
}


// ================================
// 🔢 Initialisation champ enigme_tentative_max (tentatives/jour)
// ================================
function initChampNbTentatives() {
  const bloc = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_max"]');
  if (!bloc) return;

  const input = bloc.querySelector('.champ-input');
  const postId = bloc.dataset.postId;
  const champ = bloc.dataset.champ;
  const cpt = bloc.dataset.cpt || 'enigme';

  let timerDebounce;

  // ✅ Crée un message d'aide dynamique
  let aide = bloc.querySelector('.champ-aide-tentatives');
  if (!aide) {
    aide = document.createElement('p');
    aide.className = 'champ-aide champ-aide-tentatives';
    aide.style.margin = '5px 0 0 10px';
    aide.style.fontSize = '0.9em';
    aide.style.color = '#ccc';
    bloc.appendChild(aide);
  }

  // 🔄 Fonction centralisée
  function mettreAJourAideTentatives() {
    const coutInput = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_cout_points"] .champ-input');
    if (!coutInput) return;

    const cout = parseInt(coutInput.value.trim(), 10);
    const estGratuit = isNaN(cout) || cout === 0;

    aide.textContent = estGratuit
      ? "Mode gratuit : maximum 5 tentatives par jour."
      : "Mode payant : tentatives illimitées.";

    if (estGratuit && parseInt(input.value.trim(), 10) > 5) {
      input.value = 5;
    }
  }

    // 💾 Enregistrement avec limite si nécessaire
     input.addEventListener('input', () => {
      clearTimeout(timerDebounce);
    
      let valeur = parseInt(input.value.trim(), 10);
    
      // 🔐 Forcer affichage visuel et valeur logique à 1 min
      if (isNaN(valeur) || valeur < 1) {
        valeur = 1;
        input.value = '1';
      }
    
      const coutInput = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_cout_points"] .champ-input');
      const cout = parseInt(coutInput?.value.trim() || '0', 10);
      const estGratuit = isNaN(cout) || cout === 0;
    
      if (estGratuit && valeur > 5) {
        valeur = 5;
        input.value = '5';
      }
    
      timerDebounce = setTimeout(() => {
        modifierChampSimple(champ, valeur, postId, cpt);
      }, 400);
    });


  // 💬 Mise à jour immédiate au chargement
  mettreAJourAideTentatives();

  // 🔁 Lié aux modifs de coût (input + checkbox)
  const coutInput = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_cout_points"] .champ-input');
  const checkbox = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_cout_points"] input[type="checkbox"]');
  if (coutInput) coutInput.addEventListener('input', mettreAJourAideTentatives);
  if (checkbox) checkbox.addEventListener('change', mettreAJourAideTentatives);

  // 🔄 Fonction exportée globalement
  window.mettreAJourMessageTentatives = mettreAJourAideTentatives;
}



// ================================
// 💰 Hook personnalisé – Réaction au champ coût (CPT énigme uniquement)
// ================================
window.onCoutPointsUpdated = function (bloc, champ, valeur, postId, cpt) {
  if (champ === 'enigme_tentative.enigme_tentative_cout_points') {
    const champMax = document.querySelector('[data-champ="enigme_tentative.enigme_tentative_max"] .champ-input');
    if (champMax) {
      if (valeur === 0) {
        champMax.max = 5;
        if (parseInt(champMax.value, 10) > 5) {
          champMax.value = 5;
          modifierChampSimple('enigme_tentative.enigme_tentative_max', 5, postId, cpt);
        }
      } else {
        champMax.removeAttribute('max');
        champMax.max = ''; // ✅ retire la contrainte interne
      }
    }
  }
}



champ-init.js
// ✅ champ-init.js bien chargé
console.log('✅ champ-init.js bien chargé');

// ==============================
// 🔄 MAJ dynamique des classes champ-vide / champ-rempli
// ==============================
window.mettreAJourResumeInfos = function () {

  // 🔵 ORGANISATEUR
  const panneauOrganisateur = document.querySelector('.panneau-organisateur');
  if (panneauOrganisateur) {
    panneauOrganisateur.querySelectorAll('.resume-infos li[data-champ]').forEach((ligne) => {
      const champ = ligne.dataset.champ;
      const bloc = document.querySelector('.champ-organisateur[data-champ="' + champ + '"]');

      let estRempli = bloc && !bloc.classList.contains('champ-vide');

      if (champ === 'post_title') {
        const valeurTitre = bloc?.querySelector('.champ-input')?.value.trim().toLowerCase();
        const titreParDefaut = "votre nom d’organisateur";
        estRempli = valeurTitre && valeurTitre !== titreParDefaut;
      }

      if (champ === 'coordonnees_bancaires') {
        const iban = document.getElementById('champ-iban')?.value.trim();
        const bic = document.getElementById('champ-bic')?.value.trim();
        estRempli = !!(iban && bic);
      }

      if (champ === 'liens_publics') {
        const ul = bloc?.querySelector('.liste-liens-publics');
        estRempli = ul && ul.children.length > 0;
      }

      // Mise à jour visuelle
      ligne.classList.toggle('champ-rempli', estRempli);
      ligne.classList.toggle('champ-vide', !estRempli);

      ligne.querySelectorAll('.icone-check, .icon-attente').forEach(i => i.remove());

      const icone = document.createElement('i');
      icone.className = estRempli
        ? 'fa-solid fa-circle-check icone-check'
        : 'fa-regular fa-circle icon-attente';
      icone.setAttribute('aria-hidden', 'true');
      ligne.prepend(icone);
    });
  }

  // 🟠 CHASSE
  const panneauChasse = document.querySelector('.edition-panel-chasse');
  if (panneauChasse) {
    panneauChasse.querySelectorAll('.resume-infos li[data-champ]').forEach((ligne) => {
      const champ = ligne.dataset.champ;
      
        // 🎯 [NOUVEAU] Ignorer les champs du groupe caractéristiques
        if (champ.startsWith('caracteristiques.') && champ !== 'caracteristiques_chasse_infos_recompense_valeur') {
          return; // On saute toutes sauf la récompense
        }

    
      const blocEdition = document.querySelector('.champ-chasse[data-champ="' + champ + '"]');

      let estRempli = false;

      if (blocEdition && !blocEdition.classList.contains('champ-vide')) {
        estRempli = true;
      }

      // Cas spécifiques chasse
      if (champ === 'post_title') {
        const valeurTitre = blocEdition?.querySelector('.champ-input')?.value.trim().toLowerCase();
        const titreParDefaut = window.CHP_CHASSE_DEFAUT?.titre || 'nouvelle chasse';
        estRempli = valeurTitre && valeurTitre !== titreParDefaut;
      }

      if (champ === 'chasse_principale_description') {
        const texte = document.querySelector('#panneau-description-chasse textarea')?.value?.trim();
        estRempli = !!texte;
      }

        if (champ === 'chasse_principale_image') {
          const image = blocEdition?.querySelector('img');
          estRempli = image && !image.src.includes('defaut-chasse');
        }

        if (champ === 'chasse_principale_liens') {
          const ul = document.querySelector('.champ-chasse[data-champ="chasse_principale_liens"] .liste-liens-publics');
          estRempli = ul && ul.children.length > 0;
        }

if (champ === 'caracteristiques_chasse_infos_recompense_valeur') {
  const titre = document.getElementById('champ-recompense-titre')?.value.trim();
  const texte = document.getElementById('champ-recompense-texte')?.value.trim();
  const valeur = parseFloat(document.getElementById('champ-recompense-valeur')?.value || '0');

  estRempli = (titre.length > 0) && (texte.length > 0) && (valeur > 0);
}





      // Mise à jour visuelle
      ligne.classList.toggle('champ-rempli', estRempli);
      ligne.classList.toggle('champ-vide', !estRempli);

      ligne.querySelectorAll('.icone-check, .icon-attente').forEach(i => i.remove());

      const icone = document.createElement('i');
      icone.className = estRempli
        ? 'fa-solid fa-circle-check icone-check'
        : 'fa-regular fa-circle icon-attente';
      icone.setAttribute('aria-hidden', 'true');
      ligne.prepend(icone);
    });
  }
  // 🔵 Affichage du CTA création chasse (organisateur)
    const cta = document.getElementById('cta-creer-chasse');
    if (cta) {
      const champsObligatoires = [
        '.panneau-organisateur .champ-titre',
        '.panneau-organisateur .champ-logo',
        '.panneau-organisateur .champ-description'
      ];
    
      const incomplets = champsObligatoires.filter(sel => {
        const champ = document.querySelector(sel);
        return champ && champ.classList.contains('champ-vide');
      }).length;
    
      if (incomplets === 0) {
        cta.style.display = ''; // Affiche le CTA
      } else {
        cta.style.display = 'none'; // Cache le CTA
      }
    }
};


// ================================
// 📦 Petite fonction utilitaire commune pour éviter de répéter du code
// ================================
function mettreAJourLigneResume(ligne, champ, estRempli, type) {
  ligne.classList.toggle('champ-rempli', estRempli);
  ligne.classList.toggle('champ-vide', !estRempli);

  // Nettoyer anciennes icônes
  ligne.querySelectorAll(':scope > .icone-check, :scope > .icon-attente').forEach((i) => i.remove());

  // Ajouter nouvelle icône
  const icone = document.createElement('i');
  icone.className = estRempli
    ? 'fa-solid fa-circle-check icone-check'
    : 'fa-regular fa-circle icon-attente';
  icone.setAttribute('aria-hidden', 'true');
  ligne.prepend(icone);

  // Ajouter bouton édition ✏️ si besoin
  const dejaBouton = [...ligne.children].some((el) =>
    el.classList?.contains('champ-modifier') && !el.querySelector('button')
  );
  if (!dejaBouton) {
    const bouton = document.createElement('button');
    bouton.type = 'button';
    bouton.className = 'champ-modifier';
    bouton.textContent = '✏️';
    bouton.setAttribute('aria-label', 'Modifier le champ ' + champ);

    bouton.addEventListener('click', () => {
      const blocCible = document.querySelector(`.champ-${type}[data-champ="${champ}"]`);
      const boutonInterne = blocCible?.querySelector('.champ-modifier');
      boutonInterne?.click();
    });

    ligne.appendChild(bouton);
  }
}


// ==============================
// 📅 Initialisation globale des champs date
// ==============================
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input[type="date"]').forEach(initChampDate);
});



// ==============================
// 📅 Formatage des dates Y-m-d ➔ d/m/Y
// ==============================
function formatDateFr(dateStr) {
  if (!dateStr) return '';
  const parts = dateStr.split('-');
  if (parts.length !== 3) return dateStr;
  return `${parts[2]}/${parts[1]}/${parts[0]}`;
}


// ==============================
// 📅 Mise à jour affichage Date Fin
// ==============================
function mettreAJourAffichageDateFin() {
  const spanDateFin = document.querySelector('.chasse-date-plage .date-fin');
  const inputDateFin = document.getElementById('chasse-date-fin');
  const checkboxIllimitee = document.getElementById('duree-illimitee');

  if (!spanDateFin || !inputDateFin || !checkboxIllimitee) return;

  if (checkboxIllimitee.checked) {
    spanDateFin.textContent = 'Illimitée';
  } else {
    spanDateFin.textContent = formatDateFr(inputDateFin.value);
  }
}

// ================================
// 🛠️ Envoi AJAX d'un champ simple (texte, number, boolean)
// ================================
function modifierChampSimple(champ, valeur, postId, cpt = 'chasse') {
  const action = (cpt === 'enigme') ? 'modifier_champ_enigme' :
                 (cpt === 'organisateur') ? 'modifier_champ_organisateur' :
                 'modifier_champ_chasse';

  console.log(`📤 Envoi vers ${action} → champ :`, champ, '| valeur :', valeur, '| postId :', postId);

  fetch(ajaxurl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action,
      champ,
      valeur,
      post_id: postId
    })
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      console.log(`✅ Champ ${champ} enregistré`);
    } else {
      console.warn(`⚠️ Échec enregistrement champ ${champ} :`, res?.data);
    }
  })
  .catch(err => {
    console.error('❌ Erreur réseau AJAX :', err);
  });
}



// ==============================
// initChampTexte
// ==============================
function initChampTexte(bloc) {
  const champ = bloc.dataset.champ;
  const cpt = bloc.dataset.cpt;
  const postId = bloc.dataset.postId;

  const affichage = bloc.querySelector('.champ-affichage');
  const edition = bloc.querySelector('.champ-edition');
  const input = bloc.querySelector('.champ-input');
  const boutonEdit = bloc.querySelector('.champ-modifier');
  const boutonSave = bloc.querySelector('.champ-enregistrer');
  const boutonCancel = bloc.querySelector('.champ-annuler');
  const action = (cpt === 'chasse') ? 'modifier_champ_chasse' :
                 (cpt === 'enigme') ? 'modifier_champ_enigme' :
                 'modifier_champ_organisateur';

  if (!champ || !cpt || !postId || !input || !boutonEdit || !boutonSave || !boutonCancel) return;

  let feedback = bloc.querySelector('.champ-feedback');
  if (!feedback) {
    feedback = document.createElement('div');
    feedback.className = 'champ-feedback';
    bloc.appendChild(feedback);
  }

  // ✏️ Ouverture édition
  boutonEdit.addEventListener('click', () => {
    affichage.style.display = 'none';
    edition.style.display = 'flex';
    input.focus();
    feedback.textContent = '';
    feedback.className = 'champ-feedback';

    // Fallback affichage email (organisateur)
    if (champ === 'profil_public_email_contact') {
      const fallback = window.organisateurData?.defaultEmail || '…';
      const affichageTexte = affichage.querySelector('p');
      if (affichageTexte && input.value.trim() === '') {
        affichageTexte.innerHTML =
          '<strong>Email de contact :</strong> <em>' + fallback + '</em>';
      }
    }

    // Fallback affichage slogan (organisateur)
    if (champ === 'profil_public_description_courte') {
      const affichageTexte = affichage.querySelector('h2');
      if (affichageTexte && input.value.trim() === '') {
        affichageTexte.textContent = 'Votre slogan ici…';
      }
    }
  });

  // ❌ Annulation
  boutonCancel.addEventListener('click', () => {
    edition.style.display = 'none';
    affichage.style.display = '';
    feedback.textContent = '';
    feedback.className = 'champ-feedback';
  });

  // ✅ Sauvegarde
  boutonSave.addEventListener('click', () => {
    const valeur = input.value.trim();
    if (!champ || !postId) return;

    // Validation email (vide autorisé)
    if (champ === 'profil_public_email_contact') {
      const isValide = valeur === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valeur);
      if (!isValide) {
        feedback.textContent = '⛔ Adresse email invalide';
        feedback.className = 'champ-feedback champ-error';
        return;
      }
    }
    if (champ === 'enigme_visuel_legende') {
      const legendeDOM = document.querySelector('.enigme-legende');
    
      if (legendeDOM) {
        const valeurTexte = input.value.trim();
        legendeDOM.textContent = valeurTexte;
        legendeDOM.classList.add('modifiee');
      }
    }

    feedback.textContent = 'Enregistrement en cours...';
    feedback.className = 'champ-feedback champ-loading';

    fetch('/wp-admin/admin-ajax.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action,
        champ,
        valeur,
        post_id: postId
      })
    })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        if (champ === 'profil_public_email_contact') {
          const fallbackEmail = window.organisateurData?.defaultEmail || '…';
          const affichageTexte = affichage.querySelector('p');
          if (affichageTexte) {
            affichageTexte.innerHTML =
              '<strong>Email de contact :</strong> ' +
              (valeur ? valeur : '<em>' + fallbackEmail + '</em>');
          }
        } else if (champ === 'profil_public_description_courte') {
          const affichageTexte = affichage.querySelector('h2');
          if (affichageTexte) {
            affichageTexte.textContent = valeur || 'Votre slogan ici…';
          }
        } else {
          const affichageTexte = affichage.querySelector('h1, h2, p, span');
          if (affichageTexte) {
            affichageTexte.textContent = valeur;
          }
        }

        edition.style.display = 'none';
        affichage.style.display = '';
        bloc.classList.toggle('champ-vide', !valeur);
        feedback.textContent = '';
        feedback.className = 'champ-feedback champ-success';

        if (typeof window.mettreAJourResumeInfos === 'function') {
          window.mettreAJourResumeInfos();
        }
      } else {
        feedback.textContent = 'Erreur lors de l’enregistrement.';
        feedback.className = 'champ-feedback champ-error';
      }
    })
    .catch(() => {
      feedback.textContent = 'Erreur réseau.';
      feedback.className = 'champ-feedback champ-error';
    });
  });
}


// ==============================
// initChampDeclencheur
// ==============================
function initChampDeclencheur(bouton) {
  const champ = bouton.dataset.champ;
  const postId = bouton.dataset.postId;
  const cpt = bouton.dataset.cpt || 'organisateur';

  if (!champ || !postId || !cpt) return;

  bouton.addEventListener('click', () => {
  const bloc = document.querySelector(
    `.champ-${cpt}[data-champ="${champ}"][data-post-id="${postId}"]`
  );

  if (!bloc) return;

  // 🛡️ Sécurité : ignorer si c'est un résumé
  if (bloc.classList.contains('resume-ligne')) {
    return; // Ne pas essayer d'ouvrir l'édition sur une ligne résumé
  }

  const vraiBouton = [...bloc.querySelectorAll('.champ-modifier')].find(b => b !== bouton);

  if (vraiBouton) vraiBouton.click();
});

}


// ==============================
// initChampImage
// ==============================
function initChampImage(bloc) {
  const champ = bloc.dataset.champ;
  const cpt = bloc.dataset.cpt;
  const postId = bloc.dataset.postId;

  const input = bloc.querySelector('.champ-input');
  const image = bloc.querySelector('img');
  const feedback = bloc.querySelector('.champ-feedback');
  const boutonEdit = bloc.querySelector('.champ-modifier');
  const action = (cpt === 'chasse') ? 'modifier_champ_chasse' :
                 (cpt === 'enigme') ? 'modifier_champ_enigme' :
                 'modifier_champ_organisateur';

  if (!champ || !cpt || !postId || !input || !image || !boutonEdit) return;

  let frame = null;

  boutonEdit.addEventListener('click', () => {
    if (frame) return frame.open();

    frame = wp.media({
      title: 'Choisir une image',
      multiple: false,
      library: { type: 'image' },
      button: { text: 'Utiliser cette image' }
    });

    frame.on('select', () => {
      const selection = frame.state().get('selection').first();
      const id = selection?.id;
      const url = selection?.attributes?.url;
      if (!id || !url) return;

      image.src = url;
      input.value = id;

      if (feedback) {
        feedback.textContent = 'Enregistrement...';
        feedback.className = 'champ-feedback champ-loading';
      }

      fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action,
          champ,
          valeur: id,
          post_id: postId
        })
      })
      .then(res => res.json())
      .then(res => {
        if (res.success) {
          bloc.classList.remove('champ-vide');
          if (feedback) {
            feedback.textContent = '';
            feedback.className = 'champ-feedback champ-success';
          }
          if (typeof window.mettreAJourResumeInfos === 'function') {
            window.mettreAJourResumeInfos();
          }
        } else {
          if (feedback) {
            feedback.textContent = '❌ Erreur : ' + (res.data || 'inconnue');
            feedback.className = 'champ-feedback champ-error';
          }
        }
      })
      .catch(() => {
        if (feedback) {
          feedback.textContent = '❌ Erreur réseau.';
          feedback.className = 'champ-feedback champ-error';
        }
      });
    });

    frame.open();
  });
}


// ==============================
// 📅 initChampDate
// ==============================
function initChampDate(input) {
    console.log('⏱️ Attachement initChampDate à', input, '→ ID:', input.id);

  const bloc = input.closest('[data-champ]');
  const champ = bloc?.dataset.champ;
  const postId = bloc?.dataset.postId;
  const cpt = bloc?.dataset.cpt || 'chasse';

  if (!champ || !postId) return;

  // 🕒 Pré-remplissage si vide
  if (!input.value && bloc.dataset.date) {
    const dateInit = bloc.dataset.date;
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateInit)) {
      input.value = dateInit;
    }
  }

  input.addEventListener('change', () => {
    const valeur = input.value.trim();
    console.log('[🧪 initChampDate]', champ, '| valeur saisie :', valeur);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(valeur)) {
      console.warn(`❌ Date invalide (${champ}) :`, valeur);
      return;
    }

    const action = (cpt === 'chasse') ? 'modifier_champ_chasse' :
                   (cpt === 'enigme') ? 'modifier_champ_enigme' :
                   'modifier_champ_organisateur';
    
    console.log('📤 Envoi AJAX date', { champ, valeur, postId });

    fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action,
        champ,
        valeur,
        post_id: postId
      })
    })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
          console.log('[initChampDate] Hook onDateFieldUpdated =', typeof window.onDateFieldUpdated);

        if (typeof window.onDateFieldUpdated === 'function') {
            console.log('[initChampDate] Appel de onDateFieldUpdated() avec valeur =', valeur);

          window.onDateFieldUpdated(input, valeur);
        }
      } else {
        console.warn('❌ Erreur serveur (date)', res.data || res);
      }
    })
    .catch(err => {
      console.error('❌ Erreur réseau (date)', err);
    });
  });
}



// ================================
// 💰 Initialisation affichage coût en points (Gratuit / Payant) — multi-CPT
// ================================
function initChampCoutPoints() {
  document.querySelectorAll('.champ-cout-points').forEach(bloc => {
    const input = bloc.querySelector('.champ-input.champ-cout[type="number"]');
    const checkbox = bloc.querySelector('input[type="checkbox"]');
    if (!input || !checkbox) return;

    const postId = bloc.dataset.postId;
    const champ = bloc.dataset.champ;
    const cpt = bloc.dataset.cpt;
    if (!postId || !champ || !cpt) return;

    let timerDebounce;
    let ancienneValeur = input.value.trim();

    // ✅ Initialisation checkbox (mais on ne désactive rien ici)
    const valeurInitiale = parseInt(input.value.trim(), 10);
    checkbox.checked = valeurInitiale === 0;

    const enregistrerCout = () => {
      clearTimeout(timerDebounce);
      timerDebounce = setTimeout(() => {
        let valeur = parseInt(input.value.trim(), 10);
        if (isNaN(valeur) || valeur < 0) valeur = 0;
        input.value = valeur;
        modifierChampSimple(champ, valeur, postId, cpt);

        if (typeof window.onCoutPointsUpdated === 'function') {
          window.onCoutPointsUpdated(bloc, champ, valeur, postId, cpt);
        }
      }, 500);
    };

    input.addEventListener('input', enregistrerCout);

    checkbox.addEventListener('change', () => {
      if (checkbox.checked) {
        ancienneValeur = input.value.trim();
        input.value = 0;
      } else {
        const valeur = parseInt(ancienneValeur, 10);
        input.value = valeur > 0 ? valeur : 10;
      }
      enregistrerCout();
    });
  });
}

document.addEventListener('DOMContentLoaded', initChampCoutPoints);



// ================================
// 💰 Affichage conditionnel des boutons d'édition coût
// ================================
function initAffichageBoutonsCout() {
  document.querySelectorAll('.champ-cout-points .champ-input').forEach(input => {
    const container = input.closest('.champ-enigme');
    const boutons = container.querySelector('.champ-inline-actions');
    if (!boutons) return;

    // Sauvegarde la valeur initiale
    input.dataset.valeurInitiale = input.value.trim();

    /// Avant d'ajouter les événements
    boutons.style.transition = 'none';
    boutons.style.opacity = '0';
    boutons.style.visibility = 'hidden';
    
    // Ensuite (petit timeout pour réactiver les transitions après masquage immédiat)
    setTimeout(() => {
      boutons.style.transition = 'opacity 0.3s ease, visibility 0.3s ease';
    }, 50);

    input.addEventListener('input', () => {
      let val = input.value.trim();
      if (val === '') val = '0'; // Vide = 0

      const initiale = input.dataset.valeurInitiale;
      if (val !== initiale) {
        boutons.style.opacity = '1';
        boutons.style.visibility = 'visible';
      } else {
        boutons.style.opacity = '0';
        boutons.style.visibility = 'hidden';
      }
    });

    input.addEventListener('blur', () => {
      if (input.value.trim() === '') {
        input.value = '0';
      }
    });
  });
}
initAffichageBoutonsCout();


// ================================
// 🔁 Recalcul dynamique du statut (actuellement : chasse uniquement)
// ================================
function recalculerEtMettreAJourStatut(postId, cpt = 'chasse') {
  if (!postId || !cpt) return;

  if (cpt === 'chasse') {
    fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'forcer_recalcul_statut_chasse',
        post_id: postId
      })
    })
    .then(res => res.json())
    .then(stat => {
      if (stat.success) {
        fetch(ajaxurl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'recuperer_statut_chasse',
            post_id: postId
          })
        })
        .then(r => r.json())
        .then(data => {
          if (data.success && data.data?.statut) {
            const statut = data.data.statut;
            const label = data.data.statut_label;
            const badge = document.querySelector(`.badge-statut[data-post-id="${postId}"]`);
            if (badge) {
              badge.textContent = label;
              badge.className = `badge-statut statut-${statut}`;
            }
          }
        });
      }
    });
  }

  // ⏳ À étendre : enigme, organisateur, etc.
}



// ================================
// 💰 Initialisation affichage coût en points (Gratuit / Payant) — multi-CPT
// ================================
function initChampCoutPoints() {
  document.querySelectorAll('.champ-cout-points').forEach(bloc => {
    const input = bloc.querySelector('.champ-input.champ-cout[type="number"]');
    const checkbox = bloc.querySelector('input[type="checkbox"]');
    if (!input || !checkbox) return;

    const postId = bloc.dataset.postId;
    const champ = bloc.dataset.champ;
    if (!postId || !champ) return;

    let timerDebounce;
    let ancienneValeur = input.value.trim();

    const enregistrerCout = () => {
      clearTimeout(timerDebounce);
        timerDebounce = setTimeout(() => {
          let valeur = parseInt(input.value.trim(), 10);
          if (isNaN(valeur) || valeur < 0) valeur = 0;
          input.value = valeur;
          modifierChampSimple(champ, valeur, postId, bloc.dataset.cpt);
        
          // ✅ Mise à jour visuelle du badge coût pour la chasse
          if (
            champ === 'caracteristiques.chasse_infos_cout_points' &&
            typeof mettreAJourAffichageCout === 'function'
          ) {
            mettreAJourAffichageCout(postId, valeur);
          }
        }, 500);
    };

    // ✅ état initial : disable si gratuit
    const valeurInitiale = parseInt(input.value.trim(), 10);
    if (valeurInitiale === 0) {
      input.disabled = true;
      checkbox.checked = true;
    } else {
      input.disabled = false;
      checkbox.checked = false;
    }

    input.addEventListener('input', enregistrerCout);

    checkbox.addEventListener('change', () => {
      if (checkbox.checked) {
        ancienneValeur = input.value.trim();
        input.value = 0;
        input.disabled = true;
      } else {
        const valeur = parseInt(ancienneValeur, 10);
        input.value = valeur > 0 ? valeur : 10;
        input.disabled = false;
      }
      enregistrerCout();
    });
  });
}
document.addEventListener('DOMContentLoaded', initChampCoutPoints);


