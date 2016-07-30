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
 * Process ajax requests
 *
 * @copyright 2016 Leo Renis Santos
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_icontent
 */

if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$sesskey = optional_param('sesskey', false, PARAM_TEXT);
#$itemorder = optional_param('itemorder', false, PARAM_SEQUENCE);

if ($id) {
    $cm         = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $icontent  	= $DB->get_record('icontent', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $icontent  	= $DB->get_record('icontent', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $icontent->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('icontent', $icontent->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}
require_sesskey();
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
// Check actions
$return = false;
switch ($action) {
	case 'loadpage':
		require_capability('mod/icontent:view', $context);
		$pagenum = required_param('pagenum', PARAM_INT);
		$return = icontent_ajax_getpage($pagenum, $icontent, $context);
		break;
	// Save and return records table {pages_notes}
	case 'savereturnpagenotes':
		require_capability('mod/icontent:viewnotes', $context);
		$pageid = required_param('pageid', PARAM_INT);
		$note = new stdClass;
		$note->comment 		= required_param('comment', PARAM_CLEANHTML);
		$note->cmid 		= required_param('id', PARAM_INT);
		$note->featured 	= required_param('featured', PARAM_INT);
		$note->private 		= required_param('private', PARAM_INT);
		$note->doubttutor 	= required_param('doubttutor', PARAM_INT);
		$note->tab 			= required_param('tab', PARAM_ALPHANUMEXT);
		// Prepare return
		$return = icontent_ajax_savereturnnotes($pageid, $note, $icontent);
		break;
		
	case 'likenote':
		require_capability('mod/icontent:likenotes', $context);
		$notelike = new stdClass;
		$notelike->pagenoteid 	= required_param('pagenoteid', PARAM_INT);
		$notelike->cmid 		= required_param('id', PARAM_INT);
		
		$return = icontent_ajax_likenote($notelike, $icontent);
		break;
		
	case 'editnote':
		require_capability('mod/icontent:editnotes', $context);
		$pagenote = new stdClass;
		$pagenote->id 		= required_param('pagenoteid', PARAM_INT);
		$pagenote->cmid 	= required_param('id', PARAM_INT);
		$pagenote->comment 	= required_param('comment', PARAM_CLEANHTML);
		
		$return = icontent_ajax_editnote($pagenote, $icontent);
	break;
	
	case 'replynote':
		require_capability('mod/icontent:replynotes', $context);
		$pagenote = new stdClass;
		$pagenote->parent	= required_param('parent', PARAM_INT);
		$pagenote->cmid 	= required_param('id', PARAM_INT);
		$pagenote->comment = required_param('comment', PARAM_CLEANHTML);
		
		$return = icontent_ajax_replynote($pagenote, $icontent);
		break;
		
	case 'saveattempt':
		require_capability('mod/icontent:view', $context);
		$formdata = required_param('formdata', PARAM_RAW);
		$return = icontent_ajax_saveattempt($formdata, $cm, $icontent);
		break;
}

echo json_encode($return);
die;
