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
 * Delete icontent page
 *
 * @package    mod_icontent
 * @copyright  2016-2015 Leo Santos {@link http://github.com/leorenis}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id        = required_param('id', PARAM_INT);        // Course Module ID
$pageid = required_param('pageid', PARAM_INT); // page ID
$confirm   = optional_param('confirm', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$icontent = $DB->get_record('icontent', array('id'=>$cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('mod/icontent:edit', $context);

$PAGE->set_url('/mod/icontent/delete.php', array('id'=>$id, 'pageid'=>$pageid));

$page = $DB->get_record('icontent_pages', array('id'=>$pageid, 'icontentid'=>$icontent->id), '*', MUST_EXIST);

// Header and strings.
$PAGE->set_title($icontent->name);
$PAGE->set_heading($course->fullname);

// Form processing.
if ($confirm) {
	// The operation was confirmed.
    $fs = get_file_storage();
 	// Deleting all files and records linked to page
    $fs->delete_area_files($context->id, 'mod_icontent', 'page', $page->id);
	$fs->delete_area_files($context->id, 'mod_icontent', 'bgpage', $page->id);
	// Delete records
	$DB->delete_records('icontent_pages_displayed', array('pageid'=>$page->id));
	// Get page questions for remove items attempts
	$pagequestions = $DB->get_records('icontent_pages_questions', array('pageid' => $page->id), null, 'id, pageid, cmid');
	if($pagequestions) foreach ($pagequestions as $pagequestion){
		$DB->delete_records('icontent_question_attempts', array('pagesquestionsid' => $pagequestion->id));
	}
	$DB->delete_records('icontent_pages_questions', array('pageid'=>$page->id));
	icontent_remove_notes($page->id); // remove notes and notes like
    $DB->delete_records('icontent_pages', array('id'=>$page->id));
    icontent_preload_pages($icontent); // Fix structure.
    // Event log
    \mod_icontent\event\page_deleted::create_from_page($icontent, $context, $page)->trigger();
    // Make URL
    $url = new moodle_url('/mod/icontent/view.php', array('id'=>$cm->id));
    redirect($url);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($icontent->name);

// The operation has not been confirmed yet so ask the user to do so.
$strconfirm = get_string('confpagedelete', 'mod_icontent');

echo '<br />';
$continue = new moodle_url('/mod/icontent/delete.php', array('id'=>$cm->id, 'pageid'=>$page->id, 'confirm'=>1));
$cancel = new moodle_url('/mod/icontent/view.php', array('id'=>$cm->id, 'pageid'=>$page->id));
echo $OUTPUT->confirm("<strong>$page->title</strong><p>$strconfirm</p>", $continue, $cancel);

echo $OUTPUT->footer();