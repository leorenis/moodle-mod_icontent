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
 * Edit icontent page
 *
 * @package    mod_icontent
 * @copyright  2005-2016 Leo Santos {@link http://facebook.com/leorenisJC}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/edit_form.php');

$cmid       = required_param('cmid', PARAM_INT);  // Content Course Module ID
$pageid  	= optional_param('id', 0, PARAM_INT); // Page ID
$pagenum    = optional_param('pagenum', 0, PARAM_INT);

$cm = get_coursemodule_from_id('icontent', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$icontent = $DB->get_record('icontent', array('id'=>$cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/icontent:edit', $context);

// Log this request.
$event = \mod_icontent\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $icontent);
$event->trigger();

$PAGE->set_url('/mod/icontent/edit.php', array('cmid'=>$cmid, 'id'=>$pageid, 'pagenum'=>$pagenum));
$PAGE->set_pagelayout('admin'); // TODO: Something. This is a bloody hack!

if ($pageid) {
    $page = $DB->get_record('icontent_pages', array('id'=>$pageid, 'icontentid'=>$icontent->id), '*', MUST_EXIST);
} else {
    $page = new stdClass();
    $page->id    		= null;
    $page->pagenum		= $pagenum;
}
$page->icontentid	= $icontent->id;
$page->cmid			= $cm->id;

$pageicontentoptions = array('noclean'=>true, 'subdirs'=>true, 'maxfiles'=>-1, 'maxbytes'=>0, 'context'=>$context);
$page = file_prepare_standard_editor($page, 'pageicontent', $pageicontentoptions, $context, 'mod_icontent', 'page', $page->id);

$mform = new icontent_pages_edit_form(null, array('page'=>$page, 'pageicontentoptions'=>$pageicontentoptions));

// If data submitted, then process and store.
if ($mform->is_cancelled()) {
    if (empty($page->id)) {
        redirect("view.php?id=$cm->id");
    } else {
        redirect("view.php?id=$cm->id&pageid=$page->id");
    }

} else if ($data = $mform->get_data()){
	
	// update
	if ($data->id) {
		// store the files
        $data->timemodified = time();
		$data = file_postupdate_standard_editor($data, 'pageicontent', $pageicontentoptions, $context, 'mod_icontent', 'page', $data->id);
        $DB->update_record('icontent_pages', $data);
		// Saving file bgarea in the filemanager
		file_save_draft_area_files($data->bgimage, $context->id, 'mod_icontent', 'bgpage', $data->id, array('subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 1));
		// Get page
        $page = $DB->get_record('icontent_pages', array('id' => $data->id));
		// Set log
        \mod_icontent\event\page_updated::create_from_page($icontent, $context, $page)->trigger();
		// redirect
		redirect("view.php?id=$cm->id&pageid=$data->id", get_string('msgsucess','icontent'));
		
	}else{
		$data->pageicontent = '';         		// updated later
		$data->pageicontentformat = FORMAT_HTML; // updated later
		$data->id = $DB->insert_record('icontent_pages', $data);
		$data = file_postupdate_standard_editor($data, 'pageicontent', $pageicontentoptions, $context, 'mod_icontent', 'page', $data->id);
   		$DB->update_record('icontent_pages', $data);
		// Saving file bgarea in the filemanager
		file_save_draft_area_files($data->bgimage, $context->id, 'mod_icontent', 'bgpage', $data->id, array('subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 1));
		// fix structure
		icontent_preload_pages($icontent);
		// Get page
        $page = $DB->get_record('icontent_pages', array('id' => $data->id));
		// Set log
		\mod_icontent\event\page_created::create_from_page($icontent, $context, $page)->trigger();
		redirect("view.php?id=$cm->id&pageid=$data->id", get_string('msgsucess','icontent'));	// redirect
	}
}
// Otherwise fill and print the form.
$PAGE->set_title($icontent->name);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($icontent->name);

$mform->display();

echo $OUTPUT->footer();