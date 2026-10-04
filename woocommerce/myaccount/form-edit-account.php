<?php
/**
 * Edit account form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-edit-account.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hook - woocommerce_before_edit_account_form.
 *
 * @since 2.6.0
 */
do_action( 'woocommerce_before_edit_account_form' );
?>


<form class="woocommerce-EditAccountForm edit-account" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?> >

    <?php do_action( 'woocommerce_edit_account_form_start' ); ?>

    <p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
        <label for="account_first_name"><?php esc_html_e( 'First name', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr( $user->first_name ); ?>" aria-required="true" />
    </p>
    <p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
        <label for="account_last_name"><?php esc_html_e( 'Last name', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr( $user->last_name ); ?>" aria-required="true" />
    </p>
    <div class="clear"></div>

    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="account_display_name"><?php esc_html_e( 'Display name', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name" aria-describedby="account_display_name_description" value="<?php echo esc_attr( $user->display_name ); ?>" aria-required="true" /> <span id="account_display_name_description"><em><?php esc_html_e( 'This will be how your name will be displayed in the account section and in reviews', 'woocommerce' ); ?></em></span>
    </p>
    <div class="clear"></div>

    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="account_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
        <input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" aria-required="true" />
    </p>

    <?php
        /**
         * Hook where additional fields should be rendered.
         *
         * @since 8.7.0
         */
        do_action( 'woocommerce_edit_account_form_fields' );
    ?>

    <fieldset>
        <legend><?php esc_html_e( 'Password change', 'woocommerce' ); ?></legend>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="password_current"><?php esc_html_e( 'Current password (leave blank to leave unchanged)', 'woocommerce' ); ?></label>
            <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="off" />
        </p>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="password_1"><?php esc_html_e( 'New password (leave blank to leave unchanged)', 'woocommerce' ); ?></label>
            <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="off" />
        </p>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="password_2"><?php esc_html_e( 'Confirm new password', 'woocommerce' ); ?></label>
            <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="off" />
        </p>
    </fieldset>
    <div class="clear"></div>

    <?php
        /**
         * My Account edit account form.
         *
         * @since 2.6.0
         */
        do_action( 'woocommerce_edit_account_form' );
    ?>

    <p>
        <?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
        <button type="submit" class="woocommerce-Button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="save_account_details" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>"><?php esc_html_e( 'Save changes', 'woocommerce' ); ?></button>
        <input type="hidden" name="action" value="save_account_details" />
    </p>

    <?php do_action( 'woocommerce_edit_account_form_end' ); ?>
</form>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>

<div class="myaccount-addresses">
    <?php wc_get_template( 'myaccount/my-address.php' ); ?>
</div>

<?php
$orders_output = function_exists( 'afficher_commandes_utilisateur' )
    ? afficher_commandes_utilisateur( (int) $user->ID, 5 )
    : '';
$orders_url = function_exists( 'wc_get_account_endpoint_url' )
    ? wc_get_account_endpoint_url( 'orders' )
    : home_url( '/mon-compte/commandes/' );
?>
<section class="dashboard-section myaccount-settings-orders">
    <header class="dashboard-section-header">
        <h2 class="dashboard-section-title"><?php esc_html_e( 'Commandes', 'chassesautresor-com' ); ?></h2>
        <p class="dashboard-section-intro">
            <?php esc_html_e( 'Historique de vos commandes, accessible depuis le profil.', 'chassesautresor-com' ); ?>
        </p>
    </header>
    <div class="dashboard-section-body">
        <?php if ( $orders_output ) : ?>
            <div class="dashboard-card">
                <div class="dashboard-card-content">
                    <?php echo $orders_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
        <?php else : ?>
            <div class="dashboard-card dashboard-placeholder" aria-disabled="true">
                <div class="dashboard-card-content">
                    <p><?php esc_html_e( 'Aucune commande pour le moment.', 'chassesautresor-com' ); ?></p>
                </div>
            </div>
        <?php endif; ?>
        <p class="myaccount-settings-orders-link">
            <a class="bouton-secondaire" href="<?php echo esc_url( $orders_url ); ?>">
                <?php esc_html_e( 'Voir toutes les commandes', 'chassesautresor-com' ); ?>
            </a>
        </p>
    </div>
</section>
