<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_onetutor\connection;
use local_onetutor\form\connect_form;
use local_onetutor\provisioner;

admin_externalpage_setup('local_onetutor_connect');

$pageurl = new moodle_url('/local/onetutor/connect.php');

if (optional_param('disconnect', 0, PARAM_BOOL) && confirm_sesskey()) {
    provisioner::disconnect();
    redirect($pageurl, get_string('disconnected', 'local_onetutor'));
}

$form = new connect_form($pageurl);
if ($data = $form->get_data()) {
    try {
        provisioner::connect($data->baseurl, $data->registrationcode);
        redirect($pageurl, get_string('connected', 'local_onetutor'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_onetutor'));

if (connection::is_connected()) {
    echo $OUTPUT->notification(get_string('connectedto', 'local_onetutor', (object) [
        'url' => s(connection::base_url()),
        'siteid' => s(connection::site_id()),
    ]), \core\output\notification::NOTIFY_SUCCESS);
    echo $OUTPUT->single_button(
        new moodle_url($pageurl, ['disconnect' => 1, 'sesskey' => sesskey()]),
        get_string('disconnect', 'local_onetutor')
    );
} else {
    $form->display();
}

echo $OUTPUT->footer();
