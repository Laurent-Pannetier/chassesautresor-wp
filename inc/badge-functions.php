<?php

// 🚀 Empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('chasse_preparer_badge_statut')) {
    /**
     * Prépare les informations d'affichage du badge de statut d'une chasse.
     *
     * @param string      $statut            Statut métier principal (revision, en_cours, ...).
     * @param string|null $statut_validation Statut de validation complémentaire.
     *
     * @return array{
     *     statut: string,
     *     label: string,
     *     base_class: string,
     *     icon_name: string|null,
     *     icon_html: string
     * }
     */
    function chasse_preparer_badge_statut(string $statut, ?string $statut_validation): array
    {
        $statut = $statut !== '' ? $statut : 'revision';
        $statut_for_class = $statut === 'payante' ? 'en_cours' : $statut;

        $label = '';
        $icon_name = null;
        $translate = static function (string $string): string {
            return function_exists('__') ? __($string, 'chassesautresor-com') : $string;
        };

        if ($statut === 'revision') {
            if ($statut_validation === 'creation') {
                $label = $translate('création');
                $icon_name = 'add';
            } elseif ($statut_validation === 'correction' || $statut_validation === 'edition') {
                $label = $translate('correction');
                $icon_name = 'edition';
            } elseif ($statut_validation === 'en_attente') {
                $label = $translate('en attente');
                $icon_name = 'pending';
            } else {
                $label = $translate('révision');
                $icon_name = 'edition';
            }
        } elseif ($statut_for_class === 'en_cours') {
            $label = $translate('en cours');
            $icon_name = 'in_progress';
        } elseif ($statut === 'a_venir') {
            $label = $translate('à venir');
            $icon_name = 'hourglass';
        } elseif ($statut === 'termine') {
            $label = $translate('terminée');
            $icon_name = 'finish';
        } elseif ($statut === 'en_attente') {
            $label = $translate('en attente');
            $icon_name = 'pending';
        } else {
            $label = function_exists('__') ? __($statut, 'chassesautresor-com') : $statut;
        }

        $icon_html = ($icon_name && function_exists('get_svg_icon')) ? get_svg_icon($icon_name) : '';

        return [
            'statut'     => $statut_for_class,
            'label'      => $label,
            'base_class' => 'statut-' . $statut_for_class,
            'icon_name'  => $icon_name,
            'icon_html'  => $icon_html,
        ];
    }
}
