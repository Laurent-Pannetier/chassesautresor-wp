<?php // 🔗 Liens publics
defined( 'ABSPATH' ) || exit;
$args = $args ?? [];
$organisateur_id = $args['organisateur_id']
    ?? get_query_var('organisateur_id_force')
    ?? get_organisateur_from_user(get_current_user_id());

$liens_publics = get_field('liens_publics', $organisateur_id);
$types_disponibles = organisateur_get_liste_liens_publics();
$liens_actifs = [];

if (is_array($liens_publics)) {
    foreach ($liens_publics as $entree) {
        $type_raw = $entree['type_de_lien'] ?? null;
        $url = $entree['url_lien'] ?? null;
    
        $type = is_array($type_raw) ? ($type_raw[0] ?? '') : $type_raw;
    
        if (is_string($type) && trim($type) !== '' && is_string($url) && trim($url) !== '') {
            $liens_actifs[$type] = $url;
        }
    }
}

    if (!empty($types_disponibles)) : ?>
        <div id="panneau-liens-publics" class="panneau-lateral-liens" aria-hidden="true">
            <div class="panneau-lateral__contenu">

                <div class="panneau-lateral__header">
                    <h2>Configurer vos liens publics</h2>
                    <button type="button" class="panneau-fermer" aria-label="Fermer le panneau">✖</button>
                </div>

                <form id="formulaire-liens-publics"
                      data-post-id="<?= esc_attr($organisateur_id); ?>"
                      data-cpt="organisateur"
                      data-champ="liens_publics">
                    <ul class="liste-liens-formulaires">
                        <?php foreach ($types_disponibles as $type => $infos) :
                            $url = $liens_actifs[$type] ?? '';
                            ?>
                            <li class="ligne-lien-formulaire" data-type="<?= esc_attr($type); ?>">
                                <label for="champ-<?= esc_attr($type); ?>">
                                    <i class="fa <?= esc_attr($infos['icone']); ?>"></i>
                                    <?= esc_html($infos['label']); ?>
                                </label>
                                <input
                                    type="url"
                                    name="liens_publics[<?= esc_attr($type); ?>]"
                                    id="champ-<?= esc_attr($type); ?>"
                                    value="<?= esc_attr($url); ?>"
                                    placeholder="https://..."
                                    class="champ-url-lien"
                                    inputmode="url"
                                >
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="panneau-lateral__actions">
                        <button type="submit" class="bouton-enregistrer-liens">💾 Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>