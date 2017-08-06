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
 * @copyright  2016 Leo Renis Santos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or.
$n  = optional_param('n', 0, PARAM_INT);  // ... icontent instance ID - it should be named as the first character of the module.
$pageid = optional_param('pageid', 0, PARAM_INT); // Chapter ID.
$action = optional_param('action', '', PARAM_BOOL);

$sort = optional_param('sort', '', PARAM_RAW);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', ICONTENT_PER_PAGE, PARAM_INT);

if ($id) {
    $cm         = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $icontent   = $DB->get_record('icontent', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $icontent  = $DB->get_record('icontent', array('id' => $n), '*', MUST_EXIST);
    $course    = $DB->get_record('course', array('id' => $icontent->course), '*', MUST_EXIST);
    $cm        = get_coursemodule_from_instance('icontent', $icontent->id, $course->id, false, MUST_EXIST);
} else {
    print_error('You must specify a course_module ID or an instance ID');
}
if(!$pageid) {
    print_error('You must specify a page ID');
}
// Require login.
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$coursecontext = $context->get_course_context(true)->id;
require_capability('mod/icontent:newquestion', $context);
// Log event.
\mod_icontent\event\question_page_viewed::create_from_question_page($icontent, $context, $pageid)->trigger();
// Print the page header.
$PAGE->set_url('/mod/icontent/addquestionpage.php', array('id' => $cm->id, 'pageid'=>$pageid));
$PAGE->set_title(format_string($icontent->name));
$PAGE->set_heading(format_string($course->fullname));
// CSS
$PAGE->requires->css(new moodle_url($CFG->wwwroot.'/mod/icontent/styles/font-awesome-4.6.2/css/font-awesome.min.css'));
$url = new moodle_url('/mod/icontent/addquestionpage.php', array('id'=>$id, 'pageid'=> $pageid, 'page' => $page, 'perpage' => $perpage));;
// Output starts here.
echo $OUTPUT->header();
// Replace the following lines with you own code.
echo $OUTPUT->heading($icontent->name. ": ". get_string('addquestion', 'mod_icontent'));

if ($action){
    // Receives values
    $questions = optional_param_array('question', array(), PARAM_RAW);
    // Save values
    if(icontent_add_questionpage($questions, $pageid, $cm->id)){
        $urlredirect = new moodle_url('/mod/icontent/view.php', array('id'=>$cm->id, 'pageid'=>$pageid));
        redirect($urlredirect, get_string('msgaddquestionpage', 'mod_icontent'));
    }
}
// Get info.
$sort = icontent_check_value_sort($sort);
$questions = icontent_get_questions_of_questionbank($coursecontext, $sort, $page, $perpage);
$tquestions = icontent_count_questions_of_questionbank($coursecontext);
$qtscurrentpage = icontent_get_questions_of_currentpage($pageid, $cm->id);
$answerscurrentpage = icontent_checks_answers_of_currentpage($pageid, $cm->id);
// Make table questions.
$table = new html_table();
$table->id = "categoryquestions";
$table->attributes = array('class'=>'icontentquestions');
$table->colclasses = array('checkbox', 'qtype', 'questionname', 'previewaction', 'creatorname', 'modifiername');
$table->head  = array(null, get_string('type', 'mod_icontent'), get_string('question'), get_string('createdby', 'mod_icontent'), get_string('lastmodifiedby', 'mod_icontent'));
if($questions) foreach ($questions as $question){
    $checked = isset($qtscurrentpage[$question->id]) ? array('checked'=>'checked') : array();
    $disabled = $answerscurrentpage ? array('disabled'=>'disabled') : array();
    $checkbox = html_writer::empty_tag('input', array('type'=>'checkbox', 'name'=>'question[]', 'value'=>$question->id, 'id'=>'idcheck'.$question->id) + $checked + $disabled);
    $qtype = html_writer::empty_tag('img', array('src'=> $OUTPUT->pix_url('q/'.$question->qtype, 'mod_icontent'), 'class'=> 'smallicon', 'alt'=> get_string($question->qtype, 'mod_icontent'), 'title'=> get_string($question->qtype, 'mod_icontent')));
    $qname = html_writer::label($question->name, 'idcheck'.$question->id);
    $createdby = icontent_get_user_by_id($question->createdby);
    $modifiedby = icontent_get_user_by_id($question->modifiedby);
    $table->data[] = array($checkbox, $qtype, $qname, $createdby->firstname , $modifiedby->firstname);
}
else {
    echo html_writer::div(get_string('emptyquestionbank', 'mod_icontent'), 'alert alert-warning');
    echo $OUTPUT->footer();
    exit;
}
// Show elements HTML.
echo html_writer::div(get_string('infomaxquestionperpage', 'mod_icontent'), 'alert alert-info');
echo $answerscurrentpage ? html_writer::div(get_string('msgstatusdisplay', 'mod_icontent'), 'alert alert-warning') : null;
echo html_writer::start_tag('form', array('action'=> new moodle_url('addquestionpage.php', array('id'=>$id, 'pageid'=>$pageid)), 'method'=>'POST'));
echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'action', 'value'=>true));
echo html_writer::start_div('categoryquestionscontainer');
echo html_writer::table($table);
echo $OUTPUT->paging_bar($tquestions, $page, $perpage, $url);
echo html_writer::end_div();
echo html_writer::empty_tag('input', array('type'=>'submit', 'value'=>get_string('add')) + $disabled);
echo html_writer::end_tag('form');

// Finish the page.
echo $OUTPUT->footer();