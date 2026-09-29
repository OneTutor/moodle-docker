<?php
namespace local_onetutor\privacy;

/**
 * The plugin stores no personal data and sends only course content to OneTutor.
 *
 * @package local_onetutor
 */
class provider implements \core_privacy\local\metadata\null_provider {
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
