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
 * This creates a one page listing of all the iContent activities in the course.
 *
 *
 * @package    mod_icontent
 * @copyright  2015 Leo Renis Santos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace icontent with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');

$id = required_param('id', PARAM_INT); // Course.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

// Trigger course module instance list event.
$params = [
    'context' => context_course::instance($course->id),
];
$event = \mod_icontent\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

// Print the header.
$strname = get_string('modulenameplural', 'mod_icontent');
$PAGE->set_url('/mod/icontent/index.php', ['id' => $id]);
$PAGE->navbar->add($strname);
$PAGE->set_title("$course->shortname: $strname");
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading($strname);

if (! $icontents = get_all_instances_in_course('icontent', $course)) {
    notice(get_string('noicontents', 'icontent'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

// Sections.
$usesections = course_format_uses_sections($course->format);
if ($usesections) {
    $modinfo = get_fast_modinfo($course);
    $sections = $modinfo->get_section_info_all();
}

$timenow = time();

// Table data.
$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

$table->head = [];
$table->align = [];
if ($usesections) {
    // Add column heading based on the course format. e.g. Week, Topic.
    $table->head[] = get_string('sectionname', 'format_' . $course->format);
    $table->align[] = 'left';
}
// Add activity Name, activity Description, and Page, headings.
$table->head[] = get_string('name');
$table->align[] = 'left';
$table->head[] = get_string('description');
$table->align[] = 'left';

// Needs to also display the question count.
// Display the Pages col to everyone.
$table->head[] = get_string('pages', 'icontent');
$table->align[] = 'left';

// Display the Notes col to everyone.
$table->head[] = get_string('notes', 'icontent');
$table->align[] = 'left';

// Display the Question col to everyone.
$table->head[] = get_string('questions', 'icontent');
$table->align[] = 'left';

$modinfo = get_fast_modinfo($course);
$currentsection = '';
$i = 0;

foreach ($icontents as $icontent) {
    $context = context_module::instance($icontent->coursemodule);

    // Section.
    $printsection = '';
    if ($icontent->section !== $currentsection) {
        if ($icontent->section) {
            $printsection = get_section_name($course, $sections[$icontent->section]);
        }
        if ($currentsection !== '') {
            $table->data[$i] = 'hr';
            $i ++;
        }
        $currentsection = $icontent->section;
    }
    if ($usesections) {
        $table->data[$i][] = $printsection;
    }

    // Link.
    $icontentname = format_string($icontent->name, true, ['context' => $context]);
    if (! $icontent->visible) {
        // Show dimmed if the mod is hidden.
        $url = new moodle_url('view.php', ['id' => $icontent->coursemodule]);
        $table->data[$i][] = '<a class="dimmed" href="'.$url->out(false).'">'.$icontentname.'</a>';
    } else {
        // Show normal if the mod is visible.
        $url = new moodle_url('view.php', ['id' => $icontent->coursemodule]);
        $table->data[$i][] = '<a href="'.$url->out(false).'">'.$icontentname. '</a>';
    }

    // Description.
    $table->data[$i][] = format_text($icontent->intro, $icontent->introformat);

    // Read count of pages in each iContent activity.
    $pages = count(icontent_preload_pages($icontent));
    $notes = 0;
    $doubts = 0;
 
    // Need to change this to a page/question count.
    //$questioncount = results::icontent_count_entries($icontent);
    //$questioncount = icontent_count_entries($icontent);
    $url = new moodle_url('view.php', ['id' => $icontent->coursemodule,]);
    $table->data[$i][] = '<a href="'.$url->out(false).'">'
        .get_string('pagecount', 'icontent', $pages).'</a>';


    // Need to change this to a Notes count.
    //$notescount = results::icontent_count_entries($icontent);
    //$notescount = icontent_count_entries($icontent);
    $url = new moodle_url('view.php', ['id' => $icontent->coursemodule,]);
    $table->data[$i][] = '<a href="'.$url->out(false).'">'
        .get_string('note', 'icontent', $notes).'</a>';


    // Need to change this to a Questions(doubts) count.
    //$doubtscount = results::icontent_count_entries($icontent);
    //$doubtscount = icontent_count_entries($icontent);
    $url = new moodle_url('view.php', ['id' => $icontent->coursemodule,]);
    $table->data[$i][] = '<a href="'.$url->out(false).'">'
        .get_string('doubt', 'icontent', $doubts).'</a>';

    $i ++;
}

echo html_writer::table($table);

echo $OUTPUT->footer();
