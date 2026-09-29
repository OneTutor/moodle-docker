<?php
defined('MOODLE_INTERNAL') || die();

$observers = array_map(
    fn(string $eventname) => [
        'eventname' => $eventname,
        'callback' => '\local_onetutor\observer::course_changed',
        'internal' => false,
    ],
    [
        '\core\event\course_module_created',
        '\core\event\course_module_updated',
        '\core\event\course_module_deleted',
        '\core\event\course_section_created',
        '\core\event\course_section_updated',
        '\core\event\course_section_deleted',
        '\core\event\course_updated',
        '\core\event\course_deleted',
        '\core\event\course_content_deleted',
        '\core\event\course_restored',
        '\mod_folder\event\folder_updated',
    ]
);
