<?php
/**
 * Display a table of indices for a hunt or riddle.
 *
 * Variables:
 * - $indices    (array WP_Post)
 * - $page       (int)
 * - $pages      (int)
 * - $objet_type ('chasse'|'enigme')
 * - $objet_id   (int)
 */

defined('ABSPATH') || exit;

$args         = $args ?? [];
$indices      = $args['indices'] ?? $indices ?? [];
$page         = $args['page'] ?? $page ?? 1;
$pages        = $args['pages'] ?? $pages ?? 1;
$objet_type   = $args['objet_type'] ?? $objet_type ?? 'chasse';
$objet_id     = $args['objet_id'] ?? $objet_id ?? 0;
$objet_titre  = get_the_title($objet_id);
$count_total  = isset($args['count_total']) ? (int) $args['count_total'] : 0;
$count_chasse = isset($args['count_chasse']) ? (int) $args['count_chasse'] : 0;
$count_enigme = isset($args['count_enigme']) ? (int) $args['count_enigme'] : 0;
$toggle       = $args['toggle'] ?? null;

if (empty($indices)) {
    $titre = get_the_title($objet_id);
    if ($titre === TITRE_DEFAUT_ENIGME) {
        $titre = __('en création', 'chassesautresor-com');
    } elseif ($titre === TITRE_DEFAUT_CHASSE) {
        $titre = __('Nouvelle chasse', 'chassesautresor-com');
    }
    echo '<p>' . esc_html(sprintf(__('Vous n\'avez publié aucun indice attaché à %s', 'chassesautresor-com'), $titre)) . '</p>';
    return;
}
?>

<?php if ($count_total || $count_chasse || $count_enigme || $toggle) : ?>
<div class="indices-table-header">
    <?php if ($count_total) : ?>
        <span class="etiquette"><?php echo esc_html(sprintf(_n('%d indice au total', '%d indices au total', $count_total, 'chassesautresor-com'), $count_total)); ?></span>
    <?php endif; ?>
    <?php if ($count_chasse) : ?>
        <span class="etiquette"><?php echo esc_html(sprintf(_n('%d indice chasse', '%d indices chasse', $count_chasse, 'chassesautresor-com'), $count_chasse)); ?></span>
    <?php endif; ?>
    <?php if ($count_enigme) : ?>
        <span class="etiquette"><?php echo esc_html(sprintf(_n('%d indice énigme', '%d indices énigme', $count_enigme, 'chassesautresor-com'), $count_enigme)); ?></span>
    <?php endif; ?>
    <?php if ($toggle) : ?>
        <span class="etiquette">
            <button type="button" class="indices-toggle champ-modifier" data-chasse-id="<?= esc_attr($toggle['chasse_id']); ?>" data-enigme-id="<?= esc_attr($toggle['enigme_id']); ?>">
                <?= esc_html($toggle['label']); ?>
            </button>
        </span>
    <?php endif; ?>
</div>
<?php endif; ?>

<table class="stats-table indices-table">
    <thead>
        <tr>
            <th><?= esc_html__('Date', 'chassesautresor-com'); ?></th>
            <th><?= esc_html__('Indice', 'chassesautresor-com'); ?></th>
            <th class="indice-text"><?= esc_html__('Texte', 'chassesautresor-com'); ?></th>
            <th><?= esc_html__('Indice pour', 'chassesautresor-com'); ?></th>
            <th class="indice-actions"><?= esc_html__('Action', 'chassesautresor-com'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($indices as $indice) :
            $indice_rank = (int) get_post_meta($indice->ID, 'indice_rank', true);
            $timestamp   = strtotime($indice->post_date);
            $locale      = function_exists('determine_locale')
                ? determine_locale()
                : (function_exists('get_locale') ? get_locale() : 'fr_FR');
            $date_format = str_starts_with($locale, 'en') ? 'm/d/y' : 'd/m/y';
            $date_value  = function_exists('wp_date')
                ? wp_date($date_format, $timestamp)
                : date($date_format, $timestamp);
            $time_value  = function_exists('wp_date')
                ? wp_date('H:i', $timestamp)
                : date('H:i', $timestamp);
            $img_id  = get_field('indice_image', $indice->ID);
            $img_url = '';
            if ($img_id) {
                $img_url = wp_get_attachment_image_url($img_id, 'thumbnail') ?: '';
            }

            $indice_title = get_indice_title($indice);
            $contenu      = wp_strip_all_tags(get_field('indice_contenu', $indice->ID) ?: '');
            $dispo   = get_field('indice_disponibilite', $indice->ID) ?: 'immediate';

            $date_raw   = get_field('indice_date_disponibilite', $indice->ID) ?: '';
            $dt         = null;
            $date_dispo = '';
            if ($date_raw) {
                $dt = convertir_en_datetime($date_raw);
                if ($dt) {
                    $date_dispo = $dt->format('Y-m-d\\TH:i');
                }
            }

            $etat       = get_field('indice_cache_etat_systeme', $indice->ID) ?: '';
            $etat_class = 'etiquette-error';
            $etat_label = __($etat, 'chassesautresor-com');
            if ($etat === 'accessible') {
                $etat_class = 'etiquette-success';
            } elseif ($etat === 'programme' || $etat === 'programmé') {
                $etat_class = 'etiquette-pending';
                if ($dt instanceof DateTimeInterface) {
                    $format     = get_option('date_format') . ' ' . get_option('time_format');
                    $date_label = function_exists('wp_date')
                        ? wp_date($format, $dt->getTimestamp())
                        : date($format, $dt->getTimestamp());
                    $etat_label = sprintf(
                        /* translators: %s: scheduled date */
                        __('programmé le %s', 'chassesautresor-com'),
                        $date_label
                    );
                } else {
                    $etat_label = __('programmé', 'chassesautresor-com');
                }
            }

            $cible_type  = get_field('indice_cible_type', $indice->ID) === 'enigme' ? 'enigme' : 'chasse';
            $cible_label = $cible_type === 'enigme'
                ? __('Énigme', 'chassesautresor-com')
                : __('Chasse', 'chassesautresor-com');
            $linked_html = '';
            $linked      = $cible_type === 'enigme'
                ? get_field('indice_enigme_linked', $indice->ID)
                : get_field('indice_chasse_linked', $indice->ID);
            if ($linked) {
                if (is_array($linked)) {
                    $first     = $linked[0] ?? null;
                    $linked_id = is_array($first) ? ($first['ID'] ?? 0) : $first;
                } else {
                    $linked_id = $linked;
                }
                if (!empty($linked_id)) {
                    $linked_title = get_the_title($linked_id);
                    $linked_html  = '<a href="' . esc_url(get_permalink($linked_id)) . '">' .
                        esc_html($linked_title) . '</a>';
                }
            }
        ?>
        <tr>
            <td>
                <div><?= esc_html($date_value); ?></div>
                <div><?= esc_html(sprintf(__('à %s', 'chassesautresor-com'), $time_value)); ?></div>
            </td>
            <td>
                <div><a href="<?= esc_url(get_permalink($indice)); ?>"><?= esc_html($indice_title); ?></a></div>
                <div><span class="etiquette <?= esc_attr($etat_class); ?>"><?= esc_html($etat_label); ?></span></div>
            </td>
            <?php echo cta_render_proposition_cell($contenu); ?>
            <td>
                <div><span class="etiquette"><?= esc_html($cible_label); ?></span></div>
                <div><?= $linked_html; ?></div>
            </td>
            <td class="indice-actions">
                <div class="indice-action-buttons">
                    <button
                        type="button"
                        class="badge-action edit"
                        data-objet-type="<?= esc_attr($objet_type); ?>"
                        data-objet-id="<?= esc_attr($objet_id); ?>"
                        data-objet-titre="<?= esc_attr($objet_titre); ?>"
                        data-indice-id="<?= esc_attr($indice->ID); ?>"
                        data-indice-rang="<?= esc_attr($indice_rank); ?>"
                        data-indice-image="<?= esc_attr($img_id); ?>"
                        data-indice-image-url="<?= esc_attr($img_url); ?>"
                        data-indice-contenu="<?= esc_attr($contenu); ?>"
                        data-indice-disponibilite="<?= esc_attr($dispo); ?>"
                        data-indice-date="<?= esc_attr($date_dispo); ?>"
                        title="<?= esc_attr__('Éditer', 'chassesautresor-com'); ?>"
                    >
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                    </button>
                    <button
                        type="button"
                        class="badge-action delete"
                        data-indice-id="<?= esc_attr($indice->ID); ?>"
                        data-confirm="<?= esc_attr__('Supprimer cet indice ?', 'chassesautresor-com'); ?>"
                        title="<?= esc_attr__('Supprimer', 'chassesautresor-com'); ?>"
                    >
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php echo cta_render_pager($page, $pages, 'indices-pager'); ?>
