<?php
defined('ABSPATH') || exit;

$post_id = $args['post_id'] ?? null;
if (!$post_id) return;

echo '<div class="style-pirate-test">';
echo '<p>🏴‍☠️ Ceci est <strong>images.php</strong> depuis le thème <code>pirate</code>.</p>';
echo '</div>';
