<?php
/**
 * Billing and shipping addresses on the profile page.
 *
 * Identical addresses collapse into one card.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

$customer_id = get_current_user_id();
$addresses = [
    'billing' => __('Adresse de facturation', 'chassesautresor-com'),
];

if (!wc_ship_to_billing_address_only() && wc_shipping_enabled()) {
    $addresses['shipping'] = __('Adresse de livraison', 'chassesautresor-com');
}

$addresses = apply_filters('woocommerce_my_account_get_addresses', $addresses, $customer_id);

$formatted = [];
foreach ($addresses as $name => $title) {
    $formatted[$name] = (string) wc_get_account_formatted_address($name);
}

$merged = isset($addresses['billing'], $addresses['shipping'])
    && function_exists('myaccount_addresses_match')
    && myaccount_addresses_match($formatted['billing'], $formatted['shipping']);

if ($merged) {
    $addresses = [
        'billing' => __('Adresse', 'chassesautresor-com'),
    ];
}

$render_address = static function (
    string $title,
    string $type,
    string $address_html,
    string $note_html = ''
): void {
    $edit_url = wc_get_endpoint_url('edit-address', $type);
    $has_address = trim(wp_strip_all_tags($address_html)) !== '';
    $action_label = $has_address
        ? __('Modifier', 'chassesautresor-com')
        : __('Ajouter', 'chassesautresor-com');
    ?>
    <section class="account-panel account-address">
        <header class="account-panel__header">
            <h2 class="account-panel__title"><?php echo esc_html($title); ?></h2>
            <a class="account-button account-button--ghost" href="<?php echo esc_url($edit_url); ?>">
                <?php echo esc_html($action_label); ?>
            </a>
        </header>
        <?php if ($has_address) : ?>
            <address class="account-address__lines">
                <?php echo wp_kses_post($address_html); ?>
            </address>
        <?php else : ?>
            <p class="account-orders__empty">
                <?php esc_html_e('Aucune adresse enregistrée.', 'chassesautresor-com'); ?>
            </p>
        <?php endif; ?>
        <?php if ($note_html !== '') : ?>
            <p class="account-field__hint">
                <?php
                // Built above from escaped URL and translated label.
                echo $note_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </p>
        <?php endif; ?>
        <?php
        do_action('woocommerce_my_account_after_my_address', $type);
        ?>
    </section>
    <?php
};
?>

<div class="account-addresses">
    <?php
    foreach ($addresses as $name => $title) {
        $note_html = '';
        if ($merged && $name === 'billing') {
            $shipping_url = wc_get_endpoint_url('edit-address', 'shipping');
            $shipping_link = sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url($shipping_url),
                esc_html__('Utiliser une adresse de livraison différente', 'chassesautresor-com')
            );
            $plain = function_exists('myaccount_plain_address')
                ? myaccount_plain_address($formatted['billing'])
                : trim(wp_strip_all_tags($formatted['billing']));
            if ($plain !== '') {
                $note_html = esc_html__('Facturation et livraison identiques.', 'chassesautresor-com')
                    . ' '
                    . $shipping_link;
            } else {
                $note_html = $shipping_link;
            }
        }

        $render_address($title, (string) $name, $formatted[$name] ?? '', $note_html);
    }
    ?>
</div>
