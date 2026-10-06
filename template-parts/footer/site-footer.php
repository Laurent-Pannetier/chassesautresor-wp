<?php
/**
 * Compact public site footer (legal bar only).
 *
 * @package chassesautresor
 *
 * @var array $args {
 *     @type array $footer Footer payload from cta_get_site_footer_data().
 * }
 */

defined('ABSPATH') || exit;

$footer = is_array($args['footer'] ?? null) ? $args['footer'] : [];
$mode = (string) ($footer['mode'] ?? 'single_hunt');
$legalLinks = is_array($footer['legal_links'] ?? null) ? $footer['legal_links'] : [];
$copyright = (string) ($footer['copyright'] ?? '');

$footerClasses = [
    'cat-site-footer',
    'cat-site-footer--compact',
    'cat-site-footer--' . sanitize_html_class($mode),
];
?>
<footer
    class="<?php echo esc_attr(implode(' ', $footerClasses)); ?>"
    data-experience-mode="<?php echo esc_attr($mode); ?>"
    role="contentinfo"
>
    <div class="cat-site-footer__bar">
        <div class="cat-site-footer__bar-inner conteneur">
            <p class="cat-site-footer__copyright"><?php echo esc_html($copyright); ?></p>
            <?php if (!empty($legalLinks)) : ?>
                <nav aria-label="<?php echo esc_attr__('Informations légales', 'chassesautresor-com'); ?>">
                    <ul class="cat-site-footer__legal">
                        <?php foreach ($legalLinks as $link) :
                            $label = (string) ($link['label'] ?? '');
                            $url = (string) ($link['url'] ?? '');
                            if ($label === '' || $url === '') {
                                continue;
                            }
                            ?>
                            <li>
                                <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</footer>
