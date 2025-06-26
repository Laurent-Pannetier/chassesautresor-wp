<?php
// Minimal bootstrap for testing access helper
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/..');
}

// Dummy WordPress hook functions
if (!function_exists('add_action')) {
    function add_action(...$args) {}
}
if (!function_exists('add_filter')) {
    function add_filter(...$args) {}
}
if (!function_exists('add_rewrite_rule')) {
    function add_rewrite_rule(...$args) {}
}

$mock_posts = [];
$mock_fields = [];
$mock_field_objects = [];
$mock_current_time = null;

if (!function_exists('update_field')) {
    function update_field($selector, $value, $post_id = false) {
        global $mock_fields, $mock_field_objects;

        $group = $mock_field_objects[$selector] ?? null;
        if (!$group) {
            foreach ($mock_field_objects as $obj) {
                if (($obj['name'] ?? null) === $selector && isset($obj['sub_fields'])) {
                    $group = $obj;
                    break;
                }
            }
        }

        if ($group && isset($group['sub_fields'])) {
            $converted = [];
            foreach ($group['sub_fields'] as $sub) {
                $name = $sub['name'];
                $key  = $sub['key'] ?? $name;
                if (array_key_exists($name, $value)) {
                    $converted[$name] = $value[$name];
                } elseif (array_key_exists($key, $value)) {
                    $converted[$name] = $value[$key];
                }
            }
            foreach ($converted as $name => $val) {
                $mock_fields[$post_id][$name] = $val;
            }
        } else {
            $mock_fields[$post_id][$selector] = $value;
        }

        return true;
    }
}
if (!function_exists('get_field_object')) {
    function get_field_object($selector, $post_id = false) {
        global $mock_field_objects;
        if (isset($mock_field_objects[$selector])) {
            return $mock_field_objects[$selector];
        }
        foreach ($mock_field_objects as $object) {
            if (($object['name'] ?? null) === $selector) {
                return $object;
            }
        }
        return null;
    }
}
if (!function_exists('clean_post_cache')) {
    function clean_post_cache($post_id) {}
}
if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string) { return strip_tags($string); }
}
if (!function_exists('cat_debug')) {
    function cat_debug($message, bool $force = false): void {}
}

// Minimal WP user functions for tests
$mock_users = [];
$current_user_id = 0;

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        global $current_user_id;
        return $current_user_id;
    }
}
if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        return get_current_user_id() > 0;
    }
}
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        $id = get_current_user_id();
        return get_userdata($id) ?: new WP_User($id);
    }
}
if (!function_exists('get_userdata')) {
    function get_userdata($id) {
        global $mock_users;
        return $mock_users[$id] ?? null;
    }
}
if (!function_exists('get_user_by')) {
    function get_user_by($field, $id) {
        return get_userdata($id);
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post_id) {
        global $mock_posts;
        return $mock_posts[$post_id]['post_type'] ?? null;
    }
}
if (!function_exists('get_post_field')) {
    function get_post_field($field, $post_id) {
        global $mock_posts;
        return $mock_posts[$post_id][$field] ?? null;
    }
}
if (!function_exists('get_post_status')) {
    function get_post_status($post_id) {
        global $mock_posts;
        return $mock_posts[$post_id]['post_status'] ?? 'publish';
    }
}

if (!function_exists('get_field')) {
    function get_field($key, $post_id = 0) {
        global $mock_fields, $mock_field_objects;

        // Attempt to resolve group name from key or object
        $group = $mock_field_objects[$key] ?? null;
        if (!$group) {
            foreach ($mock_field_objects as $obj) {
                if (($obj['name'] ?? null) === $key && isset($obj['sub_fields'])) {
                    $group = $obj;
                    break;
                }
            }
        }

        if ($group && isset($group['sub_fields'])) {
            $values = [];
            foreach ($group['sub_fields'] as $sub) {
                $name = $sub['name'];
                if (array_key_exists($name, $mock_fields[$post_id] ?? [])) {
                    $values[$name] = $mock_fields[$post_id][$name];
                }
            }
            return $values ?: null;
        }

        return $mock_fields[$post_id][$key] ?? null;
    }
}

if (!function_exists('recuperer_id_chasse_associee')) {
    function recuperer_id_chasse_associee($post_id) {
        $champ = get_field('enigme_chasse_associee', $post_id);
        if (is_array($champ)) {
            return reset($champ);
        }
        return $champ;
    }
}
if (!function_exists('get_organisateur_from_chasse')) {
    function get_organisateur_from_chasse($chasse_id) {
        $id = get_field('chasse_cache_organisateur', $chasse_id);
        if (is_array($id)) {
            $id = reset($id);
        } elseif ($id instanceof WP_Post) {
            $id = $id->ID;
        }
        return is_numeric($id) ? (int) $id : null;
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        global $mock_current_time;
        if ($type === 'timestamp') {
            return $mock_current_time ?? time();
        }
        return '';
    }
}

// Minimal WP_User class for tests
if (!class_exists('WP_User')) {
    class WP_User {
        public $ID;
        public $roles = [];

        public function __construct($id) {
            global $mock_users;
            $this->ID = $id;
            $user = $mock_users[$id] ?? null;
            if ($user) {
                $this->roles = is_object($user) ? (array) $user->roles : ($user['roles'] ?? []);
            }
        }

        protected function sync() {
            global $mock_users;
            $mock_users[$this->ID] = (object) ['ID' => $this->ID, 'roles' => $this->roles];
        }

        public function set_role($role) {
            $this->roles = [$role];
            $this->sync();
        }

        public function add_role($role) {
            if (!in_array($role, $this->roles, true)) {
                $this->roles[] = $role;
                $this->sync();
            }
        }

        public function remove_role($role) {
            $this->roles = array_values(array_diff($this->roles, [$role]));
            $this->sync();
        }
    }
}

require_once __DIR__ . '/../inc/constants.php';
require_once __DIR__ . '/../inc/access-functions.php';
require_once __DIR__ . '/../inc/chasse-functions.php';
require_once __DIR__ . '/../inc/user-functions.php';
require_once __DIR__ . '/../inc/edition/edition-core.php';
