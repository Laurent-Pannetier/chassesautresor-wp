<?php

use PHPUnit\Framework\TestCase;

if (!function_exists('current_user_can')) {
    function current_user_can($cap)
    {
        return true;
    }
}

if (!function_exists('est_organisateur')) {
    function est_organisateur()
    {
        return true;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in()
    {
        return true;
    }
}

if (!function_exists('is_singular')) {
    function is_singular($types = [])
    {
        $post_id = $GLOBALS['test_current_post_id'] ?? 0;
        $post_type = $GLOBALS['test_post_types'][$post_id] ?? 'post';
        if (empty($types)) {
            return true;
        }
        if (is_array($types)) {
            return in_array($post_type, $types, true);
        }
        return $post_type === $types;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id()
    {
        return 1;
    }
}

if (!function_exists('get_organisateur_from_user')) {
    function get_organisateur_from_user($user_id)
    {
        return 99;
    }
}

if (!function_exists('recuperer_enigmes_tentatives_en_attente')) {
    function recuperer_enigmes_tentatives_en_attente($organisateur_id)
    {
        return $GLOBALS['test_enigmes_pending'] ?? [];
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key, $single)
    {
        global $wpdb;
        $repo = new UserMessageRepository($wpdb);

        if ($key === '_myaccount_flash_messages') {
            $rows = $repo->get($user_id, 'flash', false);
            $messages = [];
            foreach ($rows as $row) {
                $data = json_decode($row['message'], true);
                if (is_array($data)) {
                    $messages[] = $data;
                }
            }
            return $messages;
        }

        if ($key === '_myaccount_messages') {
            $rows = $repo->get($user_id, 'persistent', false);
            $messages = [];
            foreach ($rows as $row) {
                $data = json_decode($row['message'], true);
                if (is_array($data) && isset($data['key'])) {
                    $messages[$data['key']] = $data;
                }
            }
            return $messages;
        }

        return [
            [
                'statut' => 'en attente',
            ],
        ];
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $key, $value)
    {
        global $wpdb;
        $repo = new UserMessageRepository($wpdb);

        if ($key === '_myaccount_flash_messages') {
            delete_user_meta($user_id, $key);
            foreach ((array) $value as $msg) {
                $repo->insert($user_id, wp_json_encode($msg), 'flash');
            }
        } elseif ($key === '_myaccount_messages') {
            delete_user_meta($user_id, $key);
            foreach ($value as $k => $msg) {
                $payload       = $msg;
                $payload['key'] = $k;
                $repo->insert($user_id, wp_json_encode($payload), 'persistent');
            }
        }

        return true;
    }
}

if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $key)
    {
        global $wpdb;
        $repo = new UserMessageRepository($wpdb);

        if ($key === '_myaccount_flash_messages') {
            $rows = $repo->get($user_id, 'flash', null);
        } elseif ($key === '_myaccount_messages') {
            $rows = $repo->get($user_id, 'persistent', null);
        } else {
            return true;
        }

        foreach ($rows as $row) {
            $repo->delete((int) $row['id']);
        }

        return true;
    }
}

if (!function_exists('get_organisateur_from_chasse')) {
    function get_organisateur_from_chasse($chasse_id)
    {
        return 99;
    }
}

if (!function_exists('get_field')) {
    function get_field($field, $post_id)
    {
        if ($field === 'utilisateurs_associes' && $post_id === 99) {
            return [
                (object) ['ID' => 2],
            ];
        }

        return null;
    }
}

if (!function_exists('get_post_field')) {
    function get_post_field($field, $post_id)
    {
        if ($field === 'post_author' && $post_id === 99) {
            return 10;
        }

        return 0;
    }
}

if (!function_exists('get_stylesheet_directory')) {
    function get_stylesheet_directory()
    {
        return __DIR__;
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data)
    {
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post_id)
    {
        if ($post_id === 99) {
            return 'https://example.com/organisateur';
        }

        if ($post_id === 101) {
            return 'https://example.com/chasse-101';
        }

        return 'https://example.com/post-' . $post_id;
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($post_id)
    {
        if ($post_id === 101) {
            return 'Chasse Example';
        }

        return 'Post ' . $post_id;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return $text;
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '')
    {
        $query = http_build_query($args);
        $sep = strpos($url, '?') === false ? '?' : '&';
        return $url . $sep . $query;
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url)
    {
        return $url;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '')
    {
        return 'https://example.com' . $path;
    }
}

if (!function_exists('get_queried_object_id')) {
    function get_queried_object_id()
    {
        return $GLOBALS['test_current_post_id'] ?? 0;
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post = null)
    {
        $post_id = $post ?? ($GLOBALS['test_current_post_id'] ?? 0);
        return $GLOBALS['test_post_types'][$post_id] ?? 'post';
    }
}

if (!function_exists('recuperer_id_chasse_associee')) {
    function recuperer_id_chasse_associee($post_id = null)
    {
        $post_id = $post_id ?? 0;
        return $GLOBALS['test_enigme_to_chasse'][$post_id] ?? 0;
    }
}

if (!function_exists('peut_valider_chasse')) {
    function peut_valider_chasse($chasse_id, $user_id)
    {
        return true;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default')
    {
        return $text;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}

if (!class_exists('PointsRepository')) {
    class PointsRepository
    {
        public function __construct($wpdb)
        {
        }

        public function getConversionRequests($userId, $status)
        {
            return [['id' => 1]];
        }
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = [])
    {
        return [101];
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }
}

global $wpdb;
$wpdb = new class {
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $data = [];

    public function insert($table, array $data, array $format): void
    {
        $this->insert_id++;
        $data['id'] = $this->insert_id;
        $this->data[$this->insert_id] = $data;
    }

    public function update($table, array $data, array $where, array $format, array $whereFormat): void
    {
        $id = $where['id'];
        $this->data[$id] = array_merge($this->data[$id], $data);
    }

    public function delete($table, array $where, array $whereFormat): void
    {
        unset($this->data[$where['id']]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_results($sql, $output): array
    {
        return array_values($this->data);
    }

    public function query($sql): void
    {
        // no-op
    }
};

require_once __DIR__ . '/../inc/messages/class-user-message-repository.php';
require_once __DIR__ . '/../inc/user-functions.php';

class MyAccountMessagesTest extends TestCase
{
    public function test_conversion_request_message_contains_link(): void
    {
        $output = myaccount_get_important_messages();

        $this->assertStringContainsString('<a', $output);
        $this->assertStringContainsString(
            'https://example.com/organisateur?edition=open&onglet=revenus',
            $output
        );
        $this->assertStringContainsString('demande de conversion', $output);
    }

    public function test_admin_conversion_request_message_has_no_points_link(): void
    {
        $output = myaccount_get_important_messages();
        $this->assertStringContainsString(
            'Des demandes de conversion sont en attente de traitement.',
            $output
        );
        $this->assertStringNotContainsString(
            'https://example.com/mon-compte/?section=points',
            $output
        );
    }

    public function test_pending_validation_message_is_displayed(): void
    {
        $output = myaccount_get_important_messages();

        $this->assertStringContainsString('Demande pour', $output);
        $this->assertStringContainsString('en cours de traitement', $output);
        $this->assertStringContainsString(
            '<a href="https://example.com/chasse-101">Chasse Example</a>',
            $output
        );
    }

    public function test_pending_request_message_contains_riddle_link(): void
    {
        update_user_meta(
            1,
            '_myaccount_messages',
            [
                'tentative_123' => [
                    'text' => '<a href="https://example.com/enigme">Énigme</a>',
                    'type' => 'info',
                ],
            ]
        );

        $output = myaccount_get_important_messages();

        $this->assertStringContainsString('Votre demande de résolution de l\'énigme', $output);
        $this->assertStringContainsString('<a href="https://example.com/enigme">Énigme</a>', $output);

        delete_user_meta(1, '_myaccount_messages');
    }

    public function test_multiple_pending_requests_are_grouped(): void
    {
        update_user_meta(
            1,
            '_myaccount_messages',
            [
                'tentative_1' => [
                    'text' => '<a href="https://example.com/e1">E1</a>',
                    'type' => 'info',
                ],
                'tentative_2' => [
                    'text' => '<a href="https://example.com/e2">E2</a>',
                    'type' => 'info',
                ],
            ]
        );

        $output = myaccount_get_important_messages();

        $this->assertStringContainsString('Vos demandes de résolution d\'énigmes sont en cours de traitement', $output);
        $this->assertStringContainsString('<a class="etiquette" href="https://example.com/e1">E1</a>', $output);
        $this->assertStringContainsString('<a class="etiquette" href="https://example.com/e2">E2</a>', $output);

        delete_user_meta(1, '_myaccount_messages');
    }

    public function test_pending_request_with_full_message_is_not_duplicated(): void
    {
        $stored = "Votre demande de résolution de l'énigme <a href=\"https://example.com/enigme\">Énigme</a> est en cours de traitement. Vous recevrez une notification dès que votre demande sera traitée.";
        update_user_meta(
            1,
            '_myaccount_messages',
            [
                'tentative_456' => [
                    'text' => $stored,
                    'type' => 'info',
                ],
            ]
        );

        $output = myaccount_get_important_messages();

        $this->assertSame(1, substr_count($output, "Votre demande de résolution de l'énigme"));
        $this->assertStringContainsString('<a href="https://example.com/enigme">Énigme</a>', $output);

        delete_user_meta(1, '_myaccount_messages');
    }

    public function test_no_pending_attempt_messages_for_organizer(): void
    {
        $GLOBALS['test_enigmes_pending'] = [321];

        $output = myaccount_get_important_messages();

        $this->assertStringNotContainsString('Votre demande de résolution', $output);
        $this->assertStringNotContainsString('Important ! Des tentatives attendent votre action', $output);

        unset($GLOBALS['test_enigmes_pending']);
    }

    public function test_flash_message_is_displayed_once(): void
    {
        update_user_meta(
            1,
            '_myaccount_flash_messages',
            [
                ['text' => 'Message unique', 'type' => 'info'],
            ]
        );

        $first = myaccount_get_important_messages();
        $this->assertStringContainsString('Message unique', $first);

        $second = myaccount_get_important_messages();
        $this->assertStringNotContainsString('Message unique', $second);
    }

    public function test_persistent_message_persists_until_removed(): void
    {
        update_user_meta(
            1,
            '_myaccount_messages',
            [
                'foo' => ['text' => 'Persiste', 'type' => 'info'],
            ]
        );

        $first = myaccount_get_important_messages();
        $this->assertStringContainsString('Persiste', $first);

        $second = myaccount_get_important_messages();
        $this->assertStringContainsString('Persiste', $second);

        myaccount_remove_persistent_message(1, 'foo');
        $third = myaccount_get_important_messages();
        $this->assertStringNotContainsString('Persiste', $third);
    }

    public function test_clear_correction_message_removes_for_all_users(): void
    {
        update_user_meta(
            1,
            '_myaccount_messages',
            [
                'correction_chasse_123' => ['text' => 'X', 'type' => 'info'],
            ]
        );
        update_user_meta(
            2,
            '_myaccount_messages',
            [
                'correction_chasse_123' => ['text' => 'X', 'type' => 'info'],
            ]
        );
        update_user_meta(
            10,
            '_myaccount_messages',
            [
                'correction_chasse_123' => ['text' => 'X', 'type' => 'info'],
            ]
        );

        myaccount_clear_correction_message(123);

        $this->assertSame([], get_user_meta(1, '_myaccount_messages', true));
        $this->assertSame([], get_user_meta(2, '_myaccount_messages', true));
        $this->assertSame([], get_user_meta(10, '_myaccount_messages', true));
    }

    public function test_scoped_message_only_on_related_pages(): void
    {
        update_user_meta(
            1,
            '_myaccount_messages',
            [
                'scoped' => [
                    'text'            => 'Info',
                    'type'            => 'info',
                    'chasse_scope'    => 42,
                    'include_enigmes' => true,
                ],
            ]
        );

        $GLOBALS['test_current_post_id'] = 42;
        $GLOBALS['test_post_types']      = [42 => 'chasse'];
        $output = myaccount_get_important_messages();
        $this->assertStringContainsString('Info', $output);

        $GLOBALS['test_current_post_id'] = 100;
        $GLOBALS['test_post_types']      = [100 => 'enigme'];
        $GLOBALS['test_enigme_to_chasse'] = [100 => 42];
        $output = myaccount_get_important_messages();
        $this->assertStringContainsString('Info', $output);

        $GLOBALS['test_current_post_id'] = 43;
        $GLOBALS['test_post_types']      = [43 => 'chasse'];
        $output = myaccount_get_important_messages();
        $this->assertStringNotContainsString('Info', $output);

        $GLOBALS['test_current_post_id'] = 101;
        $GLOBALS['test_post_types']      = [101 => 'enigme'];
        $GLOBALS['test_enigme_to_chasse'] = [101 => 43];
        $output = myaccount_get_important_messages();
        $this->assertStringNotContainsString('Info', $output);

        delete_user_meta(1, '_myaccount_messages');
    }

    public function test_maybe_add_validation_message_persists_message(): void
    {
        delete_user_meta(1, '_myaccount_messages');
        $GLOBALS['test_current_post_id'] = 42;
        $GLOBALS['test_post_types']      = [42 => 'chasse'];

        myaccount_maybe_add_validation_message();

        global $wpdb;
        $repo       = new UserMessageRepository($wpdb);
        $rows       = $repo->get(1, 'persistent', null);
        $hasMessage = false;
        foreach ($rows as $row) {
            $data = json_decode($row['message'], true);
            if (is_array($data) && ($data['key'] ?? '') === 'correction_info_chasse_42') {
                $hasMessage = true;
                break;
            }
        }

        $this->assertTrue($hasMessage);

        delete_user_meta(1, '_myaccount_messages');
    }

    public function test_messages_are_styled(): void
    {
        update_user_meta(
            1,
            '_myaccount_flash_messages',
            [
                ['text' => 'Stylé', 'type' => 'info'],
            ]
        );
        $output = myaccount_get_important_messages();
        $this->assertStringContainsString('<p class="message-info" role="status" aria-live="polite">Stylé</p>', $output);
    }

    public function test_dismissible_message_has_button(): void
    {
        update_user_meta(
            1,
            '_myaccount_messages',
            [
                'foo' => [
                    'text'        => 'Salut',
                    'type'        => 'info',
                    'dismissible' => true,
                ],
            ]
        );

        $output = myaccount_get_important_messages();
        $this->assertStringContainsString('class="message-close" data-key="foo"', $output);

        delete_user_meta(1, '_myaccount_messages');
    }

    public function test_message_key_is_translated(): void
    {
        global $wpdb;
        $repo = new UserMessageRepository($wpdb);
        $repo->insert(
            1,
            wp_json_encode([
                'key'         => 'bar',
                'text'        => 'Original',
                'message_key' => 'translated_key',
                'type'        => 'info',
            ]),
            'persistent'
        );

        $output = myaccount_get_important_messages();
        $this->assertStringContainsString('translated_key', $output);
        $this->assertStringNotContainsString('Original', $output);

        delete_user_meta(1, '_myaccount_messages');
    }

    public function test_ajax_section_returns_flash_message(): void
    {
        update_user_meta(
            1,
            '_myaccount_flash_messages',
            [
                ['text' => 'Via AJAX', 'type' => 'info'],
            ]
        );
        $_GET['section'] = 'organisateurs';

        ob_start();
        ca_load_admin_section();
        $json = ob_get_clean();
        $data = json_decode($json, true);

        $this->assertTrue($data['success']);
        $this->assertStringContainsString('Via AJAX', $data['data']['messages']);
        $this->assertSame([], get_user_meta(1, '_myaccount_flash_messages', true));

        unset($_GET['section']);
    }
}
