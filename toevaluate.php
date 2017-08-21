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
 * to evaluate user icontent page
 *
 * @package    mod_icontent
 * @copyright  2016-2015 Leo Santos {@link http://github.com/leorenis}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id			= required_param('id', PARAM_INT);      // Course Module ID
$userid		= required_param('userid', PARAM_INT); 	// page note ID
$action		= optional_param('action', 0, PARAM_BOOL); // Action
$status		= optional_param('status', ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE, PARAM_ALPHA); // Status

$cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$icontent = $DB->get_record('icontent', array('id'=>$cm->instance), '*', MUST_EXIST);
$user = $DB->get_record('user', array('id'=>$userid), '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('mod/icontent:manage', $context);
// Page setting
$PAGE->set_url('/mod/icontent/toevaluate.php', array('id' => $cm->id, 'action'=>$action, 'userid'=>$userid, 'sesskey'=>sesskey()));
// Header and strings.
$PAGE->set_title($icontent->name);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('results', 'mod_icontent'))->add(get_string('manualreview', 'mod_icontent'))->add(fullname($user));
// Get total questions by instance
$tquestinstance = icontent_get_totalquestions_by_instance($cm->id);
// Check action
if ($action){
	// Receives values
	$questions = optional_param_array('question', array(), PARAM_RAW);
	$i = 0;
	if($questions) foreach ($questions as $qname => $qvalue){
		list($strname, $answerid) = explode('-', $qname);
		$qvalue = $qvalue > 1 ? 1 : $qvalue;
		$attempt = new stdClass();
		$attempt->id = $answerid;
		$attempt->fraction = $qvalue;
		$attempt->rightanswer = ICONTENT_QTYPE_ESSAY_STATUS_VALUED;
		// Save values
		$update = icontent_update_question_attempts($attempt);
		$i ++;
	}
	if($update){
		// Update grade
		icontent_set_grade_item($icontent, $cm->id, $user->id);
		// Log event.
		\mod_icontent\event\question_toevaluate_created::create_from_question_toevaluate($icontent, $context, $user)->trigger();
		redirect(new moodle_url('/mod/icontent/grading.php', array('id'=>$cm->id, 'action'=> 'grading')), get_string('msgsucessevaluate', 'mod_icontent', $i));
	}
}
// Make page
echo $OUTPUT->header();
echo $OUTPUT->heading($icontent->name);
echo $OUTPUT->heading(get_string('manualreviewofparticipant', 'mod_icontent', fullname($user)), 3);
$qopenanswers = icontent_get_questions_and_open_answers_by_user($user->id, $cm->id, $status);
echo html_writer::start_tag('form', array('method'=>'post'));
if($qopenanswers) foreach ($qopenanswers as $qopenanswer){
	$fieldname = 'question[attemptid-'.$qopenanswer->id.']';
	$fieldid = 'idquestion-'.$qopenanswer->questionid.'_pqid-'.$qopenanswer->pagesquestionsid.'_'.ICONTENT_QTYPE_ESSAY;
	$fraction = ($status === ICONTENT_QTYPE_ESSAY_STATUS_VALUED) ? $qopenanswer->fraction : '';	// Check status
	// Get page
	$page = $DB->get_record('icontent_pages', array('id'=>$qopenanswer->pageid), 'id, title, pagenum', MUST_EXIST);
	$attempttitle = html_writer::tag('strong', get_string('strattempttitle', 'mod_icontent', $page));
	$qtext = html_writer::div($qopenanswer->questiontext, 'qtext');
	$qanswer = html_writer::div($qopenanswer->answertext, 'answer qtype_essay_editor qtype_essay_response readonly');
	$ablock = html_writer::div($qanswer, 'ablock');
	$skipline = html_writer::empty_tag('br');
	$labelgrade = html_writer::label(get_string('grade'). $skipline, $fieldid, null, array('class'=>'labelfieldgrade'));
	$fieldfraction = html_writer::empty_tag('input', array('type'=>'number', 'class'=>'input-mini', 'value'=>$fraction, 'id'=>$fieldid, 'name'=>$fieldname, 'required'=>'required', 'step'=>'0.1', 'min'=>'0', 'max'=>'1'));
	$labelmaxgrade = html_writer::label(get_string('strmaxgrade', 'mod_icontent'), null);
	$divgrade = html_writer::div($labelgrade. $fieldfraction. $labelmaxgrade, 'gblock');
	$content = html_writer::div($qtext. $ablock. $divgrade, 'formulation');
	echo html_writer::div($attempttitle. $content, 'que manualgraded '.ICONTENT_QTYPE_ESSAY, array('id'=>'idq'.$qopenanswer->questionid));
	
}else{
	redirect(new moodle_url('/mod/icontent/grading.php', array('id'=>$cm->id, 'action'=>$action)), get_string('norecordsfound', 'mod_icontent'));
}
echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'action', 'value'=>true));
echo html_writer::empty_tag('input', array('type'=>'submit', 'value'=>get_string('savechanges')));
echo html_writer::end_tag('form');
echo $OUTPUT->footer();