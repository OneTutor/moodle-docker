<?php
namespace local_onetutor;

use curl;
use local_onetutor\task\send_signal;

/**
 * Tells OneTutor that a course changed, so OneTutor pulls its manifest again.
 *
 * @package local_onetutor
 */
class signal {
    /** @var array<int, true> courses already signalled in this request */
    private static array $sent = [];

    /**
     * Sends the signal now, falling back to a retried adhoc task when OneTutor is unreachable.
     *
     * @param int $courseid the changed course
     * @param string $reason the event that caused the change
     */
    public static function course_changed(int $courseid, string $reason): void {
        if (!connection::is_connected() || isset(self::$sent[$courseid])) {
            return;
        }
        self::$sent[$courseid] = true;

        if (!self::send($courseid, $reason, 2)) {
            $task = new send_signal();
            $task->set_custom_data(['courseid' => $courseid, 'reason' => $reason]);
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }

    /**
     * Posts one signed signal.
     *
     * @param int $courseid the changed course
     * @param string $reason the event that caused the change
     * @param int $timeout seconds before giving up
     * @return bool whether OneTutor accepted it
     */
    public static function send(int $courseid, string $reason, int $timeout): bool {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $siteid = connection::site_id();
        $body = json_encode([
            'siteId' => $siteid,
            'courseId' => $courseid,
            'reason' => $reason,
            'sentAt' => time(),
        ]);

        $curl = new curl();
        $curl->setHeader([
            'Content-Type: application/json',
            'X-OneTutor-Site: ' . $siteid,
            'X-OneTutor-Signature: sha256=' . hash_hmac('sha256', $body, connection::signing_secret()),
        ]);
        $curl->post(connection::api_url('/integrations/moodle/signal'), $body, [
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_CONNECTTIMEOUT' => $timeout,
        ]);

        $status = (int) ($curl->get_info()['http_code'] ?? 0);
        return !$curl->get_errno() && $status >= 200 && $status < 300;
    }
}
