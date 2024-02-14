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

// Replace icontent with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/lib.php');
require_once("$CFG->libdir/resourcelib.php"); // Apagar - Switch off?

$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$n  = optional_param('n', 0, PARAM_INT); // Icontent instance ID.
$edit = optional_param('edit', -1, PARAM_BOOL); // Edit mode.
$pageid = optional_param('pageid', 0, PARAM_INT); // Chapter ID.

if ($id) {
    $cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($n) {
    $icontent = $DB->get_record('icontent', ['id' => $n], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $icontent->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('icontent', $icontent->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
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

// Check permissions.
$allowedit  = has_capability('mod/icontent:edit', $context);
$edit = icontent_has_permission_edition($allowedit, $edit);

// Read pages.
$pages = icontent_preload_pages($icontent);

if ($allowedit && !$pages) {
    redirect('edit.php?cmid='.$cm->id); // No pages - add new one.
}

// Print the page header.
$PAGE->set_url('/mod/icontent/view.php', ['id' => $cm->id]);
$PAGE->force_settings_menu();
$PAGE->set_title(format_string($icontent->name));
$PAGE->set_heading(format_string($course->fullname));
// Get renderer.
$renderer = $PAGE->get_renderer('mod_icontent');
// JS.
$renderer->icontent_requires_external_js();
$renderer->icontent_requires_internal_js();
// CSS.
$renderer->icontent_requires_css();
// Get first page to be presented.
$startwithpage  = $pageid ? icontent_get_pagenum_by_pageid($pageid) : icontent_get_startpagenum($icontent, $context);
$showpage = icontent_get_fullpageicontent($startwithpage, $icontent, $context);

icontent_add_fake_block($pages, $startwithpage, $icontent, $cm, $edit); // Add block sumary.

// Content display HTML code.

// Output starts here.
echo $OUTPUT->header();
// Replace the following lines with you own code.
echo $OUTPUT->heading($icontent->name);

// Conditions to show the intro. 20231227 I'm not sure if this is needed.
if ($icontent->intro) {
   echo $OUTPUT->box(format_module_intro('icontent', $icontent, $cm->id), 'generalbox mod_introbox', 'icontentintro');
}
// Content box.
echo icontent_full_paging_button_bar($pages, $cm->id, $startwithpage);
echo $OUTPUT->box_start('icontent-page', 'idicontentpages');
echo $showpage->fullpageicontent;
echo $OUTPUT->box_end();
echo icontent_simple_paging_button_bar($pages, $cm->id, $startwithpage);

// Finish the page.
echo $OUTPUT->footer();
