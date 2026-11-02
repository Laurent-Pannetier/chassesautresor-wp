<?php
/**
 * Organizer totals live in chassesautresor-core.
 *
 * Templates keep calling organisateur_compter_joueurs_uniques() and
 * organisateur_compter_points_collectes(), now defined by the plugin.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/../chasse/stats.php';
