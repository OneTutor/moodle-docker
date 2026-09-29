<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

[$options] = cli_get_params(['reset' => false], ['r' => 'reset']);

const SHORTNAME = 'OTSYNC';

\core\session\manager::set_user(get_admin());
set_config('allowstealth', 1);

if ($existing = $DB->get_record('course', ['shortname' => SHORTNAME])) {
    if (!$options['reset']) {
        cli_writeln("Course exists: id {$existing->id} (pass --reset to recreate)");
        exit(0);
    }
    delete_course($existing, false);
}

function pdf_bytes(string $title, string $body): string {
    $escape = fn(string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    $stream = "BT /F1 20 Tf 72 740 Td ({$escape($title)}) Tj ET\nBT /F1 12 Tf 72 700 Td ({$escape($body)}) Tj ET";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length ' . strlen($stream) . " >>\nstream\n$stream\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $i => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n$object\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
}

function draft(array $files): int {
    global $USER;
    $itemid = file_get_unused_draft_itemid();
    $context = context_user::instance($USER->id);
    foreach ($files as $filename => $content) {
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
            'license' => 'cc-4.0',
        ], $content);
    }
    return $itemid;
}

$generator = testing_util::get_data_generator();
$course = $generator->create_course([
    'shortname' => SHORTNAME,
    'fullname' => 'OneTutor sync fixture',
    'numsections' => 3,
]);
$group = $generator->create_group(['courseid' => $course->id, 'name' => 'Group A']);

$lecture1 = pdf_bytes('Lecture 1: Photosynthesis', 'Light reactions convert light energy into ATP and NADPH.');
$future = time() + 7 * DAYSECS;

$resources = [
    ['Lecture 1', 1, ['lecture-1.pdf' => $lecture1], []],
    ['Lecture 2 (hidden)', 1, ['lecture-2.pdf' => pdf_bytes('Lecture 2', 'The Calvin cycle fixes carbon.')], ['visible' => 0]],
    ['Stealth handout', 1, ['stealth.pdf' => pdf_bytes('Stealth', 'Only reachable by link.')], ['visibleoncoursepage' => 0]],
    ['Exam solutions (next week)', 2, ['solutions.pdf' => pdf_bytes('Solutions', 'Answer key for the exam.')], [
        'availability' => json_encode(['op' => '&', 'c' => [['type' => 'date', 'd' => '>=', 't' => $future]], 'showc' => [true]]),
    ]],
    ['Group A notes', 2, ['group-a.pdf' => pdf_bytes('Group A', 'Notes for group A only.')], [
        'availability' => json_encode(['op' => '&', 'c' => [['type' => 'group', 'id' => (int) $group->id]], 'showc' => [true]]),
    ]],
    ['Hidden section lecture', 3, ['hidden-section.pdf' => pdf_bytes('Hidden section', 'Prepared for later.')], []],
];
foreach ($resources as [$name, $section, $files, $extra]) {
    $generator->create_module('resource', ['course' => $course->id, 'section' => $section, 'name' => $name, 'files' => draft($files)] + $extra);
}

$generator->create_module('folder', [
    'course' => $course->id,
    'section' => 2,
    'name' => 'Reading pack',
    'files' => draft([
        'reading-1.pdf' => pdf_bytes('Reading 1', 'Chlorophyll absorbs red and blue light.'),
        'lecture-1-copy.pdf' => $lecture1,
        'notes.txt' => 'Plain text notes are not a PDF.',
    ]),
]);
$generator->create_module('page', ['course' => $course->id, 'section' => 0, 'name' => 'Syllabus', 'content' => '<p>Course syllabus</p>']);

set_section_visible($course->id, 3, 0);

cli_writeln("Created course id {$course->id}");
