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
 * @copyright  2004-2011 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id			= required_param('id', PARAM_INT);      // Course Module ID
$pnid		= required_param('pnid', PARAM_INT); 	// page note ID
$confirm	= optional_param('confirm', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$icontent = $DB->get_record('icontent', array('id'=>$cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('mod/icontent:edit', $context);

$PAGE->set_url('/mod/icontent/deletenote.php', array('id'=>$id, 'pnid'=>$pnid));

$pagenote = $DB->get_record('icontent_pages_notes', array('id'=>$pnid), '*', MUST_EXIST);


// Header and strings.
$PAGE->set_title($icontent->name);
$PAGE->set_heading($course->fullname);

// Form processing.
if ($confirm) {  // the operation was confirmed.
	
	$notes = icontent_get_notes_daughters($pagenote->id);
	var_dump($notes);
	die();
  //  redirect('view.php?id='.$cm->id);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($icontent->name." : ".get_string('removenotes', 'mod_icontent'));

// The operation has not been confirmed yet so ask the user to do so.
$notes = icontent_get_notes_daughters($pagenote->id);
$strconfirm = get_string('confpagenotedelete', 'mod_icontent', count($notes));

$continue = new moodle_url('/mod/icontent/deletenote.php', array('id'=>$cm->id, 'pnid'=>$pagenote->id, 'confirm'=>1));
$cancel = new moodle_url('/mod/icontent/view.php', array('id'=>$cm->id, 'pageid'=>$pagenote->pageid));
$listreplies = icontent_make_list_group_notesdaughters($notes);

echo $OUTPUT->confirm("<p>$strconfirm</p><blockquote>$pagenote->comment</blockquote>". $listreplies, $continue, $cancel);
echo $OUTPUT->footer();