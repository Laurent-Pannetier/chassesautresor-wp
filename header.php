<?php
/**
 * The header for Astra Theme.
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package Astra
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?><!DOCTYPE html>
<?php astra_html_before(); ?>
<html <?php language_attributes(); ?>>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-GGEM813SKQ"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-GGEM813SKQ');
</script>

<head>
<?php astra_head_top(); ?>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
if ( apply_filters( 'astra_header_profile_gmpg_link', true ) ) {
	?>
	<link rel="profile" href="https://gmpg.org/xfn/11"> 
	<?php
}
?>
<?php wp_head(); ?>
<?php astra_head_bottom(); ?>
</head>

<body <?php astra_schema_body(); ?> <?php body_class(); ?>>
<?php astra_body_top(); ?>
<?php wp_body_open(); ?>

<a
        class="skip-link screen-reader-text"
        href="#content"
        title="<?php echo esc_attr( astra_default_strings( 'string-header-skip-link', false ) ); ?>">
                <?php echo esc_html( astra_default_strings( 'string-header-skip-link', false ) ); ?>
</a>

<div
<?php
        echo wp_kses_post(
                astra_attr(
			'site',
			array(
				'id'    => 'page',
				'class' => 'hfeed site',
			)
		)
	);
	?>
>
	<?php
	astra_header_before();

	astra_header(); 
	?>

    <?php
    astra_header_after();
    
    // ==================================================
    // 🧩 HEADER VISUEL (selon contexte)
    // ==================================================
    $is_account_area      = function_exists( 'is_user_account_area' ) ? is_user_account_area() : false;
    $is_organisation_page = function_exists( 'myaccount_is_organisation_page' ) ? myaccount_is_organisation_page() : false;
    $should_hide_hero     = $is_account_area || $is_organisation_page;

    if ( is_cart() ) {
        get_template_part('template-parts/header-panier');
    } elseif ( is_front_page() && ! $should_hide_hero ) {
        $image_url = imagify_get_webp_url( wp_get_attachment_image_url( 8810, 'full' ) );

        $line1 = sprintf(
            /* translators: 1: ordinal suffix, 2: phrase 'plateforme de'. */
            __( '1<sup>%1$s</sup> %2$s', 'chassesautresor-com' ),
            esc_html__( 'ère', 'chassesautresor-com' ),
            esc_html__( 'plateforme de', 'chassesautresor-com' )
        );

        $titre = sprintf(
            '<span class="hero-title__line1">%1$s</span><span class="hero-title__line2">%2$s</span>',
            wp_kses_post( $line1 ),
            esc_html__( 'chasses au trésor', 'chassesautresor-com' )
        );

        ob_start();
        get_header_fallback(
            [
                'titre'      => $titre,
                'sous_titre' => '',
                'image_fond' => $image_url,
                'logo_id'    => 475,
            ]
        );
        $fallback_markup = ob_get_clean();

        if ( $fallback_markup ) {
            $fallback_markup = preg_replace(
                '/<section(\s+)class="([^\"]*\bbandeau-hero\b[^\"]*)"/',
                '<section$1class="$2" data-home-hero="initial"',
                $fallback_markup,
                1
            );
        }

        $latest_hero_markup = '';

        $latest_chasse_query = new WP_Query(
            [
                'post_type'      => 'chasse',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'meta_query'     => [
                    [
                        'key'   => 'chasse_cache_statut_validation',
                        'value' => 'valide',
                    ],
                ],
                'fields'         => 'ids',
            ]
        );

        if ( $latest_chasse_query->have_posts() && function_exists( 'generer_cta_chasse' ) ) {
            $latest_chasse_id = (int) $latest_chasse_query->posts[0];
            $raw_description  = get_field( 'chasse_principale_description', $latest_chasse_id );

            if ( ! $raw_description ) {
                $raw_description = get_the_excerpt( $latest_chasse_id );
            }

            $description = $raw_description
                ? wp_trim_words( wp_strip_all_tags( (string) $raw_description ), 45, '…' )
                : '';

            $image_fond = '';
            $image_data = get_field( 'chasse_principale_image', $latest_chasse_id );

            if ( is_array( $image_data ) ) {
                if ( ! empty( $image_data['sizes']['chasse-fiche'] ) ) {
                    $image_fond = (string) $image_data['sizes']['chasse-fiche'];
                } elseif ( ! empty( $image_data['ID'] ) ) {
                    $image_fond = (string) wp_get_attachment_image_url( (int) $image_data['ID'], 'chasse-fiche' );
                } elseif ( ! empty( $image_data['url'] ) ) {
                    $image_fond = (string) $image_data['url'];
                }
            } elseif ( ! empty( $image_data ) ) {
                $image_fond = (string) wp_get_attachment_image_url( (int) $image_data, 'chasse-fiche' );
            }

            if ( ! $image_fond ) {
                $image_fond = get_the_post_thumbnail_url( $latest_chasse_id, 'chasse-fiche' );
            }

            if ( ! $image_fond ) {
                $image_fond = get_the_post_thumbnail_url( $latest_chasse_id, 'full' );
            }

            if ( $image_fond && function_exists( 'imagify_get_webp_url' ) ) {
                $webp_url = imagify_get_webp_url( $image_fond );

                if ( $webp_url ) {
                    $image_fond = $webp_url;
                }
            }

            $cta_data = generer_cta_chasse( $latest_chasse_id, get_current_user_id() );

            $latest_cta_type = $cta_data['type'] ?? '';

            if ( $latest_cta_type === 'engage' ) {
                $cta_data['cta_html'] = sprintf(
                    '<a href="%s" class="bouton-secondaire">%s</a>',
                    esc_url( get_permalink( $latest_chasse_id ) . '#chasse-enigmes-wrapper' ),
                    esc_html__( 'Voir mes énigmes', 'chassesautresor-com' )
                );
            } elseif ( $latest_cta_type === 'reset_demo' ) {
                // Laisser le CTA de réinitialisation tel quel pour les chasses de démonstration.
            }

            $cta_data['cta_message'] = '';

            ob_start();
            get_template_part(
                'template-parts/headers/front-page-latest-hero',
                null,
                [
                    'chasse_id'   => $latest_chasse_id,
                    'titre'       => get_the_title( $latest_chasse_id ),
                    'image_fond'  => $image_fond,
                    'description' => $description,
                    'cta_html'    => $cta_data['cta_html'] ?? '',
                    'cta_message' => $cta_data['cta_message'] ?? '',
                ]
            );
            $latest_hero_markup = ob_get_clean();
        }

        wp_reset_postdata();

        if ( $fallback_markup || $latest_hero_markup ) {
            echo '<div class="homepage-hero-wrapper" data-home-hero-wrapper>';

            if ( $fallback_markup ) {
                echo '<div class="homepage-hero homepage-hero--initial" data-home-hero-initial>';
                echo $fallback_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '</div>';
            }

            if ( $latest_hero_markup ) {
                echo '<div class="homepage-hero homepage-hero--latest" data-home-hero-latest>';
                echo $latest_hero_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '</div>';
            }

            echo '</div>';
        }
    } elseif ( is_page() && ! $should_hide_hero ) {
        $has_custom_header = false;

        if ( is_page( 'contact' ) && isset( $_GET['email_organisateur'] ) ) {
            $email_organisateur = sanitize_email( wp_unslash( $_GET['email_organisateur'] ) );

            if ( $email_organisateur ) {
                $organisateur_id = function_exists( 'get_organisateur_id_by_contact_email' )
                    ? get_organisateur_id_by_contact_email( $email_organisateur )
                    : null;

                if ( $organisateur_id ) {
                    get_template_part(
                        'template-parts/organisateur/organisateur-header',
                        null,
                        [
                            'organisateur_id' => $organisateur_id,
                            'onglet_actif'    => 'contact',
                        ]
                    );
                    $has_custom_header = true;
                }
            }
        }

        if ( ! $has_custom_header ) {
            $image_id     = get_post_thumbnail_id();
            $fallback_url = function_exists( 'get_theme_file_uri' )
                ? get_theme_file_uri( 'assets/images/institutionnels.webp' )
                : '';
            $image_url    = '';

            if ( $image_id ) {
                $image_url = wp_get_attachment_image_url( $image_id, 'full' );

                if ( $image_url && function_exists( 'imagify_get_webp_url' ) ) {
                    $webp_url = imagify_get_webp_url( $image_url );

                    if ( $webp_url ) {
                        $image_url = $webp_url;
                    }
                }
            }

            if ( ! $image_url ) {
                $image_url = $fallback_url;
            }

            get_header_fallback(
                [
                    'titre'      => get_the_title(),
                    'sous_titre' => '',
                    'image_fond' => $image_url,
                ]
            );
        }
    }
    
    astra_content_before();
    ?>

	
        <div id="content" class="site-content">
                <div class="ast-container<?php echo ( is_singular('enigme') || is_singular('chasse') ) ? '' : ' ast-container--boxed'; ?>">
                <?php astra_content_top(); ?>
                <?php if (!is_page_template('templates/page-devenir-organisateur.php')) : ?>
                <section class="msg-important"><?php print_site_messages(); ?></section>
                <?php endif; ?>
