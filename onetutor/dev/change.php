<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

[$options] = cli_get_params(['action' => '', 'cmid' => 0, 'visible' => 1]);
\core\session\manager::set_user(get_admin());

switch ($options['action']) {
    case 'visibility':
        $cm = get_coursemodule_from_id('', $options['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        \core_courseformat\formatactions::cm($course)->set_visibility($cm->id, (int) $options['visible']);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        break;
    case 'delete':
        course_delete_module($options['cmid']);
        break;
}
cli_writeln('done');
