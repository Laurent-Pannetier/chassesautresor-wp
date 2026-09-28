<?php
defined( 'ABSPATH' ) || exit;

if (!defined('ROLE_ORGANISATEUR')) {
    define('ROLE_ORGANISATEUR', 'organisateur');
}
if (!defined('ROLE_ORGANISATEUR_CREATION')) {
    define('ROLE_ORGANISATEUR_CREATION', 'organisateur_creation');
}

if (!defined('CA_DEMO_ORGANISATEUR_LOGINS')) {
    /**
     * Liste des logins organisateur considérés comme étant en mode démo.
     *
     * Utilisez le filtre `ca_demo_organisateur_logins` pour enrichir cette liste dynamiquement.
     */
    define('CA_DEMO_ORGANISATEUR_LOGINS', [
        'organisateur1',
    ]);
}

// --------------------------------------------------
// 🔢 Solution states
// --------------------------------------------------
const SOLUTION_STATE_INVALIDE          = 'INVALIDE';
const SOLUTION_STATE_FIN_CHASSE        = 'FIN_CHASSE';
const SOLUTION_STATE_FIN_CHASSE_DIFFERE = 'FIN_CHASSE_DIFFERE';
const SOLUTION_STATE_A_VENIR           = 'A_VENIR';
const SOLUTION_STATE_EN_COURS          = 'EN_COURS';
const SOLUTION_STATE_DESACTIVE         = 'DESACTIVE';

// --------------------------------------------------
// 🔧 Debug / Logging
// --------------------------------------------------
// Change CAT_DEBUG_VERBOSE to true to enable verbose logging.
if (!defined('CAT_DEBUG_VERBOSE')) {
    define('CAT_DEBUG_VERBOSE', false);
}
