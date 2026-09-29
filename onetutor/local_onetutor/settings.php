<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_onetutor_connect',
        get_string('pluginname', 'local_onetutor'),
        new moodle_url('/local/onetutor/connect.php')
    ));
}
