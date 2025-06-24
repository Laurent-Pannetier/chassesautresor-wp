<?php
use WP_Mock\Tools\TestCase;
use function Chasses\Enigme\enigme_get_liste_prerequis_possibles;
use function Chasses\Enigme\utilisateur_peut_repondre_manuelle;

require_once __DIR__ . '/bootstrap.php';

class EnigmeNamespacedTest extends TestCase
{
    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    public function test_enigme_get_liste_prerequis_possibles_filters_list()
    {
        \WP_Mock::userFunction('get_field', [
            'times'  => 3,
            'return_in_order' => [10, 'code', 'aucune'],
        ]);
        \WP_Mock::userFunction('recuperer_enigmes_associees', [
            'times' => 1,
            'return' => [1, 2, 3],
        ]);
        \WP_Mock::userFunction('get_the_title', [
            'times' => 2,
            'return_in_order' => ['Premiere enigme', 'Deuxieme enigme'],
        ]);

        $result = enigme_get_liste_prerequis_possibles(2);
        $this->assertSame([1 => 'Premiere enigme'], $result);
    }

    public function test_utilisateur_peut_repondre_manuelle_true_when_authorized()
    {
        \WP_Mock::userFunction('enigme_get_statut_utilisateur', [
            'times' => 1,
            'args' => [5, 1],
            'return' => 'en_cours',
        ]);
        $this->assertTrue(utilisateur_peut_repondre_manuelle(1, 5));
    }

    public function test_utilisateur_peut_repondre_manuelle_false_when_not_authorized()
    {
        \WP_Mock::userFunction('enigme_get_statut_utilisateur', [
            'times' => 1,
            'args' => [5, 2],
            'return' => 'terminee',
        ]);
        $this->assertFalse(utilisateur_peut_repondre_manuelle(2, 5));
    }
}
