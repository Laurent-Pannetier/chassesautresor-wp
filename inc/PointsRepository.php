<?php

declare(strict_types=1);

/**
 * Transitional compatibility loader.
 *
 * The points repository now belongs to the Chasses au Tresor Core plugin. The
 * theme keeps this loader temporarily so existing calls and tests continue to
 * work while the remaining business code is migrated.
 */

if (!class_exists('PointsRepository', false)) {
    $corePluginFile = dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Points/PointsRepository.php';

    require_once $corePluginFile;

    class_alias(ChassesAuTresor\Core\Points\PointsRepository::class, 'PointsRepository');
}
