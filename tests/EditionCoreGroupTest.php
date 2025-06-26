<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class EditionCoreGroupTest extends TestCase
{
    protected function setUp(): void
    {
        global $mock_fields, $mock_field_objects;
        $mock_fields = [];
        $mock_field_objects = [];
    }

    public function test_update_datetime_picker_by_name()
    {
        global $mock_fields, $mock_field_objects;
        $post_id = 1;

        $group_object = [
            'name' => 'caracteristiques',
            'sub_fields' => [
                [
                    'name' => 'chasse_infos_date_debut',
                    'key'  => 'field_date_debut',
                    'type' => 'date_time_picker',
                ],
            ],
        ];
        $mock_field_objects['group_carac'] = $group_object;
        $mock_field_objects['caracteristiques'] = $group_object;

        $result = mettre_a_jour_sous_champ_group(
            $post_id,
            'caracteristiques',
            'chasse_infos_date_debut',
            '2025-06-01'
        );

        $this->assertTrue($result);
        $this->assertSame('2025-06-01 00:00:00', $mock_fields[$post_id]['chasse_infos_date_debut']);
    }

    public function test_grouped_updates_store_values()
    {
        global $mock_fields, $mock_field_objects;
        $post_id = 2;

        $group_object = [
            'name' => 'infos',
            'sub_fields' => [
                [
                    'name' => 'infos_titre',
                    'key'  => 'field_titre',
                    'type' => 'text',
                ],
                [
                    'name' => 'infos_date',
                    'key'  => 'field_date',
                    'type' => 'date_time_picker',
                ],
            ],
        ];
        $mock_field_objects['group_infos'] = $group_object;
        $mock_field_objects['infos'] = $group_object;

        $values = [
            'infos_titre' => 'Test',
            'infos_date'  => '2025-05-01 10:30:00',
        ];

        $result = mettre_a_jour_sous_champ_group($post_id, 'infos', '', $values);

        $this->assertTrue($result);
        $this->assertSame('Test', $mock_fields[$post_id]['infos_titre']);
        $this->assertSame('2025-05-01 10:30:00', $mock_fields[$post_id]['infos_date']);
    }
}
