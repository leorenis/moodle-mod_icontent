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
 * Edit icontent page.
 *
 * @package    mod_icontent
 * @copyright  2005-2016 Leo Santos {@link http://facebook.com/leorenisJC}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_icontent\local\icontent_info;

require(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/edit_form.php');

$cmid = required_param('cmid', PARAM_INT);  // Content Course Module ID.
$pageid = optional_param('id', 0, PARAM_INT); // Page ID.
$pagenum = optional_param('pagenum', 0, PARAM_INT);

$cm = get_coursemodule_from_id('icontent', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/icontent:edit', $context);

// Log this request.
$params = [
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
];
$event = \mod_icontent\event\course_module_viewed::create($params);
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $icontent);
$event->trigger();

$PAGE->set_url('/mod/icontent/edit.php', ['cmid' => $cmid, 'id' => $pageid, 'pagenum' => $pagenum]);
$PAGE->set_pagelayout('admin'); // Not sure just what this does.

if ($pageid) {
    $page = $DB->get_record('icontent_pages', ['id' => $pageid, 'icontentid' => $icontent->id], '*', MUST_EXIST);
} else {
    $page = new stdClass();
    $page->id = null;
    $page->pagenum = $pagenum;
}
$page->icontentid = $icontent->id;
$page->cmid = $cm->id;
$maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes);
$pageicontentoptions = [
    'noclean' => true,
    'subdirs' => true,
    'maxfiles' => -1,
    'maxbytes' => $maxbytes,
    'context' => $context,
];
$page = file_prepare_standard_editor($page, 'pageicontent', $pageicontentoptions, $context, 'mod_icontent', 'page', $page->id);

$mform = new icontent_pages_edit_form(null, ['page' => $page, 'pageicontentoptions' => $pageicontentoptions]);

// If data submitted, then process and store.
if ($mform->is_cancelled()) {
    if (empty($page->id)) {
        redirect("view.php?id=$cm->id");
    } else {
        redirect("view.php?id=$cm->id&pageid=$page->id");
    }

} else if ($data = $mform->get_data()) {
    // Update.
    if ($data->id) {
        // Store the files.
        $data->timemodified = time();
        $data = file_postupdate_standard_editor($data,
            'pageicontent',
            $pageicontentoptions,
            $context,
            'mod_icontent',
            'page',
            $data->id
        );
        $DB->update_record('icontent_pages', $data);
        // Saving file bgarea in the filemanager.
        file_save_draft_area_files($data->bgimage, $context->id,
            'mod_icontent',
            'bgpage',
            $data->id,
            ['subdirs' => 0,
                'maxbytes' => $maxbytes,
                'maxfiles' => 1,
            ]
        );
        // Get page.
        $page = $DB->get_record('icontent_pages', ['id' => $data->id]);
        // Set log.
        \mod_icontent\event\page_updated::create_from_page($icontent, $context, $page)->trigger();
        // Redirect.
        redirect("view.php?id=$cm->id&pageid=$data->id", get_string('msgsucess', 'icontent'));
    } else {
        $data->pageicontent = ''; // Updated later.
        $data->pageicontentformat = FORMAT_HTML; // Updated later.
        $data->id = $DB->insert_record('icontent_pages', $data);
        $data = file_postupdate_standard_editor($data,
            'pageicontent',
            $pageicontentoptions,
            $context,
            'mod_icontent',
            'page',
            $data->id
        );
        $DB->update_record('icontent_pages', $data);
        // Saving file bgarea in the filemanager.
        file_save_draft_area_files($data->bgimage,
            $context->id,
            'mod_icontent',
            'bgpage',
            $data->id,
            ['subdirs' => 0,
                'maxbytes' => $maxbytes,
                'maxfiles' => 1,
            ]
        );
        // Fix structure.
        icontent_info::icontent_preload_pages($icontent);
        // Get page.
        $page = $DB->get_record('icontent_pages', ['id' => $data->id]);
        // Set log.
        \mod_icontent\event\page_created::create_from_page($icontent, $context, $page)->trigger();
        redirect("view.php?id=$cm->id&pageid=$data->id", get_string('msgsucess', 'icontent')); // Redirect.
    }
}
// Otherwise fill and print the form.
$PAGE->set_title($icontent->name);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($icontent->name);

$mform->display();

echo $OUTPUT->footer();
