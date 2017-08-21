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

$id			= required_param('id', PARAM_INT);      // Course Module ID
$pnid		= required_param('pnid', PARAM_INT); 	// page note ID
$confirm	= optional_param('confirm', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$icontent = $DB->get_record('icontent', array('id'=>$cm->instance), '*', MUST_EXIST);
$pagenote = $DB->get_record('icontent_pages_notes', array('id'=>$pnid), '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
icontent_user_can_remove_note($pagenote, $context);

$PAGE->set_url('/mod/icontent/deletenote.php', array('id'=>$id, 'pnid'=>$pnid));

// Header and strings.
$PAGE->set_title($icontent->name);
$PAGE->set_heading($course->fullname);

// Form processing.
if ($confirm) {
	// the operation was confirmed.
	$notes = icontent_get_notes_daughters($pagenote->id);
	icontent_remove_notes($pagenote->pageid, $pagenote->id);
	\mod_icontent\event\note_deleted::create_from_note($icontent, $context, $pagenote)->trigger();
	$url = new moodle_url('/mod/icontent/view.php', array('id'=>$cm->id, 'pageid'=>$pagenote->pageid));
 	redirect($url, get_string('msgsucessexclusion', 'mod_icontent'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($icontent->name." : ".get_string('removenotes', 'mod_icontent'));

// Operation not confirmed.
$notes = icontent_get_notes_daughters($pagenote->id);
$strconfirm = get_string('confpagenotedelete', 'mod_icontent', count($notes));

$continue = new moodle_url('/mod/icontent/deletenote.php', array('id'=>$cm->id, 'pnid'=>$pagenote->id, 'confirm'=>1));
$cancel = new moodle_url('/mod/icontent/view.php', array('id'=>$cm->id, 'pageid'=>$pagenote->pageid));
$listreplies = icontent_make_list_group_notesdaughters($notes);

echo $OUTPUT->confirm("<p>$strconfirm</p><blockquote>$pagenote->comment</blockquote>". $listreplies, $continue, $cancel);
echo $OUTPUT->footer();