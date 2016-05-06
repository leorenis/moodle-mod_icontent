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
 * Prints a particular instance of icontent
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_icontent
 * @copyright  2015 Leo Renis Santos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace icontent with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // ... icontent instance ID - it should be named as the first character of the module.
$pageid = optional_param('pageid', 0, PARAM_INT); // Chapter ID

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

require_login($course, true, $cm);
require_sesskey();
$context = context_module::instance($cm->id);

// Log this request.
$event = \mod_icontent\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $icontent);
$event->trigger();

// Print the page header.
$PAGE->set_url('/mod/icontent/addquestionpage.php', array('id' => $cm->id));
$PAGE->set_title(format_string($icontent->name));
$PAGE->set_heading(format_string($course->fullname));

// Output starts here.
echo $OUTPUT->header();

// Replace the following lines with you own code.
echo $OUTPUT->heading($icontent->name. ": ". get_string('addquestion', 'mod_icontent'));

echo "<pre>"; var_dump($context->get_course_context(true)->id); echo "</pre>";
// Finish the page.
echo $OUTPUT->footer();