<?php
/**
 * Dynamic content for the "Organisateurs" section.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (!current_user_can('administrator')) {
    wp_redirect(home_url('/mon-compte/'));
    exit;
}

$organisateurs_liste = recuperer_organisateurs_pending();
$page                 = max(1, (int) ($_GET['page'] ?? 1));
?>
<section>
    <h2><?php esc_html_e('Organisateurs', 'chassesautresor-com'); ?></h2>
    <?php if (empty($organisateurs_liste)) : ?>
        <p><?php esc_html_e('Aucun organisateur.', 'chassesautresor-com'); ?></p>
    <?php else : ?>
        <?php
        $etats = [];
        foreach ($organisateurs_liste as $entry) {
            if (!empty($entry['statut'])) {
                $etats[$entry['statut']] = true;
            }
        }
        ?>
        <div class="stats-header">
            <span class="etiquette">
                <?php
                $count = count($organisateurs_liste);
                printf(
                    esc_html(
                        _n('%d organisateur', '%d organisateurs', $count, 'chassesautresor-com')
                    ),
                    $count
                );
                ?>
            </span>
            <div class="stats-filtres">
                <label for="filtre-etat"><?php esc_html_e('Filtrer par état :', 'chassesautresor-com'); ?></label>
                <select id="filtre-etat">
                    <option value="tous"><?php esc_html_e('Tous', 'chassesautresor-com'); ?></option>
                    <?php foreach (array_keys($etats) as $etat) : ?>
                        <option value="<?php echo esc_attr($etat); ?>"><?php echo esc_html($etat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php afficher_tableau_organisateurs_pending($organisateurs_liste, $page); ?>
    <?php endif; ?>
</section>
