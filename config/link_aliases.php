<?php
/**
 * Dynamic link alias configuration
 *
 * Centralizes alias => destination mapping for contextual links.
 *
 * @package FortaleceePSE
 * @subpackage Config
 */

return [
    'grupo_home' => [
        'type' => 'group',
        'path' => '',
        'label' => 'Acessar grupo',
    ],
    'grupo_mural' => [
        'type' => 'group',
        'path' => 'activity/',
        'label' => 'Acessar mural do grupo',
    ],
    'grupo_forum' => [
        'type' => 'group',
        'path' => 'forum/',
        'label' => 'Acessar forum do grupo',
    ],
    'grupo_courses' => [
        'type' => 'group',
        'path' => 'courses/',
        'label' => 'Acessar cursos do grupo',
    ],
];
