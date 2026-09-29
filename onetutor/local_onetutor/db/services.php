<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_onetutor_get_course_manifest' => [
        'classname' => \local_onetutor\external\get_course_manifest::class,
        'description' => 'Lists the course sections, modules and module files with their content hashes and visibility.',
        'type' => 'read',
        'capabilities' => 'local/onetutor:readcoursecontent',
    ],
];

$services = [
    'OneTutor sync' => [
        'functions' => ['local_onetutor_get_course_manifest'],
        'shortname' => 'local_onetutor',
        'restrictedusers' => 1,
        'enabled' => 1,
        'downloadfiles' => 1,
    ],
];
