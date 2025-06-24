<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class RoleSwitchTest extends TestCase
{
    protected function setUp(): void
    {
        global $mock_users, $current_user_id;
        $mock_users = [];
        $current_user_id = 0;
    }

    public function test_ajouter_role_organisateur_creation_adds_role()
    {
        global $mock_users, $current_user_id;

        $current_user_id = 5;
        $mock_users[5] = (object) ['ID' => 5, 'roles' => ['subscriber']];

        $post = (object) ['post_type' => 'organisateur', 'post_status' => 'publish'];

        ajouter_role_organisateur_creation(1, $post, false);

        $this->assertContains(ROLE_ORGANISATEUR_CREATION, $mock_users[5]->roles);
    }

    public function test_ajouter_role_organisateur_creation_skips_autodraft()
    {
        global $mock_users, $current_user_id;

        $current_user_id = 6;
        $mock_users[6] = (object) ['ID' => 6, 'roles' => ['subscriber']];

        $post = (object) ['post_type' => 'organisateur', 'post_status' => 'auto-draft'];

        ajouter_role_organisateur_creation(1, $post, false);

        $this->assertNotContains(ROLE_ORGANISATEUR_CREATION, $mock_users[6]->roles);
    }
}
