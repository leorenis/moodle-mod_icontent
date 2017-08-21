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
 * List notes
 *
 * @package    mod_icontent
 * @copyright  2016-2015 Leo Santos {@link http://github.com/leorenis}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id			= required_param('id', PARAM_INT);      // Course Module ID
$action		= optional_param('action', 0, PARAM_ALPHA); // Action
$private	= optional_param('private', 0, PARAM_INT); // Private
$featured	= optional_param('featured', 0, PARAM_INT); // featured
$likes		= optional_param('likes', 0, PARAM_INT); // Likes
$doubttutor	= optional_param('doubttutor', 0, PARAM_INT); // doubttutor
$sort 		= optional_param('sort', '', PARAM_ALPHA);
$page 		= optional_param('page', 0, PARAM_INT);
$perpage 	= optional_param('perpage', ICONTENT_PER_PAGE, PARAM_INT);

$cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$icontent = $DB->get_record('icontent', array('id'=>$cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/icontent:viewnotes', $context);
$urlparams = array('id' => $cm->id, 'action'=>$action);
// Page setting
switch ($action){
	case 'featured':
		$urlparams += array('featured' => $featured);
		$strheading = get_string('highlighted', 'mod_icontent');
		break;
	case 'likes':
		$urlparams += array('likes' => $likes);
		$strheading = get_string('likes', 'mod_icontent');
		break;
	case 'private':
		$urlparams += array('private' => $private);
		$strheading = get_string('privates', 'mod_icontent');
		break;
}
$PAGE->set_url('/mod/icontent/notes.php', $urlparams);
// Header and strings.
$PAGE->set_title($icontent->name);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($icontent->name);
echo $OUTPUT->heading(get_string('mylistcomments', 'mod_icontent').' '. strtolower($strheading), 3);
$url = new moodle_url('/mod/icontent/notes.php', $urlparams + array('page' => $page, 'perpage' => $perpage));
// Get sort value
$sort = icontent_check_value_sort($sort);
// Get users attempts
$notesusers = icontent_get_notes_users_instance($cm->id, $sort, $page, $perpage, $private, $featured, $doubttutor, $likes);
$tnotesusers = icontent_count_notes_users_instance($cm->id, $private, $featured, $doubttutor, $likes);
// Make table questions
$table = new html_table();
$table->id = "idtablenotesusers";
$table->colclasses = array('pictures', 'comments');
$table->attributes = array('class'=>'table table-hover tableattemptsusers');
$table->head  = array(get_string('fullname'), get_string('description'));
if($notesusers) foreach ($notesusers as $notesuser){
	$user = clone $notesuser;
	$user->id = $notesuser->userid;
	$picture = $OUTPUT->user_picture($user, array('size'=>35, 'class'=> 'img-thumbnail pull-left'));
	$linkfirstname = html_writer::link(new moodle_url('/user/view.php', array('id'=>$notesuser->userid, 'course'=>$course->id)), $notesuser->firstname. ' '. $notesuser->lastname, array('title'=>$notesuser->firstname, 'class'=>'lkfullname'));
	// Set data
	$table->data[] = array($picture. $linkfirstname, $notesuser->comment);
}
else {
	echo html_writer::div(get_string('norecordsfound', 'mod_icontent'), 'alert alert-warning');
	echo $OUTPUT->footer();
	exit;
}
// Show table
echo html_writer::start_div('idtablenotes');
echo html_writer::table($table);
echo $OUTPUT->paging_bar($tnotesusers, $page, $perpage, $url);
echo html_writer::end_div();
echo $OUTPUT->footer();