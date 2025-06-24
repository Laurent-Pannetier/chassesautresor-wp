<?php
use WP_Mock\Tools\TestCase;

require_once __DIR__ . '/bootstrap.php';

class SolutionVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        global $mock_posts, $mock_fields, $mock_current_time;
        $mock_posts = [];
        $mock_fields = [];
        $mock_current_time = null;
    }

    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    public function test_solution_hidden_if_chasse_not_terminee()
    {
        global $mock_posts, $mock_fields;
        $mock_posts[10] = ['post_type' => 'chasse'];
        $mock_posts[20] = ['post_type' => 'enigme'];
        $mock_fields[20]['enigme_chasse_associee'] = 10;
        $mock_fields[10]['statut_chasse'] = 'En cours';

        $this->assertFalse(solution_peut_etre_affichee(20));
    }

    public function test_solution_hidden_until_delay_elapsed()
    {
        global $mock_posts, $mock_fields, $mock_current_time;
        $mock_posts[10] = ['post_type' => 'chasse'];
        $mock_posts[20] = ['post_type' => 'enigme'];
        $mock_fields[20]['enigme_chasse_associee'] = 10;
        $mock_fields[10]['statut_chasse'] = 'Terminée';
        $mock_fields[20]['enigme_solution_mode'] = 'delai_fin_chasse';
        $mock_fields[20]['enigme_solution_delai'] = 1;
        $mock_fields[20]['enigme_solution_heure'] = '00:00';
        $mock_fields[10]['date_de_decouverte'] = '2020-01-01';
        $mock_current_time = strtotime('2020-01-01 12:00:00');

        $this->assertFalse(solution_peut_etre_affichee(20));
    }

    public function test_solution_visible_after_delay()
    {
        global $mock_posts, $mock_fields, $mock_current_time;
        $mock_posts[10] = ['post_type' => 'chasse'];
        $mock_posts[20] = ['post_type' => 'enigme'];
        $mock_fields[20]['enigme_chasse_associee'] = 10;
        $mock_fields[10]['statut_chasse'] = 'Terminée';
        $mock_fields[20]['enigme_solution_mode'] = 'delai_fin_chasse';
        $mock_fields[20]['enigme_solution_delai'] = 1;
        $mock_fields[20]['enigme_solution_heure'] = '00:00';
        $mock_fields[10]['date_de_decouverte'] = '2020-01-01';
        $mock_current_time = strtotime('2020-01-03 12:00:00');

        $this->assertTrue(solution_peut_etre_affichee(20));
    }
}
