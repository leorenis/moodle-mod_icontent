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
 * Try Again icontent page
 *
 * @package    mod_icontent
 * @copyright  2016-2015 Leo Santos {@link http://github.com/leorenis}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id			= required_param('id', PARAM_INT);      // Course Module ID
$pageid		= required_param('pageid', PARAM_INT); 	// page note ID
$confirm	= optional_param('confirm', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$icontent = $DB->get_record('icontent', array('id'=>$cm->instance), '*', MUST_EXIST);
$page = $DB->get_record('icontent_pages', array('id'=>$pageid), 'id, pagenum, title', MUST_EXIST);
// Check auth
require_login($course, false, $cm);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('mod/icontent:answerquestionstryagain', $context);
// Page setting
$PAGE->set_url('/mod/icontent/deleteattempt.php', array('id' => $cm->id, 'pageid' => $pageid,'sesskey' => sesskey()));
// Header and strings.
$PAGE->set_title($icontent->name);
$PAGE->set_heading($course->fullname);
// Form processing.
if ($confirm) {
	// Try the operation confirmed.
	$delete = icontent_remove_answers_attempt_toquestion_by_page($pageid, $cm->id);
	if($delete){
		// Update grade
		icontent_set_grade_item($icontent, $cm->id, $USER->id);
		// Event log
		\mod_icontent\event\question_attempt_deleted::create_from_question_attempt($icontent, $context, $pageid)->trigger();
		// Make URL and redirect
		$url = new moodle_url('/mod/icontent/view.php', array('id'=>$cm->id, 'pageid'=>$pageid));
		redirect($url, get_string('msgsucessexclusion', 'mod_icontent'));
	}	
}
echo $OUTPUT->header();
echo $OUTPUT->heading($icontent->name." : ".get_string('confdeleteattempt', 'mod_icontent', $page));

// Operation not confirmed.
$attemptsummary = icontent_get_attempt_summary_by_page($pageid, $cm->id);
$strconfirm = get_string('msgconfirmdeleteattempt', 'mod_icontent', $attemptsummary);

$continue = new moodle_url('/mod/icontent/deleteattempt.php', array('id' => $cm->id, 'pageid' => $pageid, 'confirm'=>1));
$cancel = new moodle_url('/mod/icontent/view.php', array('id'=>$cm->id, 'pageid'=>$pageid));

echo $OUTPUT->confirm("<p>$strconfirm</p>", $continue, $cancel);
echo $OUTPUT->footer();