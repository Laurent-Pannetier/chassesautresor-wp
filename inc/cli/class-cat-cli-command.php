<?php

declare(strict_types=1);

/**
 * Transitional compatibility loader for the core WP-CLI command.
 */

if (!class_exists('Cat_CLI_Command', false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Cli/CatCliCommand.php';

    class_alias(ChassesAuTresor\Core\Cli\CatCliCommand::class, 'Cat_CLI_Command');

    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::add_command('cat', ChassesAuTresor\Core\Cli\CatCliCommand::class);
    }
}
