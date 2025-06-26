<?php
defined('ABSPATH') || exit;


$organisateur_id = get_organisateur_id_from_context($args ?? []);
$peut_modifier = utilisateur_peut_modifier_post($organisateur_id);

$description = get_field('description_longue', $organisateur_id);
$date_inscription = get_the_date('d/m/Y', $organisateur_id);
$email_contact = get_field('profil_public_email_contact', $organisateur_id);

$liens_publics = get_field('liens_publics', $organisateur_id);
$types_disponibles = organisateur_get_liste_liens_publics();

$liens_actifs = [];

if (!empty($liens_publics) && is_array($liens_publics)) {
    foreach ($liens_publics as $entree) {
        $type_raw = $entree['type_de_lien'] ?? null;
        $url      = $entree['url_lien'] ?? null;

        $type = is_array($type_raw) ? ($type_raw[0] ?? '') : $type_raw;

        if (is_string($type) && trim($type) !== '' && is_string($url) && trim($url) !== '') {
            $liens_actifs[$type] = esc_url($url);
        }
    }
}

?>

<section id="presentation" class="bloc-presentation-organisateur bloc-toggle masque bloc-discret">
    <button type="button" class="panneau-fermer presentation-fermer" aria-label="Fermer les informations">✖</button>
    <!-- Liens publics -->
    <?php $champ_vide_liens = count($liens_actifs) === 0;    ?>
    <?php $classe_liens = count($liens_actifs) >= 3 ? 'liens-compacts' : ''; ?>
    <div class="champ-organisateur champ-liens <?= empty($liens_actifs) ? 'champ-vide' : ''; ?>"
             data-champ="liens_publics"
             data-cpt="organisateur"
             data-post-id="<?= esc_attr($organisateur_id); ?>">
        
            <div class="champ-affichage champ-affichage-liens bloc-liens-publics header-organisateur__liens <?= count($liens_actifs) >= 3 ? 'liens-compacts' : ''; ?>">
        
            <?php if (!empty($liens_actifs)) : ?>
              <ul class="liste-liens-publics">
                <?php foreach ($liens_actifs as $type => $url) :
                  $infos = organisateur_get_lien_public_infos($type);
                ?>
                  <li class="item-lien-public">
                    <a href="<?= esc_url($url); ?>"
                       class="lien-public lien-<?= esc_attr($type); ?>"
                       target="_blank" rel="noopener"
                       aria-label="<?= esc_attr($infos['label']); ?>">
                      <i class="fa <?= esc_attr($infos['icone']); ?>" aria-hidden="true"></i>
                      <span class="texte-lien"><?= esc_html($infos['label']); ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else : ?>
              <div class="liens-placeholder">
                <p class="liens-placeholder-message">Aucun lien ajouté pour le moment.</p>
                <?php foreach ($types_disponibles as $type => $infos) : ?>
                  <i class="fa <?= esc_attr($infos['icone']); ?> icone-grisee" title="<?= esc_attr($infos['label']); ?>"></i>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
        
            <?php if ($peut_modifier) : ?>
              <button type="button"
                      class="champ-modifier modifier-liens"
                      aria-label="Configurer vos liens"
                      data-champ="liens_publics"
                      data-cpt="organisateur"
                      data-post-id="<?= esc_attr($organisateur_id); ?>">
                ✏️
              </button>
            <?php endif; ?>
          </div>
        
          <div class="champ-feedback"></div>
        </div>
    
    <div class="presentation__description champ-organisateur bloc-elegant champ-description-longue <?= empty($description) ? 'champ-vide' : ''; ?>"
     data-champ="description_longue"
     data-post-id="<?= esc_attr($organisateur_id); ?>">
        <div class="flex-row titre-presentation">
          <h2>Présentation</h2>
          <?php if ($peut_modifier) : ?>
            <button type="button"
                    class="champ-modifier ouvrir-panneau-description"
                    aria-label="Modifier la présentation">
                ✏️
            </button>
          <?php endif; ?>
        </div>

        <div class="separateur-2"></div>
        <div class="champ-affichage">
            <?= wpautop($description ?: '<em>Aucune description fournie pour le moment.</em>'); ?>
            
        </div>
        <div class="champ-feedback"></div>
        
    </div>
    
    <hr class="separateur-1">
    
    <div class="presentation__infos">

        <div class="bloc-metas-inline">
          <div class="meta-etiquette" title="Date de création du profil organisateur">
            <i class="fa-regular fa-calendar"></i>
            <strong>Inscription :</strong>
            <span><?= esc_html($date_inscription); ?></span>
          </div>
        
          <div class="meta-etiquette" title="Nombre de chasses publiées">
            <i class="fa-solid fa-compass-drafting"></i>
            <strong>Chasses :</strong>
            <span data-champ="nb_chasses">—</span>
          </div>
        
          <div class="meta-etiquette" title="Nombre de joueurs ayant participé à ses chasses">
            <i class="fa-solid fa-users"></i>
            <strong>Joueurs :</strong>
            <span data-champ="nb_joueurs">—</span>
          </div>
        <!--
          <div class="meta-etiquette" title="Nombre de news publiées">
            <i class="fa-solid fa-newspaper"></i>
            <strong>News :</strong>
            <span data-champ="nb_news">—</span>
          </div>
        </div>
        -->
    </div>
</section>
