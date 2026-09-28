<?php

use PHPUnit\Framework\TestCase;

class DemoCacheContextTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_each_user_receives_filtered_status_despite_cache(): void
    {
        if (!defined('ABSPATH')) {
            define('ABSPATH', __DIR__);
        }

        if (!defined('CA_DEMO_ORGANISATEUR_LOGINS')) {
            define('CA_DEMO_ORGANISATEUR_LOGINS', []);
        }

        global $test_filters;
        $test_filters = [];

        if (!function_exists('add_filter')) {
            function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): void
            {
                global $test_filters;

                if (!isset($test_filters[$hook])) {
                    $test_filters[$hook] = [];
                }

                if (!isset($test_filters[$hook][$priority])) {
                    $test_filters[$hook][$priority] = [];
                }

                $test_filters[$hook][$priority][] = [
                    'callback'      => $callback,
                    'accepted_args' => $accepted_args,
                ];
            }
        }

        if (!function_exists('apply_filters')) {
            function apply_filters($hook, $value)
            {
                global $test_filters;

                $args = func_get_args();

                if (empty($test_filters[$hook])) {
                    return $value;
                }

                ksort($test_filters[$hook]);

                foreach ($test_filters[$hook] as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        $accepted_args = (int) $callback['accepted_args'];
                        $callback_args = array_slice($args, 1, $accepted_args);
                        $value         = call_user_func_array($callback['callback'], $callback_args);
                        $args[1]       = $value;
                    }
                }

                return $value;
            }
        }

        if (!function_exists('get_current_user_id')) {
            function get_current_user_id(): int
            {
                return (int) ($GLOBALS['current_user_id'] ?? 0);
            }
        }

        if (!function_exists('get_organisateur_from_chasse')) {
            function get_organisateur_from_chasse(int $chasse_id): int
            {
                return 777;
            }
        }

        require_once __DIR__ . '/../inc/chasse/demo.php';

        $user_statuses = [
            101 => true,
            202 => false,
        ];

        $filter_calls = [];

        add_filter(
            'ca_demo_is_demo_hunt',
            function ($default, $chasse_id, $context) use (&$filter_calls, $user_statuses) {
                $user_id        = get_current_user_id();
                $filter_calls[] = $user_id;

                return $user_statuses[$user_id] ?? $default;
            },
            10,
            3
        );

        $GLOBALS['current_user_id'] = 101;
        $this->assertTrue(ca_demo_is_demo_hunt(555));
        $this->assertTrue(ca_demo_is_demo_hunt(555));

        $GLOBALS['current_user_id'] = 202;
        $this->assertFalse(ca_demo_is_demo_hunt(555));
        $this->assertFalse(ca_demo_is_demo_hunt(555));

        $this->assertSame([101, 202], $filter_calls);
    }
}
