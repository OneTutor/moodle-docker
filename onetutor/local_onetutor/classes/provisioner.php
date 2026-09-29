<?php
namespace local_onetutor;

use context_system;
use core_external\util;
use curl;
use moodle_exception;

/**
 * Sets up the least-privilege web service access OneTutor pulls course content with, and registers it.
 *
 * @package local_onetutor
 */
class provisioner {
    /** Shortname of the role granted to the sync user. */
    public const ROLE_SHORTNAME = 'onetutorsync';

    /** Username of the sync user. */
    public const USERNAME = 'onetutor_sync';

    /** Shortname of the external service from db/services.php. */
    public const SERVICE_SHORTNAME = 'local_onetutor';

    /** Read-only capabilities the sync user needs to read the manifest and download module files. */
    public const CAPABILITIES = [
        'webservice/rest:use',
        'local/onetutor:readcoursecontent',
        'moodle/course:view',
        'moodle/course:viewhiddencourses',
        'moodle/course:ignoreavailabilityrestrictions',
        'mod/resource:view',
        'mod/folder:view',
    ];

    /**
     * Provisions web service access and registers this site with OneTutor.
     *
     * @param string $baseurl the OneTutor base URL
     * @param string $registrationcode the one-time code issued by OneTutor
     * @throws moodle_exception when OneTutor rejects the registration
     */
    public static function connect(string $baseurl, string $registrationcode): void {
        global $CFG, $SITE;

        self::enable_rest();
        $userid = self::ensure_user();
        self::ensure_role($userid);
        $service = self::ensure_service($userid);
        $token = self::reset_token($service, $userid);

        try {
            $response = self::register($baseurl, [
                'registrationCode' => $registrationcode,
                'siteUrl' => $CFG->wwwroot,
                'siteName' => format_string($SITE->fullname),
                'moodleRelease' => $CFG->release,
                'pluginVersion' => (string) get_config('local_onetutor', 'version'),
                'token' => $token,
            ]);
        } catch (moodle_exception $e) {
            self::revoke_tokens($service, $userid);
            throw $e;
        }

        connection::save($baseurl, $response['siteId'], $response['signingSecret']);
    }

    /**
     * Revokes OneTutor's token and forgets the registration.
     */
    public static function disconnect(): void {
        global $DB;

        $service = $DB->get_record('external_services', ['shortname' => self::SERVICE_SHORTNAME]);
        $user = $DB->get_record('user', ['username' => self::USERNAME, 'deleted' => 0]);
        if ($service && $user) {
            self::revoke_tokens($service, (int) $user->id);
        }
        connection::clear();
    }

    /**
     * Enables web services and the REST protocol site-wide.
     */
    private static function enable_rest(): void {
        global $CFG;

        set_config('enablewebservices', 1);
        $protocols = array_filter(explode(',', $CFG->webserviceprotocols ?? ''));
        if (!in_array('rest', $protocols, true)) {
            $protocols[] = 'rest';
            set_config('webserviceprotocols', implode(',', $protocols));
        }
    }

    /**
     * @return int the id of the sync user, created when missing
     */
    private static function ensure_user(): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');

        $existing = $DB->get_record('user', ['username' => self::USERNAME, 'mnethostid' => $CFG->mnet_localhost_id]);
        if ($existing && !$existing->deleted) {
            return (int) $existing->id;
        }

        return (int) user_create_user((object) [
            'username' => self::USERNAME,
            'auth' => 'webservice',
            'password' => generate_password(32),
            'firstname' => 'OneTutor',
            'lastname' => 'Sync',
            'email' => 'noreply@onetutor.invalid',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
        ], true, false);
    }

    /**
     * Creates the sync role with exactly CAPABILITIES and assigns it to the user at system level.
     *
     * @param int $userid the sync user
     */
    private static function ensure_role(int $userid): void {
        global $DB;

        $context = context_system::instance();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => self::ROLE_SHORTNAME]);
        if (!$roleid) {
            $roleid = create_role(
                get_string('rolename', 'local_onetutor'),
                self::ROLE_SHORTNAME,
                get_string('roledescription', 'local_onetutor')
            );
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        }
        foreach (self::CAPABILITIES as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
        }
        role_assign($roleid, $userid, $context->id);
        $context->mark_dirty();
    }

    /**
     * @param int $userid the sync user
     * @return \stdClass the enabled external service the user is authorised for
     */
    private static function ensure_service(int $userid): \stdClass {
        global $DB;

        $service = $DB->get_record('external_services', ['shortname' => self::SERVICE_SHORTNAME], '*', MUST_EXIST);
        if (!$service->enabled) {
            $DB->set_field('external_services', 'enabled', 1, ['id' => $service->id]);
            $service->enabled = 1;
        }
        if (!$DB->record_exists('external_services_users', ['externalserviceid' => $service->id, 'userid' => $userid])) {
            $DB->insert_record('external_services_users', (object) [
                'externalserviceid' => $service->id,
                'userid' => $userid,
                'timecreated' => time(),
            ]);
        }
        return $service;
    }

    /**
     * @param \stdClass $service the external service
     * @param int $userid the sync user
     * @return string a fresh permanent token; earlier tokens are revoked
     */
    private static function reset_token(\stdClass $service, int $userid): string {
        self::revoke_tokens($service, $userid);
        return util::generate_token(
            EXTERNAL_TOKEN_PERMANENT,
            $service,
            $userid,
            context_system::instance(),
            0,
            '',
            get_string('tokenname', 'local_onetutor')
        );
    }

    /**
     * @param \stdClass $service the external service
     * @param int $userid the sync user
     */
    private static function revoke_tokens(\stdClass $service, int $userid): void {
        global $DB;

        $DB->delete_records('external_tokens', ['externalserviceid' => $service->id, 'userid' => $userid]);
    }

    /**
     * @param string $baseurl the OneTutor base URL
     * @param array $payload the registration request body
     * @return array{siteId: string, signingSecret: string} OneTutor's response
     * @throws moodle_exception when the request fails or is rejected
     */
    private static function register(string $baseurl, array $payload): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new curl();
        $curl->setHeader(['Content-Type: application/json']);
        $body = $curl->post(connection::api_url('/integrations/moodle/register', $baseurl), json_encode($payload), [
            'CURLOPT_TIMEOUT' => 15,
            'CURLOPT_CONNECTTIMEOUT' => 10,
        ]);

        $status = (int) ($curl->get_info()['http_code'] ?? 0);
        if ($curl->get_errno() || $status < 200 || $status >= 300) {
            $error = json_decode((string) $body, true);
            $message = is_array($error) && is_string($error['message'] ?? null) ? $error['message'] : null;
            throw new moodle_exception(
                'registrationfailed',
                'local_onetutor',
                '',
                $curl->error ?: ($message ? "$message (HTTP $status)" : "HTTP $status")
            );
        }
        $response = json_decode($body, true);
        if (!is_array($response) || empty($response['siteId']) || empty($response['signingSecret'])) {
            throw new moodle_exception('registrationfailed', 'local_onetutor', '', 'unexpected response');
        }
        return $response;
    }
}
