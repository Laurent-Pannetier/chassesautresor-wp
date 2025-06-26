<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class AccessFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        global $mock_users, $mock_posts, $mock_fields, $current_user_id;
        $mock_users = [];
        $mock_posts = [];
        $mock_fields = [];
        $current_user_id = 0;
    }

    public function test_est_organisateur_role_principal()
    {
        global $mock_users;
        $mock_users[1] = (object) ['ID' => 1, 'roles' => [ROLE_ORGANISATEUR]];
        $this->assertTrue(est_organisateur(1));
    }

    public function test_est_organisateur_role_creation()
    {
        global $mock_users;
        $mock_users[2] = (object) ['ID' => 2, 'roles' => [ROLE_ORGANISATEUR_CREATION]];
        $this->assertTrue(est_organisateur(2));
    }

    public function test_est_organisateur_false_for_other_role()
    {
        global $mock_users;
        $mock_users[3] = (object) ['ID' => 3, 'roles' => ['subscriber']];
        $this->assertFalse(est_organisateur(3));
    }

    public function test_set_role_replaces_creation_role()
    {
        global $mock_users;
        $mock_users[4] = (object) ['ID' => 4, 'roles' => ['subscriber', ROLE_ORGANISATEUR_CREATION]];

        $user = new WP_User(4);
        $user->set_role(ROLE_ORGANISATEUR);
        $user->remove_role(ROLE_ORGANISATEUR_CREATION);

        $this->assertSame([ROLE_ORGANISATEUR], $mock_users[4]->roles);
    }

    public function test_utilisateur_peut_modifier_post_association()
    {
        global $mock_users, $mock_posts, $mock_fields, $current_user_id;

        $current_user_id = 1;
        $mock_users[1] = (object) ['ID' => 1, 'roles' => ['subscriber']];

        $mock_posts[100] = ['post_type' => 'organisateur', 'post_author' => 2];
        $mock_fields[100]['utilisateurs_associes'] = [1];

        $this->assertTrue(utilisateur_peut_modifier_post(100));
    }

    public function test_utilisateur_peut_modifier_post_author()
    {
        global $mock_users, $mock_posts, $mock_fields, $current_user_id;

        $current_user_id = 2;
        $mock_users[2] = (object) ['ID' => 2, 'roles' => ['subscriber']];

        $mock_posts[101] = ['post_type' => 'organisateur', 'post_author' => 2];
        $mock_fields[101]['utilisateurs_associes'] = [1];

        $this->assertTrue(utilisateur_peut_modifier_post(101));
    }

    public function test_utilisateur_peut_modifier_post_chasse_via_organisateur()
    {
        global $mock_users, $mock_posts, $mock_fields, $current_user_id;

        $current_user_id = 3;
        $mock_users[3] = (object) ['ID' => 3, 'roles' => ['subscriber']];

        $mock_posts[200] = ['post_type' => 'organisateur', 'post_author' => 3];
        $mock_fields[200]['utilisateurs_associes'] = [3];

        $mock_posts[201] = ['post_type' => 'chasse'];
        $mock_fields[201]['champs_caches'] = ['chasse_cache_organisateur' => 200];

        $this->assertTrue(utilisateur_peut_modifier_post(201));
    }

    public function test_utilisateur_peut_modifier_post_enigme_via_chasse()
    {
        global $mock_users, $mock_posts, $mock_fields, $current_user_id;

        $current_user_id = 4;
        $mock_users[4] = (object) ['ID' => 4, 'roles' => ['subscriber']];

        $mock_posts[300] = ['post_type' => 'organisateur', 'post_author' => 4];
        $mock_fields[300]['utilisateurs_associes'] = [4];

        $mock_posts[301] = ['post_type' => 'chasse'];
        $mock_fields[301]['champs_caches'] = ['chasse_cache_organisateur' => 300];

        $mock_posts[302] = ['post_type' => 'enigme'];
        $mock_fields[302]['enigme_chasse_associee'] = 301;

        $this->assertTrue(utilisateur_peut_modifier_post(302));
    }
}
