<?php
namespace local_onetutor\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Collects the OneTutor URL and registration code.
 *
 * @package local_onetutor
 */
class connect_form extends \moodleform {
    protected function definition(): void {
        $form = $this->_form;

        $form->addElement('static', 'grants', '', get_string('connectgrants', 'local_onetutor'));
        $form->addElement('text', 'baseurl', get_string('baseurl', 'local_onetutor'), ['size' => 60]);
        $form->setType('baseurl', PARAM_URL);
        $form->addRule('baseurl', null, 'required', null, 'client');
        $form->setDefault('baseurl', get_config('local_onetutor', 'baseurl') ?: 'https://app.onetutor.ai');

        $form->addElement('text', 'registrationcode', get_string('registrationcode', 'local_onetutor'), ['size' => 40]);
        $form->setType('registrationcode', PARAM_ALPHANUMEXT);
        $form->addRule('registrationcode', null, 'required', null, 'client');

        $this->add_action_buttons(false, get_string('connect', 'local_onetutor'));
    }
}
