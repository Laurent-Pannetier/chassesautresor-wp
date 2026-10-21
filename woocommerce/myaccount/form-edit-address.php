<?php
/**
 * Edit a billing or shipping address inside a shell card.
 *
 * @package chassesautresor
 * @see https://woocommerce.com/document/template-structure/
 * @version 9.3.0
 */

defined('ABSPATH') || exit;

$load_address = isset($load_address) ? (string) $load_address : '';
$address = isset($address) && is_array($address) ? $address : [];

do_action('woocommerce_before_edit_account_address_form');

if (!$load_address) {
    wc_get_template('myaccount/my-address.php');
} else {
    $page_title = $load_address === 'billing'
        ? __('Adresse de facturation', 'chassesautresor-com')
        : __('Adresse de livraison', 'chassesautresor-com');
    $page_title = (string) apply_filters(
        'woocommerce_my_account_edit_address_title',
        $page_title,
        $load_address
    );
    $profile_url = function_exists('wc_get_page_permalink')
        ? wc_get_page_permalink('myaccount')
        : home_url('/mon-compte/');
    ?>
    <div class="account-profile">
        <form class="account-panel account-address-form" method="post" novalidate>
            <header class="account-panel__header">
                <h2 class="account-panel__title"><?php echo esc_html($page_title); ?></h2>
                <a class="account-button account-button--ghost" href="<?php echo esc_url($profile_url); ?>">
                    <?php esc_html_e('Retour au profil', 'chassesautresor-com'); ?>
                </a>
            </header>
            <div class="woocommerce-address-fields">
                <?php do_action("woocommerce_before_edit_address_form_{$load_address}"); ?>
                <div class="woocommerce-address-fields__field-wrapper account-fields">
                    <?php
                    foreach ($address as $key => $field) {
                        woocommerce_form_field($key, $field, wc_get_post_data_by_key($key, $field['value'] ?? ''));
                    }
                    ?>
                </div>
                <?php do_action("woocommerce_after_edit_address_form_{$load_address}"); ?>
                <div class="account-panel__actions">
                    <button
                        type="submit"
                        class="account-button"
                        name="save_address"
                        value="<?php esc_attr_e('Enregistrer', 'chassesautresor-com'); ?>"
                    >
                        <?php esc_html_e('Enregistrer', 'chassesautresor-com'); ?>
                    </button>
                    <?php wp_nonce_field('woocommerce-edit_address', 'woocommerce-edit-address-nonce'); ?>
                    <input type="hidden" name="action" value="edit_address" />
                </div>
            </div>
        </form>
    </div>
    <?php
}

do_action('woocommerce_after_edit_account_address_form');
