<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class AccessFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        global $mock_users, $current_user_id;
        $mock_users = [];
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
}
