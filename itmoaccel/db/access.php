<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/itmoaccel:student' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
    'local/itmoaccel:supervisor' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
    'local/itmoaccel:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
