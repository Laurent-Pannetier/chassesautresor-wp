<?php
/**
 * Écran de connexion. L'image de chasse reste dans un cadre 16/9.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

$auth = function_exists('cta_auth_screen_context') ? cta_auth_screen_context() : [];
$view = (string) ($auth['view'] ?? 'login');
$error = function_exists('cat_social_auth_error_message')
    ? cat_social_auth_error_message((string) ($auth['error'] ?? ''))
    : '';
$info = function_exists('cat_social_auth_info_message')
    ? cat_social_auth_info_message((string) ($auth['info'] ?? ''))
    : '';
$hunt = is_array($auth['hunt'] ?? null) ? $auth['hunt'] : [];
$redirect = (string) ($auth['redirect'] ?? '');
$pageUrl = (string) ($auth['page_url'] ?? '');
$privacyUrl = (string) ($auth['privacy_url'] ?? '');
$linkProvider = (string) ($auth['link_provider'] ?? '');
$linkName = $linkProvider === 'google' ? 'Google' : 'Discord';
$emailOpen = !empty($auth['email_open']);
$showSocialConfirm = str_starts_with((string) ($auth['info'] ?? ''), 'social_');

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('auth-gate'); ?>>
<main class="auth-gate__stage">
    <section class="auth-card">
        <div class="auth-card__story">
            <figure class="auth-card__figure<?php echo empty($hunt['image']) ? ' auth-card__figure--empty' : ''; ?>">
                <?php if (!empty($hunt['image'])) : ?>
                    <img
                        class="auth-card__image"
                        src="<?php echo esc_url((string) $hunt['image']); ?>"
                        alt="<?php echo esc_attr((string) ($hunt['alt'] ?? '')); ?>"
                    >
                <?php endif; ?>
                <div class="auth-card__scrim">
                    <a class="auth-card__logo" href="<?php echo esc_url((string) ($auth['home_url'] ?? '/')); ?>">
                        <?php
                        if (!empty($auth['logo_html'])) {
                            echo $auth['logo_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        } else {
                            echo esc_html((string) ($auth['site_name'] ?? ''));
                        }
                        ?>
                    </a>
                    <a class="auth-card__back" href="<?php echo esc_url((string) ($auth['back_url'] ?? '/')); ?>">
                        <?php esc_html_e('Retour au site', 'chassesautresor-com'); ?>
                    </a>
                </div>
            </figure>
            <div class="auth-card__caption">
                <h1><?php echo esc_html((string) ($hunt['title'] ?? '')); ?></h1>
                <?php if (!empty($hunt['description'])) : ?>
                    <p><?php echo esc_html((string) $hunt['description']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="auth-card__panel">
            <?php if ($view === 'inscription') : ?>
                <h2><?php esc_html_e('Créer un compte', 'chassesautresor-com'); ?></h2>
            <?php elseif ($view === 'mot-de-passe') : ?>
                <h2><?php esc_html_e('Mot de passe oublié', 'chassesautresor-com'); ?></h2>
            <?php elseif ($view === 'relier') : ?>
                <h2><?php esc_html_e('Relier ce compte', 'chassesautresor-com'); ?></h2>
            <?php elseif ($view === 'cgu') : ?>
                <h2><?php esc_html_e('Avant de continuer', 'chassesautresor-com'); ?></h2>
            <?php else : ?>
                <h2><?php esc_html_e('Connexion', 'chassesautresor-com'); ?></h2>
            <?php endif; ?>

            <?php if ($error !== '') : ?>
                <p class="auth-notice auth-notice--error" role="alert"><?php echo esc_html($error); ?></p>
            <?php endif; ?>
            <?php if ($info !== '') : ?>
                <p class="auth-notice auth-notice--info" role="status"><?php echo esc_html($info); ?></p>
            <?php endif; ?>

            <?php if ($view === 'cgu') : ?>
                <p class="auth-card__lead">
                    <?php esc_html_e(
                        'Acceptez les conditions pour terminer la création du compte.',
                        'chassesautresor-com'
                    ); ?>
                </p>
                <form class="auth-form" method="post" action="<?php echo esc_url($pageUrl); ?>">
                    <?php wp_nonce_field('cat_auth_cgu'); ?>
                    <input type="hidden" name="cat_auth_action" value="cgu">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">
                    <label class="auth-check">
                        <input type="checkbox" name="cgu" value="1" required>
                        <span><?php cta_auth_terms_html($privacyUrl); ?></span>
                    </label>
                    <button class="auth-submit" type="submit">
                        <?php esc_html_e('Continuer', 'chassesautresor-com'); ?>
                    </button>
                </form>
            <?php elseif ($view === 'relier') : ?>
                <p class="auth-card__lead">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %s: Discord or Google. */
                        __(
                            'Un compte existe déjà. Entrez son mot de passe pour le relier à %s.',
                            'chassesautresor-com'
                        ),
                        $linkName
                    ));
                    ?>
                </p>
                <?php if (empty($auth['link_valid']) && $error === '') : ?>
                    <p class="auth-notice auth-notice--error" role="alert">
                        <?php
                        $expired = function_exists('cat_social_auth_error_message')
                            ? cat_social_auth_error_message('lien_expire')
                            : '';
                        echo esc_html($expired);
                        ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($auth['link_valid'])) : ?>
                    <form class="auth-form" method="post" action="<?php echo esc_url($pageUrl); ?>">
                        <?php wp_nonce_field('cat_auth_link'); ?>
                        <input type="hidden" name="cat_auth_action" value="link">
                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">
                        <label class="auth-field" for="auth-link-password">
                            <span><?php esc_html_e('Mot de passe', 'chassesautresor-com'); ?></span>
                            <span class="auth-field__control">
                                <input
                                    id="auth-link-password"
                                    type="password"
                                    name="pwd"
                                    autocomplete="current-password"
                                    required
                                >
                                <button
                                    class="auth-field__reveal"
                                    type="button"
                                    data-auth-password-toggle="auth-link-password"
                                    data-label-show="<?php esc_attr_e('Afficher', 'chassesautresor-com'); ?>"
                                    data-label-hide="<?php esc_attr_e('Masquer', 'chassesautresor-com'); ?>"
                                    aria-pressed="false"
                                >
                                    <?php esc_html_e('Afficher', 'chassesautresor-com'); ?>
                                </button>
                            </span>
                        </label>
                        <button class="auth-submit" type="submit">
                            <?php esc_html_e('Relier et se connecter', 'chassesautresor-com'); ?>
                        </button>
                    </form>
                <?php endif; ?>
                <p class="auth-card__switch">
                    <a href="<?php echo esc_url((string) ($auth['login_url'] ?? '')); ?>">
                        <?php esc_html_e('Retour à la connexion', 'chassesautresor-com'); ?>
                    </a>
                </p>
            <?php elseif ($view === 'mot-de-passe') : ?>
                <form class="auth-form" method="post" action="<?php echo esc_url($pageUrl); ?>">
                    <?php wp_nonce_field('cat_auth_lostpassword'); ?>
                    <input type="hidden" name="cat_auth_action" value="lostpassword">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">
                    <?php if ($showSocialConfirm) : ?>
                        <input type="hidden" name="cat_social_password_setup" value="1">
                    <?php endif; ?>
                    <label class="auth-field" for="auth-reset-login">
                        <span><?php esc_html_e('E-mail ou identifiant', 'chassesautresor-com'); ?></span>
                        <input id="auth-reset-login" type="text" name="user_login" autocomplete="username" required>
                    </label>
                    <button class="auth-submit" type="submit">
                        <?php
                        echo esc_html($showSocialConfirm
                            ? __('Recevoir le lien', 'chassesautresor-com')
                            : __('Envoyer le lien', 'chassesautresor-com'));
                        ?>
                    </button>
                </form>
                <p class="auth-card__switch">
                    <a href="<?php echo esc_url((string) ($auth['login_url'] ?? '')); ?>">
                        <?php esc_html_e('Retour à la connexion', 'chassesautresor-com'); ?>
                    </a>
                </p>
            <?php elseif ($view === 'inscription') : ?>
                <p class="auth-card__lead">
                    <?php esc_html_e(
                        'Créez votre compte avec Discord, ou avec Google.',
                        'chassesautresor-com'
                    ); ?>
                </p>
                <?php cta_auth_provider_buttons($pageUrl, $redirect, 'inscription'); ?>
                <p class="auth-card__or"><span><?php esc_html_e('ou', 'chassesautresor-com'); ?></span></p>
                <?php if (empty($auth['registration_open'])) : ?>
                    <p class="auth-card__lead">
                        <?php esc_html_e(
                            'La création de compte par e-mail est fermée.',
                            'chassesautresor-com'
                        ); ?>
                    </p>
                <?php else : ?>
                    <form class="auth-form" method="post" action="<?php echo esc_url($pageUrl); ?>">
                        <?php wp_nonce_field('cat_auth_register'); ?>
                        <input type="hidden" name="cat_auth_action" value="register">
                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">
                        <label class="auth-field" for="auth-register-email">
                            <span><?php esc_html_e('E-mail', 'chassesautresor-com'); ?></span>
                            <input
                                id="auth-register-email"
                                type="email"
                                name="user_email"
                                autocomplete="email"
                                required
                            >
                        </label>
                        <label class="auth-field" for="auth-register-name">
                            <span><?php esc_html_e('Nom affiché', 'chassesautresor-com'); ?></span>
                            <input id="auth-register-name" type="text" name="display_name" autocomplete="nickname">
                        </label>
                        <label class="auth-check">
                            <input type="checkbox" name="cgu" value="1" required>
                            <span><?php cta_auth_terms_html($privacyUrl); ?></span>
                        </label>
                        <button class="auth-submit" type="submit">
                            <?php esc_html_e('Créer le compte', 'chassesautresor-com'); ?>
                        </button>
                    </form>
                <?php endif; ?>
                <p class="auth-card__switch">
                    <a href="<?php echo esc_url((string) ($auth['login_url'] ?? '')); ?>">
                        <?php esc_html_e('Déjà un compte ? Se connecter', 'chassesautresor-com'); ?>
                    </a>
                </p>
            <?php else : ?>
                <p class="auth-card__lead">
                    <?php esc_html_e(
                        'Connectez-vous avec Discord, ou avec Google.',
                        'chassesautresor-com'
                    ); ?>
                </p>
                <?php cta_auth_provider_buttons($pageUrl, $redirect, 'login'); ?>
                <p class="auth-card__or"><span><?php esc_html_e('ou', 'chassesautresor-com'); ?></span></p>
                <button
                    class="auth-email-toggle"
                    type="button"
                    data-auth-email-toggle
                    aria-expanded="<?php echo $emailOpen ? 'true' : 'false'; ?>"
                    aria-controls="auth-email-panel"
                >
                    <?php esc_html_e('Continuer avec un e-mail', 'chassesautresor-com'); ?>
                </button>

                <form
                    id="auth-email-panel"
                    class="auth-form auth-email"
                    method="post"
                    action="<?php echo esc_url((string) ($auth['login_action'] ?? '')); ?>"
                    <?php echo $emailOpen ? '' : 'hidden'; ?>
                    data-auth-email
                >
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">
                    <label class="auth-field" for="auth-login">
                        <span><?php esc_html_e('E-mail ou identifiant', 'chassesautresor-com'); ?></span>
                        <input id="auth-login" type="text" name="log" autocomplete="username" required>
                    </label>
                    <label class="auth-field" for="auth-password">
                        <span><?php esc_html_e('Mot de passe', 'chassesautresor-com'); ?></span>
                        <span class="auth-field__control">
                            <input
                                id="auth-password"
                                type="password"
                                name="pwd"
                                autocomplete="current-password"
                                required
                            >
                            <button
                                class="auth-field__reveal"
                                type="button"
                                data-auth-password-toggle="auth-password"
                                data-label-show="<?php esc_attr_e('Afficher', 'chassesautresor-com'); ?>"
                                data-label-hide="<?php esc_attr_e('Masquer', 'chassesautresor-com'); ?>"
                                aria-pressed="false"
                            >
                                <?php esc_html_e('Afficher', 'chassesautresor-com'); ?>
                            </button>
                        </span>
                    </label>
                    <label class="auth-check">
                        <input type="checkbox" name="rememberme" value="forever">
                        <span><?php esc_html_e('Se souvenir de moi', 'chassesautresor-com'); ?></span>
                    </label>
                    <button class="auth-submit" type="submit">
                        <?php esc_html_e('Se connecter', 'chassesautresor-com'); ?>
                    </button>
                    <p class="auth-card__switch">
                        <a href="<?php echo esc_url((string) ($auth['lost_password_url'] ?? '')); ?>">
                            <?php esc_html_e('Mot de passe oublié', 'chassesautresor-com'); ?>
                        </a>
                    </p>
                </form>

                <?php if (!empty($auth['registration_open'])) : ?>
                    <p class="auth-card__switch">
                        <a href="<?php echo esc_url((string) ($auth['register_url'] ?? '')); ?>">
                            <?php esc_html_e('Créer un compte avec un e-mail', 'chassesautresor-com'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php wp_footer(); ?>
</body>
</html>
