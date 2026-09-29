<?php

declare(strict_types=1);

/**
 * Transitional compatibility loader.
 *
 * The user message repository now belongs to the Chasses au Tresor Core plugin.
 * This loader preserves the legacy class name while callers are migrated.
 */

if (!class_exists('UserMessageRepository', false)) {
    $corePluginFile = dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Messages/UserMessageRepository.php';

    require_once $corePluginFile;

    class_alias(ChassesAuTresor\Core\Messages\UserMessageRepository::class, 'UserMessageRepository');
}
