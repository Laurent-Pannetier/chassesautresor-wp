<?php
/**
 * Editorial homepage for a site dedicated to one treasure hunt.
 */

defined('ABSPATH') || exit;

$huntId = function_exists('cat_get_primary_hunt_id') ? cat_get_primary_hunt_id() : 0;
$isDemoMode = function_exists('cat_is_demo_mode') && cat_is_demo_mode();

get_header();

if ($huntId <= 0 || get_post_type($huntId) !== 'chasse') {
    ?>
    <main id="home-page" class="single-hunt-home single-hunt-home--empty">
        <section class="single-hunt-home__empty conteneur" aria-labelledby="single-hunt-empty-title">
            <p class="single-hunt-home__eyebrow"><?php esc_html_e('Prochainement', 'chassesautresor-com'); ?></p>
            <h1 id="single-hunt-empty-title">
                <?php esc_html_e('Une nouvelle chasse au trésor se prépare.', 'chassesautresor-com'); ?>
            </h1>
            <p>
                <?php esc_html_e(
                    'Revenez bientôt pour découvrir l’aventure et ses premières énigmes.',
                    'chassesautresor-com'
                ); ?>
            </p>
        </section>
    </main>
    <?php
    get_footer();
    return;
}

$userId = get_current_user_id();
$huntInfo = preparer_infos_affichage_chasse($huntId, $userId);
$fields = $huntInfo['champs'] ?? [];
$description = (string) ($huntInfo['description'] ?? '');
$rewardTitle = trim((string) ($fields['titre_recompense'] ?? ''));
$cta = function_exists('cta_get_primary_hunt_cta')
    ? cta_get_primary_hunt_cta($huntId, $userId)
    : ['cta_html' => '', 'cta_message' => '', 'type' => ''];
$engagementError = isset($_GET['erreur'])
    ? sanitize_text_field(wp_unslash((string) $_GET['erreur']))
    : '';
$frontPage = get_queried_object();
$editorialContent = $frontPage instanceof WP_Post
    ? trim((string) apply_filters('the_content', $frontPage->post_content))
    : '';
?>

<main id="home-page" class="single-hunt-home">
    <?php if ($isDemoMode) : ?>
        <div class="single-hunt-home__demo-notice" role="status">
            <div class="conteneur">
                <?php
                esc_html_e(
                    'Mode démo : cette chasse est présentée sur l’accueil avant sa publication officielle.',
                    'chassesautresor-com'
                );
                ?>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($engagementError === 'points_insuffisants') : ?>
        <div class="single-hunt-home__access-error" role="alert">
            <div class="conteneur">
                <?php
                esc_html_e(
                    'Vous n’avez pas assez de points pour rejoindre cette chasse.',
                    'chassesautresor-com'
                );
                ?>
            </div>
        </div>
    <?php elseif ($engagementError === 'engagement') : ?>
        <div class="single-hunt-home__access-error" role="alert">
            <div class="conteneur">
                <?php
                esc_html_e(
                    'L’engagement n’a pas pu être enregistré.',
                    'chassesautresor-com'
                );
                ?>
            </div>
        </div>
    <?php endif; ?>
    <section class="single-hunt-home__story" aria-labelledby="single-hunt-story-title">
        <div class="conteneur single-hunt-home__story-grid">
            <div>
                <p class="single-hunt-home__eyebrow"><?php esc_html_e('Votre mission', 'chassesautresor-com'); ?></p>
                <h2 id="single-hunt-story-title"><?php echo esc_html(get_the_title($huntId)); ?></h2>
                <?php if ($description !== '') : ?>
                    <div class="single-hunt-home__description">
                        <?php echo wp_kses_post(apply_filters('the_content', $description)); ?>
                    </div>
                <?php endif; ?>
                <?php if ($rewardTitle !== '') : ?>
                    <p class="single-hunt-home__reward">
                        <span aria-hidden="true">✦</span>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %s: reward title. */
                                __('À la clé : %s', 'chassesautresor-com'),
                                $rewardTitle
                            )
                        );
                        ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($cta['cta_message'])) : ?>
                    <div class="single-hunt-home__cta-message">
                        <?php echo wp_kses_post($cta['cta_message']); ?>
                    </div>
                <?php endif; ?>
            </div>
            <ol
                class="single-hunt-steps"
                aria-label="<?php esc_attr_e('Comment participer', 'chassesautresor-com'); ?>"
            >
                <li>
                    <span>1</span>
                    <div>
                        <h3><?php esc_html_e('Rejoignez la chasse', 'chassesautresor-com'); ?></h3>
                        <p>
                            <?php
                            esc_html_e(
                                'Connectez-vous puis confirmez votre participation.',
                                'chassesautresor-com'
                            );
                            ?>
                        </p>
                    </div>
                </li>
                <li>
                    <span>2</span>
                    <div>
                        <h3><?php esc_html_e('Explorez les énigmes', 'chassesautresor-com'); ?></h3>
                        <p>
                            <?php
                            esc_html_e(
                                'Progressez à votre rythme et débloquez la suite du parcours.',
                                'chassesautresor-com'
                            );
                            ?>
                        </p>
                    </div>
                </li>
                <li>
                    <span>3</span>
                    <div>
                        <h3><?php esc_html_e('Trouvez le trésor', 'chassesautresor-com'); ?></h3>
                        <p>
                            <?php
                            esc_html_e(
                                'Rassemblez vos découvertes pour aller au bout de l’aventure.',
                                'chassesautresor-com'
                            );
                            ?>
                        </p>
                    </div>
                </li>
            </ol>
        </div>
    </section>

    <?php if ($editorialContent !== '') : ?>
        <section
            class="single-hunt-home__editorial"
            aria-label="<?php esc_attr_e('À propos de la chasse', 'chassesautresor-com'); ?>"
        >
            <div class="conteneur entry-content">
                <?php echo $editorialContent; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="single-hunt-home__final-cta" aria-labelledby="single-hunt-final-title">
        <div class="conteneur">
            <p class="single-hunt-home__eyebrow"><?php esc_html_e('Prêt à partir ?', 'chassesautresor-com'); ?></p>
            <h2 id="single-hunt-final-title">
                <?php esc_html_e('Votre prochaine découverte commence ici.', 'chassesautresor-com'); ?>
            </h2>
            <div class="single-hunt-home__actions single-hunt-home__actions--centered">
                <?php echo $cta['cta_html'] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>
