<?php
namespace local_onetutor\task;

use local_onetutor\connection;
use local_onetutor\signal;
use moodle_exception;

/**
 * Retries a course-changed signal that could not be delivered inline.
 *
 * @package local_onetutor
 */
class send_signal extends \core\task\adhoc_task {
    public function execute(): void {
        if (!connection::is_connected()) {
            return;
        }
        $data = $this->get_custom_data();
        if (!signal::send((int) $data->courseid, (string) $data->reason, 15)) {
            throw new moodle_exception('signalfailed', 'local_onetutor');
        }
    }
}
