<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/onetutor:readcoursecontent' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [],
    ],
];
