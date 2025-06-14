<?php
// Panneau organisateur (affiché en mode édition)
defined('ABSPATH') || exit;

$organisateur_id = get_organisateur_id_from_context($args ?? []);
$peut_modifier = utilisateur_peut_modifier_post($organisateur_id);


// User
$current_user = wp_get_current_user();
$roles = (array) $current_user->roles;
$profil_expanded = array_intersect($roles, ['organisateur_creation', 'abonne']);
$profil_expanded = !empty($profil_expanded);
$infos_expanded = !$profil_expanded;
$edition_active = in_array('organisateur_creation', $roles);

// Post
$titre        = get_post_field('post_title', $organisateur_id);
$logo         = get_field('profil_public_logo_organisateur', $organisateur_id);
$description  = get_field('description_longue', $organisateur_id);
$slogan       = get_field('profil_public_description_courte', $organisateur_id);
$reseaux      = get_field('reseaux_sociaux', $organisateur_id);
$site         = get_field('lien_site_web', $organisateur_id);
$email_contact = get_field('profil_public_email_contact', $organisateur_id);
$coordonnees  = get_field('coordonnees_bancaires', $organisateur_id);
$liens_actifs = organisateur_get_liens_actifs($organisateur_id);
$nb_liens = count($liens_actifs);

$is_complete = (
    !empty($titre) &&
    !empty($logo) &&
    !empty($description)
);

$coordonnees = get_field('coordonnees_bancaires', $organisateur_id);
$iban_vide = empty($coordonnees['iban']);
$bic_vide  = empty($coordonnees['bic']);
$classe_vide_coordonnees = ($iban_vide || $bic_vide) ? 'champ-vide' : '';
?>

<?php if ($edition_active && $peut_modifier) : ?>
    <section class="panneau-organisateur edition-panel edition-panel-organisateur edition-active" aria-hidden="false">
    
        <div class="edition-panel-header">
            <h2><i class="fa-solid fa-sliders"></i> Paramètres organisateur</h2>
            <button type="button" class="panneau-fermer" aria-label="Fermer les paramètres organisateur">
              ✖
            </button>
        </div>
    
      
        <div class="edition-panel-body">
            <div class="edition-panel-section edition-panel-section-ligne accordeon-bloc"  data-bloc="profil">
                <button class="accordeon-toggle" aria-expanded="true">
                  <span class="label">
                    <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                    Profil organisateur
                  </span>
                  <i class="fa-solid fa-chevron-down chevron" aria-hidden="true"></i>
                </button>
    
                <div class="accordeon-contenu deux-col-wrapper">
                    <!-- 📌 Édition du profil -->
                    <div class="resume-bloc resume-obligatoire deux-col-bloc">
                        <h3>Champs obligatoires</h3>
                        <ul class="resume-infos">
                            <li class=" champ-titre ligne-titre <?= empty($titre) ? 'champ-vide' : 'champ-rempli'; ?>" data-champ="post_title">
                                <span class="champ-label">Un nom d'organisateur</span>
                                <?php if ($peut_modifier) : ?>
                                    <button type="button"
                                            class="champ-modifier"
                                            aria-label="Modifier le nom d’organisateur"
                                            data-champ="post_title"
                                            data-cpt="organisateur"
                                            data-post-id="<?php echo esc_attr($organisateur_id); ?>">
                                        ✏️
                                    </button>

                                <?php endif; ?>
                            </li>
    
                            <li class="champ-organisateur champ-logo ligne-logo <?= !empty($logo) ? 'champ-rempli' : 'champ-vide'; ?>" data-champ="profil_public_logo_organisateur">
                              Un logo
                              <?php if ($peut_modifier) : ?>
                                <button type="button"
                                        class="champ-modifier"
                                        aria-label="Modifier le logo"
                                        data-champ="profil_public_logo_organisateur"
                                        data-cpt="organisateur"
                                        data-post-id="<?php echo esc_attr($organisateur_id); ?>">
                                    ✏️
                                </button>

                              <?php endif; ?>
                              <input type="hidden" class="champ-input" value="<?= esc_attr($logo_id ?? '') ?>">
                              <div class="champ-feedback"></div>
                            </li>
                            <li class="ligne-description champ-rempli champ-description" data-champ="description_longue">
                                Une présentation
                                <?php if ($peut_modifier) : ?>
                                <button type="button"
                                        class="champ-modifier ouvrir-panneau-description"
                                        aria-label="Modifier la description longue">
                                    ✏️
                                </button>

                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
        
                    <!-- 🟡 Facultatif -->
                    <div class="resume-bloc resume-facultatif deux-col-bloc">
                        <h3>Facultatif (mais recommandé)</h3>
                        <ul class="resume-infos">
                            <li class="ligne-slogan <?= !empty($slogan) ? 'champ-rempli' : ''; ?>" data-champ="profil_public_description_courte">
                                Un slogan
                                <?php if ($peut_modifier) : ?>
                                  <button type="button"
                                          class="champ-modifier"
                                          aria-label="Modifier le slogan"
                                          data-champ="profil_public_description_courte"
                                          data-cpt="organisateur"
                                          data-post-id="<?php echo esc_attr($organisateur_id); ?>">
                                      ✏️
                                  </button>
                                <?php endif; ?>
                            </li>

                            <li class="ligne-liens <?= ($nb_liens > 0) ? 'champ-rempli' : ''; ?>" data-champ="liens_publics">
                              des liens externes (réseau social ou site)
                              <?php if ($peut_modifier) : ?>
                                <button type="button"
                                        class="champ-modifier ouvrir-panneau-liens"
                                        aria-label="Configurer les liens publics">
                                  ✏️
                                </button>
                              <?php endif; ?>
                            </li>

                            <li class="champ-organisateur champ-coordonnees ligne-coordonnees <?= !empty($coordonnees['iban']) ? 'champ-rempli' : ''; ?>" data-champ="coordonnees_bancaires">
                                    Coordonnées bancaires
                                    <button type="button" class="icone-info" aria-label="Informations sur les coordonnées bancaires" 
                                        onclick="alert('Ces informations sont nécessaires uniquement pour vous verser les gains issus de la conversion de vos points en euros. Nous ne prélevons jamais d’argent.');">
                                        <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                                    </button>
                                    <?php if ($peut_modifier) : ?>
                                        <button type="button"
                                                id="ouvrir-coordonnees"
                                                class="champ-modifier"
                                                aria-label="Modifier les coordonnées bancaires"
                                                data-champ="coordonnees_bancaires"
                                                data-cpt="organisateur"
                                                data-post-id="<?php echo esc_attr($organisateur_id); ?>">
                                            ✏️
                                        </button>

                                    <?php endif; ?>
                                </li>
    
                            <li class="ligne-email <?= !empty($email_contact) ? 'champ-rempli' : ''; ?>">
                              <i aria-hidden="true" class="fa-regular fa-solid fa-envelope"></i>
                              <div class="champ-organisateur champ-email-contact"
                                   data-champ="profil_public_email_contact"
                                   data-cpt="organisateur"
                                   data-post-id="<?= esc_attr($organisateur_id); ?>">
                            
                                <div class="champ-affichage">
                                  
                                    Email de contact :
                                    <?= esc_html($email_contact ?: get_the_author_meta('user_email', get_post_field('post_author', $organisateur_id))); ?>
                                  
                                  <button type="button" class="icone-info"
                                          aria-label="Informations sur l’adresse email de contact"
                                          onclick="alert('Quand aucune adresse n est renseignée, votre email utilisateur est utilisé par défaut.');">
                                    <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                                  </button>
                                  <?php if ($peut_modifier) : ?>
                                    <button type="button"
                                            class="champ-modifier"
                                            aria-label="Modifier l’adresse email de contact">
                                        ✏️
                                    </button>
                                  <?php endif; ?>
                                </div>
                            
                                <div class="champ-edition" style="display: none;">
                                  <input type="email" maxlength="255"
                                         value="<?= esc_attr($email_contact); ?>"
                                         class="champ-input"
                                         placeholder="exemple@domaine.com">
                                  <button type="button" class="champ-enregistrer">✓</button>
                                  <button type="button" class="champ-annuler">✖</button>
                                </div>
                            
                                <div class="champ-feedback"></div>
                              </div>
                            </li>

                        </ul>
                    </div>
                </div>
            </div>
            <div class="edition-panel-section edition-placeholder accordeon-bloc" data-bloc="informations">
                <button class="accordeon-toggle" aria-expanded="false">
                  <span class="label">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    Informations organisateur
                  </span>
                  <i class="fa-solid fa-chevron-down chevron" aria-hidden="true"></i>
                </button>
    
              <div class="placeholder-contenu accordeon-contenu">
                <p>🚧 Cette section affichera bientôt vos données organisateurs :</p>
                <ul class="liste-placeholder">
                  <li>📈 <strong>Nombre de chasses créées</strong></li>
                  <li>👥 <strong>Nombre de joueurs participants</strong></li>
                  <li>🏆 <strong>Trophées créés</strong></li>
                  <li>📅 <strong>Historique de vos publications</strong></li>
                  <li>💰 <strong>Revenus ou conversions en attente</strong></li>
                </ul>
                <p class="info-a-suivre">Rendez-vous prochainement pour plus de fonctionnalités !</p>
              </div>
            </div>  
        </div>
        <div class="edition-panel-footer"></div>
    </section>
<?php endif; ?>
<?php if ($peut_modifier) : ?>
  <?php get_template_part('template-parts/organisateur/panneaux/panneau-description', null, [
      'organisateur_id' => $organisateur_id
  ]); ?>

  <?php get_template_part('template-parts/organisateur/panneaux/panneau-liens-publics', null, [
      'organisateur_id' => $organisateur_id
  ]); ?>

  <?php get_template_part('template-parts/organisateur/panneaux/panneau-coordonnees', null, [
      'organisateur_id' => $organisateur_id
  ]); ?>
<?php endif; ?>