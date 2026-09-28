<?php
/**
 * Homepage displaying all valid hunts.
 */

defined('ABSPATH') || exit;

$points_history = '';
if (is_user_logged_in() && function_exists('render_points_history_table')) {
    $points_history = render_points_history_table((int) get_current_user_id());
}

get_header();

$search_term  = ca_get_search_term('home-hunts');
$home_filters = ca_home_filter_chasse_ids([
    'search' => $search_term,
]);
$filters_nonce = wp_create_nonce('ca-filter-chasses');

$chasse_ids             = $home_filters['ids'];
$normalized_filters     = $home_filters['filters_normalises'] ?? [];
$default_status_filter  = is_string($normalized_filters['statut'] ?? null)
    ? $normalized_filters['statut']
    : 'tous';
$default_cost_filters   = is_array($normalized_filters['cout'] ?? null)
    ? $normalized_filters['cout']
    : ['gratuit', 'points'];
$initial_results_count  = (int) ($home_filters['total'] ?? count($chasse_ids));

$available_filters = [];
if (isset($home_filters['available_filters']) && is_array($home_filters['available_filters'])) {
    $available_filters = $home_filters['available_filters'];
}

$available_status_counts = [];
if (isset($available_filters['statut']) && is_array($available_filters['statut'])) {
    foreach ($available_filters['statut'] as $status_value => $count) {
        $available_status_counts[$status_value] = (int) $count;
    }
}

$available_cost_counts = [];
if (isset($available_filters['cout']) && is_array($available_filters['cout'])) {
    foreach ($available_filters['cout'] as $cost_value => $count) {
        $available_cost_counts[$cost_value] = (int) $count;
    }
}

if ('tous' !== $default_status_filter) {
    $status_available_count = $available_status_counts[$default_status_filter] ?? 0;
    if ($status_available_count <= 0) {
        $default_status_filter = 'tous';
    }
}

$available_cost_values = [];
foreach ($available_cost_counts as $cost_value => $count) {
    if ($count > 0) {
        $available_cost_values[] = $cost_value;
    }
}

$available_cost_values = array_values(array_unique($available_cost_values));

$default_cost_filters = array_values(array_intersect($default_cost_filters, $available_cost_values));

if (empty($default_cost_filters) && !empty($available_cost_values)) {
    $default_cost_filters = $available_cost_values;
}

$status_options = [
    'tous'     => __('Tous les statuts', 'chassesautresor-com'),
    'en_cours' => __('En cours', 'chassesautresor-com'),
    'a_venir'  => __('À venir', 'chassesautresor-com'),
    'termine'  => __('Terminées', 'chassesautresor-com'),
];

$cost_options = [
    'gratuit' => [
        'label' => __('Gratuit', 'chassesautresor-com'),
        'id'    => 'home-hunts-cost-free',
    ],
    'points'  => [
        'label' => __('Points', 'chassesautresor-com'),
        'id'    => 'home-hunts-cost-points',
    ],
];

$results_label = sprintf(
    _n('%d résultat', '%d résultats', $initial_results_count, 'chassesautresor-com'),
    $initial_results_count
);

$filters_reset_label       = __('Réinitialiser les filtres', 'chassesautresor-com');
$reset_icon_markup         = function_exists('cta_get_reset_icon_markup')
    ? cta_get_reset_icon_markup()
    : '';
$filters_toggle_label      = __('Afficher les filtres', 'chassesautresor-com');
$search_toggle_label       = __('Afficher la recherche', 'chassesautresor-com');
$filter_toggle_icon_markup = '<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">'
    . '<path fill="currentColor" d="M4 5a1 1 0 0 1 1-1h14a1 1 0 0 1 .78 1.63l-5.58 6.7a1 1 0 0 0-.22.63v4.54a1 1 0 0 1-.55.9l-3 1.5A1 1 0 0 1 9 19.5v-5.54a1 1 0 0 0-.22-.63L3.2 6.63A1 1 0 0 1 4 5Z" />'
    . '</svg>';
$search_toggle_icon_markup = function_exists('cta_get_table_search_submit_icon_markup')
    ? cta_get_table_search_submit_icon_markup('search')
    : '';

ob_start();
?>
<div class="home-hunts__toolbar" data-home-hunts-toolbar>
    <div class="home-hunts__toolbar-toggles">
        <button
            type="button"
            class="home-hunts__toolbar-toggle"
            data-home-hunts-toggle="filters"
            aria-controls="home-hunts-filters-panel"
            aria-expanded="false"
        >
            <span class="home-hunts__toolbar-toggle-icon" aria-hidden="true">
                <?php echo $filter_toggle_icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <span class="screen-reader-text"><?php echo esc_html($filters_toggle_label); ?></span>
        </button>
        <button
            type="button"
            class="home-hunts__toolbar-toggle"
            data-home-hunts-toggle="search"
            aria-controls="home-hunts-search-panel"
            aria-expanded="false"
        >
            <span class="home-hunts__toolbar-toggle-icon" aria-hidden="true">
                <?php echo $search_toggle_icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <span class="screen-reader-text"><?php echo esc_html($search_toggle_label); ?></span>
        </button>
    </div>
    <div
        class="home-hunts__panel home-hunts__filters"
        data-home-hunts-panel="filters"
        id="home-hunts-filters-panel"
        data-collapsed="false"
    >
        <form
            class="home-hunts__filters-form"
            data-home-hunts-filters
            aria-label="<?php echo esc_attr(__('Filtrer les chasses', 'chassesautresor-com')); ?>"
        >
            <div class="home-hunts__filters-group home-hunts__filters-group--status">
                <select
                    id="home-hunts-status"
                    name="home-hunts-status"
                    data-home-hunts-select="statut"
                    data-default-value="<?php echo esc_attr($default_status_filter); ?>"
                    aria-label="<?php echo esc_attr(__('Statut', 'chassesautresor-com')); ?>"
                >
                    <?php foreach ($status_options as $status_value => $status_label) : ?>
                        <?php
                        $status_count        = $available_status_counts[$status_value] ?? 0;
                        $status_is_available = ('tous' === $status_value) || ($status_count > 0);
                        ?>
                        <option
                            value="<?php echo esc_attr($status_value); ?>"
                            data-home-hunts-status-option="<?php echo esc_attr($status_value); ?>"
                            <?php echo selected($default_status_filter, $status_value, false); ?>
                            <?php if (!$status_is_available) : ?>hidden disabled<?php endif; ?>
                        >
                            <?php echo esc_html($status_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <fieldset
                class="home-hunts__filters-group home-hunts__filters-group--cost"
                data-home-hunts-cost-group
                aria-label="<?php echo esc_attr(__('Coût', 'chassesautresor-com')); ?>"
            >
                <?php foreach ($cost_options as $cost_value => $cost_option) : ?>
                    <?php
                    $is_cost_available = in_array($cost_value, $available_cost_values, true);
                    $is_cost_checked  = in_array($cost_value, $default_cost_filters, true);
                    $cost_input_id    = $cost_option['id'] ?? ('home-hunts-cost-' . $cost_value);
                    ?>
                    <div
                        class="home-hunts__filters-checkbox"
                        data-home-hunts-cost-option="<?php echo esc_attr($cost_value); ?>"<?php echo $is_cost_available ? '' : ' hidden'; ?>
                    >
                        <input
                            type="checkbox"
                            id="<?php echo esc_attr($cost_input_id); ?>"
                            name="home-hunts-cost[]"
                            value="<?php echo esc_attr($cost_value); ?>"
                            data-home-hunts-checkbox="<?php echo esc_attr($cost_value); ?>"
                            data-default-checked="<?php echo $is_cost_checked ? 'true' : 'false'; ?>"
                            <?php echo checked($is_cost_checked, true, false); ?>
                            <?php echo disabled($is_cost_available, false, false); ?>
                        />
                        <label for="<?php echo esc_attr($cost_input_id); ?>"><?php echo esc_html($cost_option['label'] ?? ''); ?></label>
                    </div>
                <?php endforeach; ?>
            </fieldset>
            <div class="home-hunts__filters-actions">
                <button
                    type="reset"
                    class="home-hunts__filters-reset"
                    data-home-hunts-reset
                    aria-label="<?php echo esc_attr($filters_reset_label); ?>"
                >
                    <?php if ($reset_icon_markup) : ?>
                        <span class="home-hunts__filters-reset-icon" aria-hidden="true">
                            <?php echo $reset_icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </span>
                    <?php endif; ?>
                </button>
                <span
                    class="home-hunts__filters-count"
                    data-home-hunts-count
                    data-default-count="<?php echo esc_attr($initial_results_count); ?>"
                    hidden
                    aria-hidden="true"
                ><?php echo esc_html($results_label); ?></span>
            </div>
        </form>
    </div>
    <div
        class="home-hunts__panel home-hunts__search"
        data-home-hunts-panel="search"
        id="home-hunts-search-panel"
        data-collapsed="false"
    >
        <?php
        echo cta_render_search_form('home-hunts', [
            'class'             => 'home-hunts__search-form table-search--inline table-search--compact',
            'label'             => '',
            'placeholder'       => __('Rechercher une chasse', 'chassesautresor-com'),
            'submit_icon'       => 'search',
            'submit_icon_only'  => true,
            'show_reset_button' => true,
            'data_attributes'   => [
                'home-hunts-search' => '1',
            ],
        ]);
        ?>
    </div>
</div>
<?php
$before_items_markup = ob_get_clean();

$initial_feedback_message = '';
if ($initial_results_count <= 0 && !empty($home_filters['message'])) {
    $initial_feedback_message = esc_html((string) $home_filters['message']);
}

$after_items_markup = sprintf(
    '<div class="home-hunts__feedback" data-home-hunts-feedback aria-live="polite">%s</div>',
    $initial_feedback_message
);
?>

<div class="separateur-avec-icone">
    <span class="trait-gauche"></span>
    <span class="icone-svg">
        <?php
        $separator_icon = get_svg_icon('separateur-avec-icone');
        if (!empty($separator_icon)) {
            echo $separator_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>
    </span>
    <span class="trait-droite"></span>
</div>

<div id="primary" class="content-area">
    <main id="home-page">
        <section class="chasses">
            <div class="conteneur">
                <div
                    class="liste-chasses"
                    data-home-hunts="true"
                    data-nonce="<?php echo esc_attr($filters_nonce); ?>"
                >
                    <?php echo $before_items_markup; ?>
                    <?php
                    get_template_part('template-parts/organisateur/organisateur-partial-boucle-chasses', null, [
                        'chasse_ids' => $chasse_ids,
                        'show_header' => false,
                        'grid_class' => 'organisateur-chasses-grid',
                        'before_items' => '',
                        'after_items' => $after_items_markup,
                    ]);
                    ?>
                </div>
            </div>
        </section>
        <?php if ($points_history) : ?>
            <section class="points-history">
                <div class="conteneur">
                    <?php echo $points_history; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>

<?php get_footer(); ?>
