<?php
namespace local_onetutor;

use core\event\base;

/**
 * Forwards course content events to OneTutor as course-changed signals.
 *
 * @package local_onetutor
 */
class observer {
    /**
     * @param base $event any observed course content event
     */
    public static function course_changed(base $event): void {
        if (empty($event->courseid) || $event->courseid == SITEID) {
            return;
        }
        signal::course_changed((int) $event->courseid, $event->eventname);
    }
}
