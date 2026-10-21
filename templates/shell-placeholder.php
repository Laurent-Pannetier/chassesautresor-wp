<?php
/**
 * Shell destination: real content for Outils and Progression, placeholder otherwise.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

get_header();

$slug = isset($GLOBALS['cta_shell_page']) ? (string) $GLOBALS['cta_shell_page'] : '';
$titles = function_exists('cta_shell_page_titles') ? cta_shell_page_titles() : [];
$title = $titles[$slug] ?? '';
$show_outils = $slug === 'outils'
    && function_exists('cta_shell_user_sees_outils')
    && cta_shell_user_sees_outils();
$edition = function_exists('cta_shell_edition_target') ? cta_shell_edition_target() : null;
if ($show_outils && is_array($edition) && function_exists('cta_shell_render_edition')) :
    ?>
    <main class="app-outils app-outils--edition" id="app-outils">
        <?php cta_shell_render_edition($edition); ?>
    </main>
    <?php
    get_footer();
    return;
endif;
if ($show_outils) :
    ?>
    <main class="app-outils" id="app-outils">
        <h1 class="app-placeholder__title"><?php echo esc_html($title); ?></h1>
        <?php get_template_part('template-parts/shell/outils'); ?>
    </main>
    <?php
    get_footer();
    return;
endif;
if ($slug === 'equipe') :
    ?>
    <main class="app-equipe" id="app-equipe">
        <h1 class="app-placeholder__title"><?php echo esc_html($title); ?></h1>
        <?php get_template_part('template-parts/shell/equipe'); ?>
    </main>
    <?php
    get_footer();
    return;
endif;
if ($slug === 'progression') :
    ?>
    <main class="app-progression" id="app-progression">
        <h1 class="app-placeholder__title"><?php echo esc_html($title); ?></h1>
        <?php get_template_part('template-parts/shell/progression'); ?>
    </main>
    <?php
    get_footer();
    return;
endif;
?>
<main class="app-placeholder" id="app-placeholder">
    <h1 class="app-placeholder__title"><?php echo esc_html($title); ?></h1>
    <p class="app-placeholder__note">
        <?php esc_html_e('Cette page est un emplacement. Son contenu n’est pas défini.', 'chassesautresor-com'); ?>
    </p>
</main>
<?php
get_footer();
