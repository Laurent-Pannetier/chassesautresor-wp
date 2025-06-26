<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value) {
        return $value;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text) {
        return strip_tags($text);
    }
}

// Stub ACF helpers for this test
if (!function_exists('get_field_object')) {
    function get_field_object($key, $post_id = 0) {
        if ($key === 'test_group') {
            return [
                'name' => 'test_group',
                'sub_fields' => [
                    [
                        'name' => 'test_field',
                        'key'  => 'field_test_field',
                        'type' => 'text',
                    ],
                ],
            ];
        }
        return null;
    }
}

if (!function_exists('update_field')) {
    function update_field($selector, $value, $post_id = false) {
        global $mock_fields, $last_update_args;
        $last_update_args = [$selector, $value, $post_id];
        $mock_fields[$post_id][$selector] = $value;
        return true;
    }
}

if (!function_exists('clean_post_cache')) {
    function clean_post_cache($post_id) {}
}

require_once __DIR__ . '/../inc/utils.php';
require_once __DIR__ . '/../inc/edition/edition-core.php';

class UpdateGroupFieldTest extends TestCase
{
    protected function setUp(): void
    {
        global $mock_fields, $last_update_args;
        $mock_fields = [];
        $last_update_args = null;
    }

    public function test_mettre_a_jour_sous_champ_group_returns_true()
    {
        global $mock_fields, $last_update_args;

        $postId = 1;
        $mock_fields[$postId]['test_group'] = ['test_field' => 'old'];

        $result = mettre_a_jour_sous_champ_group($postId, 'test_group', 'test_field', 'new');

        $this->assertTrue($result);
        $this->assertSame('new', $mock_fields[$postId]['test_group']['test_field']);
        $this->assertSame('test_group', $last_update_args[0]);
        $this->assertArrayHasKey('test_field', $last_update_args[1]);
    }
}
