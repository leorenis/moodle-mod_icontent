<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Shows a particular instance of icontent to users.
 *
 * @package    mod_icontent
 * @copyright  2016 Leo Renis Santos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_icontent\local\icontent_info;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/lib.php');
require_once("$CFG->libdir/resourcelib.php");

$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$n  = optional_param('n', 0, PARAM_INT); // Icontent instance ID.
$edit = optional_param('edit', -1, PARAM_BOOL); // Edit mode.
$pageid = optional_param('pageid', null, PARAM_INT); // Chapter ID.
$removeqpid = optional_param('removeqpid', 0, PARAM_INT);
$savetags = optional_param('savetags', 0, PARAM_BOOL);

if ($pageid !== null && $pageid <= 0) {
    throw new invalid_parameter_exception('Invalid pageid value');
}

if ($id && $n) {
    throw new invalid_parameter_exception('Invalid request: provide either id or n, not both');
}

if (!$id && !$n && $pageid === null) {
    throw new invalid_parameter_exception('Missing required parameter: id, n, or pageid');
}

$cm = null;
$course = null;
$icontent = null;
$page = null;

// Resolve activity from pageid when arriving from page links.
if ($pageid !== null) {
    $page = $DB->get_record('icontent_pages', ['id' => $pageid], 'id, cmid', MUST_EXIST);
    $cm = get_coursemodule_from_id('icontent', $page->cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);
}
if ($id) {
    $cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($n) {
    $icontent = $DB->get_record('icontent', ['id' => $n], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $icontent->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('icontent', $icontent->id, $course->id, false, MUST_EXIST);
}

if (!$cm) {
    throw new moodle_exception(get_string('incorrectmodule', 'icontent'));
}

if (!$course) {
    throw new moodle_exception(get_string('incorrectcourseid', 'icontent'));
}

if ($pageid !== null && (!$page || (int)$page->cmid !== (int)$cm->id)) {
    throw new invalid_parameter_exception('Invalid pageid for this icontent module');
}

// Check login access.
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Log this request.
$event = \mod_icontent\event\course_module_viewed::create(
    [
        'objectid' => $PAGE->cm->instance,
        'context' => $PAGE->context,
    ]
);
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $icontent);
$event->trigger();

// Mark module as viewed for completion.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Check permissions.
$allowedit = has_capability('mod/icontent:edit', $context);
$edit = icontent_has_permission_edition($allowedit, $edit);

if ($removeqpid > 0) {
    require_sesskey();
    if (!has_any_capability(['mod/icontent:edit', 'mod/icontent:manage'], $context)) {
        throw new required_capability_exception($context, 'mod/icontent:edit', 'nopermissions', '');
    }

    if ($pageid === null) {
        throw new moodle_exception(get_string('incorrectpage', 'icontent'));
    }

    $pagerecord = $DB->get_record('icontent_pages', [
        'id' => $pageid,
        'cmid' => $cm->id,
        'icontentid' => $icontent->id,
    ], '*', MUST_EXIST);

    if (icontent_checks_answers_of_currentpage((int)$pagerecord->id, (int)$cm->id)) {
        redirect(
            new moodle_url('/mod/icontent/view.php', ['id' => $cm->id, 'pageid' => $pageid]),
            get_string('msgstatusdisplay', 'mod_icontent'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    $questionmapping = $DB->get_record('icontent_pages_questions', [
        'id' => $removeqpid,
        'pageid' => $pageid,
        'cmid' => $cm->id,
    ], '*', MUST_EXIST);

    icontent_remove_questionpagebyid((int)$questionmapping->id);

    redirect(
        new moodle_url('/mod/icontent/view.php', ['id' => $cm->id, 'pageid' => $pageid]),
        get_string('msgsucessexclusion', 'mod_icontent')
    );
}

if ($savetags) {
    require_sesskey();

    if ($pageid === null) {
        throw new moodle_exception(get_string('incorrectpage', 'icontent'));
    }

    require_capability('mod/icontent:edit', $context);

    $pagetags = optional_param('pagetags', '', PARAM_RAW_TRIMMED);
    icontent_save_page_tags((int)$pageid, (int)$cm->id, $context, $pagetags);

    redirect(
        new moodle_url('/mod/icontent/view.php', ['id' => $cm->id, 'pageid' => $pageid]),
        get_string('msgsucess', 'mod_icontent')
    );
}

// Read pages.
$pages = icontent_info::icontent_preload_pages($icontent);

if ($allowedit && !$pages) {
    redirect('edit.php?cmid=' . $cm->id); // No pages - add new one.
}

// Print the page header.
$PAGE->set_url('/mod/icontent/view.php', ['id' => $cm->id]);
$PAGE->force_settings_menu();
$PAGE->set_title($icontent->name);
$PAGE->set_heading(format_string($course->fullname));
// Get renderer.
$renderer = $PAGE->get_renderer('mod_icontent');
// JS.
$renderer->icontent_requires_external_js();
$renderer->icontent_requires_internal_js();
// CSS.
$renderer->icontent_requires_css();
// Get first page to be presented.
$startwithpage = $pageid !== null ? icontent_get_pagenum_by_pageid($pageid) : icontent_get_startpagenum($icontent, $context);
$showpage = icontent_get_fullpageicontent($startwithpage, $icontent, $context);

icontent_add_fake_block($pages, $startwithpage, $icontent, $cm, $edit); // Add block summary.

// Content display HTML code.

// Check whether icontent is currently available.
$timenow = time();
if ($course->format == 'weeks' && $icontent->days) {
    $sectionnumber = $DB->get_field('course_sections', 'section', ['id' => $cm->section], MUST_EXIST);
    $timestart = $course->startdate + (($sectionnumber - 1) * WEEKSECS);
    if ($icontent->days) {
        $timefinish = $timestart + (3600 * 24 * $icontent->days);
    } else {
        $timefinish = $course->enddate;
    }
} else if (!(icontent_info::icontent_available($icontent))) {
    // Use configured availability time limits.
    $timestart = $icontent->timeopen;
    $timefinish = $icontent->timeclose;
    $icontent->days = 0;
} else {
    // No time limits for this icontent.
    $timestart = $timenow - 1;
    $timefinish = $timenow + 1;
    $icontent->days = 0;
}

// Output starts here.
echo $OUTPUT->header();
// Output intro for pre-4.0 branches.
if (($icontent->intro) && ($CFG->branch < 400)) {
    echo $OUTPUT->heading($icontent->name);
    echo $OUTPUT->box(format_module_intro('icontent', $icontent, $cm->id), 'generalbox mod_introbox', 'icontentintro');
}

// Check whether this icontent is open.
if ($timenow > $timestart) {
    // This echo gets render before the top of the slide. It prints on every slide.

    if ($timenow < $timefinish) {
        $showpagingbars = count($pages) > 1;
        // Decide whether or not to show upper navigation buttons.
        if ($showpagingbars) {
            if (count($pages) > 10) {
                echo icontent_simple_paging_button_bar($pages, $cm->id, $startwithpage);
            } else {
                echo icontent_full_paging_button_bar($pages, $cm->id, $startwithpage);
            }
        }
        // Add all the content into a box.
        echo $OUTPUT->box_start('icontent-page', 'idicontentpages');
        echo $showpage->fullpageicontent;
        echo $OUTPUT->box_end();
        if ($showpagingbars) {
            echo icontent_simple_paging_button_bar($pages, $cm->id, $startwithpage);
        }
    } else {
        // Show message when activity period has ended.
        echo '<div class="editend"><strong>' . get_string('activityended', 'icontent') . ': ';
        echo userdate($timefinish) . '</strong></div>';
    }
} else {
    echo '<div class="warning">' . get_string('notopenuntil', 'icontent') . ': ';
    echo userdate($timestart) . '.</div>';
}

// Finish the page.
echo $OUTPUT->footer();
