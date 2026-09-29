<?php
namespace local_onetutor;

use context_module;
use moodle_url;
use stdClass;

/**
 * Builds the raw content manifest of a course; OneTutor applies all sync policy to it.
 *
 * @package local_onetutor
 */
class manifest {
    /** Module types whose files are listed in the manifest. */
    private const FILE_MODULES = ['resource', 'folder'];

    /**
     * Builds the manifest of one course.
     *
     * @param stdClass $course the course record
     * @return array the manifest, matching get_course_manifest::execute_returns()
     */
    public static function build(stdClass $course): array {
        global $DB;

        $modinfo = get_fast_modinfo($course, -1);

        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $sections[] = [
                'id' => (int) $section->id,
                'number' => (int) $section->section,
                'name' => get_section_name($course, $section),
                'visible' => (bool) $section->visible,
                'availability' => $section->availability,
            ];
        }

        $revisions = [];
        foreach (self::FILE_MODULES as $modname) {
            $instanceids = array_map(fn($cm) => $cm->instance, $modinfo->get_instances_of($modname));
            $revisions[$modname] = $instanceids
                ? $DB->get_records_list($modname, 'id', array_values($instanceids), '', 'id, revision, timemodified')
                : [];
        }

        $modules = [];
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->deletioninprogress) {
                continue;
            }
            $instance = $revisions[$cm->modname][$cm->instance] ?? null;
            $modules[] = [
                'cmid' => (int) $cm->id,
                'modname' => $cm->modname,
                'name' => $cm->get_formatted_name(),
                'sectionid' => (int) $cm->section,
                'visible' => (bool) $cm->visible,
                'visibleoncoursepage' => (bool) $cm->visibleoncoursepage,
                'availability' => $cm->availability,
                'timemodified' => $instance ? (int) $instance->timemodified : (int) $cm->added,
                'files' => $instance ? self::files($cm->id, $cm->modname, (int) $instance->revision) : [],
            ];
        }

        return [
            'course' => [
                'id' => (int) $course->id,
                'fullname' => format_string($course->fullname),
                'shortname' => format_string($course->shortname),
                'visible' => (bool) $course->visible,
                'originalcourseid' => empty($course->originalcourseid) ? null : (int) $course->originalcourseid,
                'timemodified' => (int) $course->timemodified,
            ],
            'sections' => $sections,
            'modules' => $modules,
        ];
    }

    /**
     * Lists the content files of a resource or folder module.
     *
     * @param int $cmid the course module id
     * @param string $modname resource or folder
     * @param int $revision the module revision, used as the pluginfile itemid
     * @return array the file entries
     */
    private static function files(int $cmid, string $modname, int $revision): array {
        $context = context_module::instance($cmid);
        $component = 'mod_' . $modname;
        $files = get_file_storage()->get_area_files($context->id, $component, 'content', 0, 'sortorder DESC, id ASC', false);

        $entries = [];
        foreach ($files as $file) {
            $entries[] = [
                'id' => (int) $file->get_id(),
                'contenthash' => $file->get_contenthash(),
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
                'filesize' => (int) $file->get_filesize(),
                'mimetype' => (string) $file->get_mimetype(),
                'timemodified' => (int) $file->get_timemodified(),
                'license' => $file->get_license(),
                'fileurl' => moodle_url::make_webservice_pluginfile_url(
                    $context->id,
                    $component,
                    'content',
                    $revision,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false),
            ];
        }
        return $entries;
    }
}
