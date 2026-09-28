<?php
use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        // no-op
    }
}

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false)
    {
        return $GLOBALS['cat_options'][$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, $value)
    {
        $GLOBALS['cat_options'][$option] = $value;
        return true;
    }
}

global $wpdb;
$wpdb = new class {
    public string $prefix = 'wp_';
    /** @var array<int,array<string,mixed>> */
    public array $data = [
        1 => ['id' => 1, 'user_id' => 0, 'message' => '{"content":"prout"}', 'status' => 'site', 'expires_at' => null],
        2 => ['id' => 2, 'user_id' => 0, 'message' => '{"content":"ok"}', 'status' => 'site', 'expires_at' => null],
    ];
    public function prepare($sql, $params)
    {
        return $sql;
    }
    public function get_results($sql, $output)
    {
        return array_values($this->data);
    }
    public function delete($table, array $where, array $whereFormat)
    {
        unset($this->data[$where['id']]);
    }
};

require_once __DIR__ . '/../inc/messages/class-user-message-repository.php';
require_once __DIR__ . '/../inc/messages.php';

class RemoveLegacyTestMessagesTest extends TestCase
{
    public function test_cleanup_removes_prout_messages(): void
    {
        cat_remove_legacy_test_messages();

        global $wpdb;
        $this->assertArrayNotHasKey(1, $wpdb->data);
        $this->assertArrayHasKey(2, $wpdb->data);
        $this->assertSame(1, get_option('cat_removed_test_messages'));
    }
}
