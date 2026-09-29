<?php
namespace local_onetutor\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_onetutor\manifest;

/**
 * Returns the content manifest of one course.
 *
 * @package local_onetutor
 */
class get_course_manifest extends external_api {
    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * @param int $courseid the course id
     * @return array the manifest
     */
    public static function execute(int $courseid): array {
        global $DB;

        ['courseid' => $courseid] = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/onetutor:readcoursecontent', $context);

        return manifest::build($course);
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $availability = fn(string $of) => new external_value(
            PARAM_RAW,
            "Raw availability JSON of the $of, null when unrestricted",
            VALUE_REQUIRED,
            null,
            NULL_ALLOWED
        );

        return new external_single_structure([
            'course' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course id'),
                'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
                'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
                'visible' => new external_value(PARAM_BOOL, 'Whether the course is visible to students'),
                'originalcourseid' => new external_value(
                    PARAM_INT,
                    'Course this one was restored or copied from on this site',
                    VALUE_REQUIRED,
                    null,
                    NULL_ALLOWED
                ),
                'timemodified' => new external_value(PARAM_INT, 'Course settings modification time'),
            ]),
            'sections' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Section id'),
                'number' => new external_value(PARAM_INT, 'Section position in the course'),
                'name' => new external_value(PARAM_TEXT, 'Section name'),
                'visible' => new external_value(PARAM_BOOL, 'Whether the section is visible to students'),
                'availability' => $availability('section'),
            ])),
            'modules' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Course module id'),
                'modname' => new external_value(PARAM_PLUGIN, 'Module type, e.g. resource'),
                'name' => new external_value(PARAM_TEXT, 'Module name'),
                'sectionid' => new external_value(PARAM_INT, 'Id of the containing section'),
                'visible' => new external_value(PARAM_BOOL, 'Whether the module is visible to students'),
                'visibleoncoursepage' => new external_value(PARAM_BOOL, 'False for stealth modules'),
                'availability' => $availability('module'),
                'timemodified' => new external_value(PARAM_INT, 'Module instance modification time'),
                'files' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'File id'),
                    'contenthash' => new external_value(PARAM_ALPHANUM, 'SHA-1 of the file content'),
                    'filepath' => new external_value(PARAM_PATH, 'Path inside the module file area'),
                    'filename' => new external_value(PARAM_FILE, 'File name'),
                    'filesize' => new external_value(PARAM_INT, 'Size in bytes'),
                    'mimetype' => new external_value(PARAM_RAW, 'MIME type'),
                    'timemodified' => new external_value(PARAM_INT, 'File modification time'),
                    'license' => new external_value(PARAM_RAW, 'License shortname', VALUE_REQUIRED, null, NULL_ALLOWED),
                    'fileurl' => new external_value(PARAM_URL, 'Download URL; append the token parameter'),
                ])),
            ])),
        ]);
    }
}
