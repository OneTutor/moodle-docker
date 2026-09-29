<?php
namespace local_onetutor;

/**
 * Stored state of this site's connection to OneTutor.
 *
 * @package local_onetutor
 */
class connection {
    /**
     * @return bool whether the site completed registration with OneTutor
     */
    public static function is_connected(): bool {
        return self::site_id() !== '' && self::signing_secret() !== '' && self::base_url() !== '';
    }

    /**
     * @return string the OneTutor base URL, without trailing slash
     */
    public static function base_url(): string {
        return rtrim((string) get_config('local_onetutor', 'baseurl'), '/');
    }

    /**
     * @return string the site id OneTutor assigned at registration
     */
    public static function site_id(): string {
        return (string) get_config('local_onetutor', 'siteid');
    }

    /**
     * @return string the secret signals are signed with
     */
    public static function signing_secret(): string {
        return (string) get_config('local_onetutor', 'signingsecret');
    }

    /**
     * @param string $path API path starting with a slash
     * @param string|null $baseurl base URL to use instead of the stored one
     * @return string the absolute OneTutor API URL
     */
    public static function api_url(string $path, ?string $baseurl = null): string {
        return rtrim($baseurl ?? self::base_url(), '/') . '/api' . $path;
    }

    /**
     * Stores a completed registration.
     *
     * @param string $baseurl the OneTutor base URL
     * @param string $siteid the assigned site id
     * @param string $secret the signing secret
     */
    public static function save(string $baseurl, string $siteid, string $secret): void {
        set_config('baseurl', rtrim($baseurl, '/'), 'local_onetutor');
        set_config('siteid', $siteid, 'local_onetutor');
        set_config('signingsecret', $secret, 'local_onetutor');
    }

    /**
     * Forgets the registration.
     */
    public static function clear(): void {
        unset_config('siteid', 'local_onetutor');
        unset_config('signingsecret', 'local_onetutor');
    }
}
