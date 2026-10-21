<?php
/**
 * Account profile: identity, password, addresses and recent orders.
 *
 * Field names stay compatible with WooCommerce save_account_details.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_edit_account_form');

$last_active = function_exists('myaccount_format_last_active')
    ? myaccount_format_last_active((int) $user->ID)
    : '';

$password_open = false;
$nonce = isset($_POST['save-account-details-nonce'])
    ? sanitize_text_field(wp_unslash($_POST['save-account-details-nonce']))
    : '';
if ($nonce !== '' && wp_verify_nonce($nonce, 'save_account_details')) {
    $password_open = (string) wp_unslash($_POST['password_current'] ?? '') !== ''
        || (string) wp_unslash($_POST['password_1'] ?? '') !== ''
        || (string) wp_unslash($_POST['password_2'] ?? '') !== '';
}

$orders_output = function_exists('afficher_commandes_utilisateur')
    ? afficher_commandes_utilisateur((int) $user->ID, 3)
    : '';
$orders_url = function_exists('wc_get_account_endpoint_url')
    ? wc_get_account_endpoint_url('orders')
    : home_url('/mon-compte/orders/');
$custom_avatar = function_exists('get_user_meta')
    ? (string) get_user_meta((int) $user->ID, 'user_avatar', true)
    : '';
$has_custom_avatar = $custom_avatar !== '';
?>

<div class="account-profile">
    <p class="account-profile__intro">
        <?php esc_html_e('Nom, photo, e-mail et mot de passe de votre compte.', 'chassesautresor-com'); ?>
    </p>
    <div class="account-profile__grid">
        <form
            class="woocommerce-EditAccountForm edit-account account-profile__main"
            action=""
            method="post"
            <?php do_action('woocommerce_edit_account_form_tag'); ?>
        >
            <?php do_action('woocommerce_edit_account_form_start'); ?>

            <section class="account-panel" aria-labelledby="account-identity-title">
                <h2 id="account-identity-title" class="account-panel__title">
                    <?php esc_html_e('Identité', 'chassesautresor-com'); ?>
                </h2>
                <div class="account-avatar">
                    <div class="profile-avatar-container">
                        <div class="profile-avatar">
                            <?php
                            if (function_exists('get_avatar')) {
                                echo get_avatar(
                                    (int) $user->ID,
                                    96,
                                    '',
                                    (string) $user->display_name
                                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            }
                            ?>
                        </div>
                    </div>
                    <div class="account-avatar__body">
                        <p class="account-avatar__title">
                            <?php esc_html_e('Photo de profil', 'chassesautresor-com'); ?>
                        </p>
                        <p id="account-avatar-hint" class="account-field__hint">
                            <?php esc_html_e(
                                'JPG, PNG, GIF ou WEBP, 2 Mo maximum. La photo est enregistrée immédiatement.',
                                'chassesautresor-com'
                            ); ?>
                        </p>
                        <div class="account-avatar__actions">
                            <button
                                type="button"
                                class="account-button account-button--ghost upload-avatar-btn"
                                id="upload-avatar-trigger"
                                aria-describedby="account-avatar-hint"
                            >
                                <?php esc_html_e('Changer la photo', 'chassesautresor-com'); ?>
                            </button>
                            <button
                                type="button"
                                class="account-button account-button--ghost account-avatar__remove"
                                id="remove-avatar"
                                <?php echo $has_custom_avatar ? '' : 'hidden'; ?>
                            >
                                <?php esc_html_e('Retirer', 'chassesautresor-com'); ?>
                            </button>
                        </div>
                        <p class="message-size-file-avatar">
                            <?php esc_html_e('Taille dépassée : 2 Mo max.', 'chassesautresor-com'); ?>
                        </p>
                        <p class="message-format-file-avatar">
                            <?php esc_html_e(
                                'Format non autorisé. Formats autorisés : JPG, PNG, GIF, WEBP.',
                                'chassesautresor-com'
                            ); ?>
                        </p>
                        <p
                            id="avatar-upload-message"
                            class="account-avatar__status"
                            role="status"
                            aria-live="polite"
                        ></p>
                    </div>
                    <input
                        type="file"
                        id="upload-avatar"
                        class="account-avatar__file"
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        tabindex="-1"
                        aria-hidden="true"
                    />
                </div>
                <div class="account-fields">
                    <p class="account-field__hint">
                        <?php esc_html_e(
                            'Les champs marqués d’une étoile sont obligatoires.',
                            'chassesautresor-com'
                        ); ?>
                    </p>
                    <div class="account-fields__pair">
                        <p class="woocommerce-form-row form-row form-row-first">
                            <label for="account_first_name">
                                <?php esc_html_e('Prénom', 'chassesautresor-com'); ?>
                                <span class="required" aria-hidden="true">*</span>
                            </label>
                            <input
                                type="text"
                                class="woocommerce-Input woocommerce-Input--text input-text"
                                name="account_first_name"
                                id="account_first_name"
                                autocomplete="given-name"
                                value="<?php echo esc_attr($user->first_name); ?>"
                                aria-required="true"
                                required
                            />
                        </p>
                        <p class="woocommerce-form-row form-row form-row-last">
                            <label for="account_last_name">
                                <?php esc_html_e('Nom', 'chassesautresor-com'); ?>
                                <span class="required" aria-hidden="true">*</span>
                            </label>
                            <input
                                type="text"
                                class="woocommerce-Input woocommerce-Input--text input-text"
                                name="account_last_name"
                                id="account_last_name"
                                autocomplete="family-name"
                                value="<?php echo esc_attr($user->last_name); ?>"
                                aria-required="true"
                                required
                            />
                        </p>
                    </div>
                    <p class="woocommerce-form-row form-row form-row-wide">
                        <label for="account_display_name">
                            <?php esc_html_e('Nom affiché', 'chassesautresor-com'); ?>
                            <span class="required" aria-hidden="true">*</span>
                        </label>
                        <input
                            type="text"
                            class="woocommerce-Input woocommerce-Input--text input-text"
                            name="account_display_name"
                            id="account_display_name"
                            aria-describedby="account_display_name_description"
                            value="<?php echo esc_attr($user->display_name); ?>"
                            aria-required="true"
                            required
                        />
                        <span id="account_display_name_description" class="account-field__hint">
                            <?php esc_html_e('Nom visible dans le compte.', 'chassesautresor-com'); ?>
                        </span>
                    </p>
                    <p class="woocommerce-form-row form-row form-row-wide">
                        <label for="account_email">
                            <?php esc_html_e('E-mail', 'chassesautresor-com'); ?>
                            <span class="required" aria-hidden="true">*</span>
                        </label>
                        <input
                            type="email"
                            class="woocommerce-Input woocommerce-Input--email input-text"
                            name="account_email"
                            id="account_email"
                            autocomplete="email"
                            value="<?php echo esc_attr($user->user_email); ?>"
                            aria-required="true"
                            required
                        />
                        <?php if ($last_active !== '') : ?>
                            <span class="account-field__hint">
                                <?php
                                printf(
                                    /* translators: %s: date of the last connection. */
                                    esc_html__('Dernière connexion le %s', 'chassesautresor-com'),
                                    esc_html($last_active)
                                );
                                ?>
                            </span>
                        <?php endif; ?>
                    </p>
                    <?php do_action('woocommerce_edit_account_form_fields'); ?>
                </div>
                <?php do_action('woocommerce_edit_account_form'); ?>
                <div class="account-panel__actions">
                    <button
                        type="submit"
                        class="account-button"
                        name="save_account_details"
                        value="<?php esc_attr_e('Enregistrer', 'chassesautresor-com'); ?>"
                    >
                        <?php esc_html_e('Enregistrer', 'chassesautresor-com'); ?>
                    </button>
                </div>
            </section>

            <details class="account-panel account-panel--disclosure"<?php echo $password_open ? ' open' : ''; ?>>
                <summary class="account-panel__summary">
                    <h2 class="account-panel__title">
                        <?php esc_html_e('Mot de passe', 'chassesautresor-com'); ?>
                    </h2>
                    <span class="account-panel__chevron" aria-hidden="true"></span>
                </summary>
                <div class="account-panel__body">
                    <p class="account-field__hint">
                        <?php esc_html_e(
                            'Laissez les champs vides pour conserver le mot de passe actuel.',
                            'chassesautresor-com'
                        ); ?>
                    </p>
                    <p class="woocommerce-form-row form-row form-row-wide">
                        <label for="password_current">
                            <?php esc_html_e('Mot de passe actuel', 'chassesautresor-com'); ?>
                        </label>
                        <input
                            type="password"
                            class="woocommerce-Input woocommerce-Input--password input-text"
                            name="password_current"
                            id="password_current"
                            autocomplete="current-password"
                        />
                    </p>
                    <p class="woocommerce-form-row form-row form-row-wide">
                        <label for="password_1">
                            <?php esc_html_e('Nouveau mot de passe', 'chassesautresor-com'); ?>
                        </label>
                        <input
                            type="password"
                            class="woocommerce-Input woocommerce-Input--password input-text"
                            name="password_1"
                            id="password_1"
                            autocomplete="new-password"
                        />
                    </p>
                    <p class="woocommerce-form-row form-row form-row-wide">
                        <label for="password_2">
                            <?php esc_html_e('Confirmation', 'chassesautresor-com'); ?>
                        </label>
                        <input
                            type="password"
                            class="woocommerce-Input woocommerce-Input--password input-text"
                            name="password_2"
                            id="password_2"
                            autocomplete="new-password"
                        />
                    </p>
                    <div class="account-panel__actions">
                        <button
                            type="submit"
                            class="account-button"
                            name="save_account_details"
                            value="<?php esc_attr_e('Mettre à jour', 'chassesautresor-com'); ?>"
                        >
                            <?php esc_html_e('Mettre à jour', 'chassesautresor-com'); ?>
                        </button>
                    </div>
                </div>
            </details>

            <?php wp_nonce_field('save_account_details', 'save-account-details-nonce'); ?>
            <input type="hidden" name="action" value="save_account_details" />
            <?php do_action('woocommerce_edit_account_form_end'); ?>
        </form>

        <div class="account-profile__side">
            <div class="myaccount-addresses">
                <?php wc_get_template('myaccount/my-address.php'); ?>
            </div>
            <section class="account-panel account-orders" aria-labelledby="account-orders-title">
                <h2 id="account-orders-title" class="account-panel__title">
                    <?php esc_html_e('Commandes', 'chassesautresor-com'); ?>
                </h2>
                <?php if ($orders_output) : ?>
                    <div class="account-orders__list">
                        <?php echo $orders_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                    <p class="account-orders__more">
                        <a class="account-button account-button--ghost" href="<?php echo esc_url($orders_url); ?>">
                            <?php esc_html_e('Voir toutes les commandes', 'chassesautresor-com'); ?>
                        </a>
                    </p>
                <?php else : ?>
                    <p class="account-orders__empty">
                        <?php esc_html_e('Aucune commande pour le moment.', 'chassesautresor-com'); ?>
                    </p>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>

<?php do_action('woocommerce_after_edit_account_form'); ?>
