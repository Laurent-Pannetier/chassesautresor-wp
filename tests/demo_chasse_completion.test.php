<?php

use PHPUnit\Framework\TestCase;

class DemoChasseCompletionTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_demo_hunt_completion_triggers_victory_and_schedules_reset(): void
    {
        if (!defined('ABSPATH')) {
            define('ABSPATH', __DIR__);
        }

        if (!defined('CA_DEMO_ORGANISATEUR_LOGINS')) {
            define('CA_DEMO_ORGANISATEUR_LOGINS', []);
        }

        $GLOBALS['mock_calls'] = [
            'demo'         => [],
            'finish'       => [],
            'reset'        => [],
            'actions'      => [],
            'filters'      => [],
            'cache_chasse' => [],
            'cache_enigme' => [],
        ];

        global $mock_actions, $scheduled_events;
        $mock_actions    = [];
        $scheduled_events = [];

        if (!function_exists('add_action')) {
            function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void
            {
                global $mock_actions;
                $mock_actions[$hook][] = [
                    'callback'      => $callback,
                    'accepted_args' => $accepted_args,
                ];
            }
        }

        if (!function_exists('do_action')) {
            function do_action($hook, ...$args): void
            {
                global $mock_actions;
                $GLOBALS['mock_calls']['actions'][] = [$hook, $args];

                if (empty($mock_actions[$hook])) {
                    return;
                }

                foreach ($mock_actions[$hook] as $listener) {
                    $callback      = $listener['callback'];
                    $accepted_args = (int) $listener['accepted_args'];
                    $callback_args = $accepted_args > 0 ? array_slice($args, 0, $accepted_args) : [];
                    call_user_func_array($callback, $callback_args);
                }
            }
        }

        if (!function_exists('apply_filters')) {
            function apply_filters($hook, $value)
            {
                $args = func_get_args();
                $GLOBALS['mock_calls']['filters'][] = $args;

                return $value;
            }
        }

        if (!function_exists('cat_debug')) {
            function cat_debug($message): void
            {
                $GLOBALS['mock_calls']['logs'][] = $message;
            }
        }

        if (!function_exists('wp_schedule_single_event')) {
            function wp_schedule_single_event($timestamp, $hook, $args = [])
            {
                global $scheduled_events;
                $scheduled_events[] = [
                    'timestamp' => $timestamp,
                    'hook'      => $hook,
                    'args'      => $args,
                ];

                return true;
            }
        }

        if (!function_exists('wp_next_scheduled')) {
            function wp_next_scheduled($hook, $args = [])
            {
                global $scheduled_events;
                foreach ($scheduled_events as $event) {
                    if ($event['hook'] === $hook && $event['args'] === $args) {
                        return $event['timestamp'];
                    }
                }

                return false;
            }
        }

        if (!function_exists('wp_unschedule_event')) {
            function wp_unschedule_event($timestamp, $hook, $args = [])
            {
                global $scheduled_events;
                foreach ($scheduled_events as $index => $event) {
                    if ($event['hook'] === $hook && $event['args'] === $args) {
                        unset($scheduled_events[$index]);
                    }
                }

                $scheduled_events = array_values($scheduled_events);

                return true;
            }
        }

        if (!function_exists('chasse_clear_infos_affichage_cache')) {
            function chasse_clear_infos_affichage_cache($chasse_id): void
            {
                $GLOBALS['mock_calls']['cache_chasse'][] = (int) $chasse_id;
            }
        }

        if (!function_exists('enigme_clear_sidebar_cache')) {
            function enigme_clear_sidebar_cache($chasse_id, $user_id): void
            {
                $GLOBALS['mock_calls']['cache_enigme'][] = [(int) $chasse_id, (int) $user_id];
            }
        }

        if (!function_exists('recuperer_id_chasse_associee')) {
            function recuperer_id_chasse_associee($enigme_id)
            {
                return 777;
            }
        }

        if (!function_exists('recuperer_enigmes_associees')) {
            function recuperer_enigmes_associees($chasse_id)
            {
                return [11, 12];
            }
        }

        if (!function_exists('get_field')) {
            function get_field($field, $post_id)
            {
                if ($field === 'chasse_mode_fin') {
                    return 'automatique';
                }

                if ($field === 'enigme_mode_validation') {
                    return 'auto';
                }

                return null;
            }
        }

        if (!function_exists('ca_demo_is_demo_hunt')) {
            function ca_demo_is_demo_hunt($chasse_id)
            {
                $GLOBALS['mock_calls']['demo'][] = (int) $chasse_id;

                return true;
            }
        }

        if (!function_exists('gerer_chasse_terminee')) {
            function gerer_chasse_terminee($chasse_id): void
            {
                $GLOBALS['mock_calls']['finish'][] = (int) $chasse_id;
            }
        }

        if (!function_exists('ca_demo_reset_user_progress')) {
            function ca_demo_reset_user_progress($chasse_id, $user_id)
            {
                $GLOBALS['mock_calls']['reset'][] = [(int) $chasse_id, (int) $user_id];

                return true;
            }
        }

        if (!isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new class() {
                public $prefix = 'wp_';
                public $get_var_returns = [2];

                public function prepare($query, $args = null)
                {
                    return $query;
                }

                public function get_var($query)
                {
                    return array_shift($this->get_var_returns);
                }
            };
        }

        require_once __DIR__ . '/../inc/chasse/demo.php';
        require_once __DIR__ . '/../inc/gamify-functions.php';

        $this->assertNotEmpty($mock_actions['ca_demo_schedule_reset'] ?? []);
        $this->assertNotEmpty($mock_actions['ca_demo_run_reset_event'] ?? []);

        verifier_fin_de_chasse(123, 456);

        $this->assertSame([777], $GLOBALS['mock_calls']['finish']);
        $this->assertNotEmpty($GLOBALS['mock_calls']['demo']);
        $this->assertCount(1, $scheduled_events);
        $this->assertSame('ca_demo_run_reset_event', $scheduled_events[0]['hook']);
        $this->assertSame([777, 123], $scheduled_events[0]['args']);
        $this->assertSame([], $GLOBALS['mock_calls']['reset']);
        $this->assertContains(777, $GLOBALS['mock_calls']['cache_chasse']);
        $this->assertContains([777, 123], $GLOBALS['mock_calls']['cache_enigme']);

        do_action('ca_demo_run_reset_event', 777, 123);

        $this->assertSame([[777, 123]], $GLOBALS['mock_calls']['reset']);
        $this->assertSame([777], $GLOBALS['mock_calls']['finish']);
    }
}
