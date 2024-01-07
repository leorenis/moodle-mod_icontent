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
 * Prints a particular instance of icontent.
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
require_once(__DIR__ .'/../../lib/questionlib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or.
$n = optional_param('n', 0, PARAM_INT);  // The icontent instance ID.
$pageid = optional_param('pageid', 0, PARAM_INT); // Chapter ID.
$action = optional_param('action', '', PARAM_BOOL);

$sort = optional_param('sort', '', PARAM_RAW);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', ICONTENT_PER_PAGE, PARAM_INT);

//$debug = [];
//$debug['In the addquestionpage.php file'] = '==========================';
//$debug['CP A $id: '] = $id;
//$debug['CP A $n: '] = $n;
//$debug['CP A $pageid: '] = $pageid;
//$debug['CP A $action: '] = $action;
//$debug['CP A $sort: '] = $sort;
//$debug['CP A $page: '] = $page;
//$debug['CP A $perpage: '] = $perpage;



if ($id) {
    $cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);

//$debug['CP ID $cm: '] = $cm;
//$debug['CP ID $course: '] = $course;
//$debug['CP ID $icontent: '] = $icontent;

} else if ($n) {
    $icontent = $DB->get_record('icontent', ['id' => $n], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $icontent->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('icontent', $icontent->id, $course->id, false, MUST_EXIST);

//$debug['CP N $cm: '] = $cm;
//$debug['CP N $course: '] = $course;
//$debug['CP N $icontent: '] = $icontent;

} else {
    throw new moodle_exception(get_string('incorrectmodule', 'icontent'));
}
if (!$pageid) {
    throw new moodle_exception(get_string('incorrectpage', 'icontent'));
}
// Require login.
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$coursecontext = $context->get_course_context(true)->id;
require_capability('mod/icontent:newquestion', $context);
// Log event.
\mod_icontent\event\question_page_viewed::create_from_question_page($icontent, $context, $pageid)->trigger();

//$debug['CP B $context: '] = $context;
//$debug['CP B $coursecontext: '] = $coursecontext;


// Print the page header.
$PAGE->set_url('/mod/icontent/addquestionpage.php', ['id' => $cm->id, 'pageid' => $pageid]);
$PAGE->set_title(format_string($icontent->name));
$PAGE->set_heading(format_string($course->fullname));
// CSS.
$PAGE->requires->css(new moodle_url($CFG->wwwroot.'/mod/icontent/styles/font-awesome-4.6.2/css/font-awesome.min.css'));
$url = new moodle_url('/mod/icontent/addquestionpage.php',
    [
        'id' => $id,
        'pageid' => $pageid,
        'page' => $page,
        'perpage' => $perpage,
    ]
);
// Output starts here.
echo $OUTPUT->header();

//echo 'test for $context';
//print_object($context);
//$temp = question_context_has_any_questions($context);
//print_object($temp);
//echo 'finished with $context test';

// Replace the following lines with you own code.
echo $OUTPUT->heading($icontent->name. ": ". get_string('addquestion', 'mod_icontent'));

if ($action) {
    // Receives values.
    $questions = optional_param_array('question', [], PARAM_RAW);
    // Save values.
    if (icontent_add_questionpage($questions, $pageid, $cm->id)) {
        $urlredirect = new moodle_url('/mod/icontent/view.php', ['id' => $cm->id, 'pageid' => $pageid]);
        redirect($urlredirect, get_string('msgaddquestionpage', 'mod_icontent'));
    }
}
// Get info.
$sort = icontent_check_value_sort($sort);



$debug2 = [];
$debug2['In the addquestionpage.php file'] = '===addquestionpage===';
//$testing1 = get_categories_for_contexts($coursecontext, $sortorder = 'parent, sortorder, name ASC', $top = false);
//$testing2 = \qbank_managecategories\helper::get_categories_for_contexts($coursecontext, $sortorder, $top);
$testing2 = \qbank_managecategories\helper::get_categories_for_contexts($coursecontext, $sortorder = 'parent, sortorder, name ASC', $top = false);
$debug2['CP TT1 $testing2: '] = $testing2;
foreach($testing2 AS $test2) {
    $debug2['CP TT2In the foreach loop'] = '===foreach===';

    $questioncategoryid = $test2->id;
        $debug2['CP TT3 $questioncategoryid'] = $questioncategoryid;
}

//print_object($debug2);

// I suspect I will need to do some sorting/filtering, either here, or just below for current page.
// Really need to have the questions sorted by the actual question bank category.
// The next line of code is currently getting an error for found more than one record.
$questions = icontent_get_questions_of_questionbank($coursecontext, $sort, $page, $perpage, $questioncategoryid);
$tquestions = icontent_count_questions_of_questionbank($coursecontext);
$qtscurrentpage = icontent_get_questions_of_currentpage($pageid, $cm->id);
$answerscurrentpage = icontent_checks_answers_of_currentpage($pageid, $cm->id);
// Make table questions.
$table = new html_table();
$table->id = "categoryquestions";
$table->attributes = ['class' => 'icontentquestions'];
$table->colclasses = ['checkbox', 'qtype', 'questionname', 'previewaction', 'creatorname', 'modifiername'];
$table->head  = [
    null,
    get_string('type', 'mod_icontent'),
    get_string('question').'<br>Name',
    //get_string('questionid'),
    'Question'.'<br>'.'ID',
    get_string('question').'<br>'.get_string('status'),


    get_string('question').'<br>'.get_string('version'),
    get_string('context').'ID',
    get_string('course'),

    get_string('createdby', 'mod_icontent'),
    get_string('lastmodifiedby', 'mod_icontent'),
    'Category'.'<br>'.'ID',

];




//print_object($debug);


//print_object($context);
//print_object($coursecontext);
//print_object($questions);


if ($questions) {
    foreach ($questions as $question) {

//print_object($question);

        $checked = isset($qtscurrentpage[$question->qid]) ? ['checked' => 'checked'] : [];
        $disabled = $answerscurrentpage ? ['disabled' => 'disabled'] : [];
        $checkbox = html_writer::empty_tag('input', ['type' => 'checkbox',
            'name' => 'question[]',
            'value' => $question->qid,
            'id' => 'idcheck'.$question->qid] + $checked + $disabled);
        //$qtype = html_writer::empty_tag('img', ['src' => $OUTPUT->pix_url('q/'.$question->qtype, 'mod_icontent'),
        $qtype = html_writer::empty_tag('img', ['src' => $OUTPUT->image_url('q/'.$question->qqtype, 'mod_icontent'),
            'class' => 'smallicon', 'alt' => get_string($question->qqtype, 'mod_icontent'),
            'title' => get_string($question->qqtype, 'mod_icontent')]
        );
        $qname = html_writer::label($question->qname, 'idcheck'.$question->qid);
        //$qname = html_writer::label($question->name, 'idcheck'.$question->id);

        // These users must exist or you will get an error.
        $createdby = icontent_get_user_by_id($question->qcreatedby);
        $modifiedby = icontent_get_user_by_id($question->qmodifiedby);
//print_object($question->qcreatedby);
//print_object($modifiedby);

// This needs a LOT more work to make it look more like the page you see in a course Question Bank.
        $table->data[] = [
            $checkbox,
            $qtype,
            $question->qname,
            $question->qvquestionid,
            $question->qvstatus,
            $question->qvversion,
            $question->qccontextid,
            $course->id,
            $createdby->firstname.' '.$createdby->lastname.'<br>'.date(get_config('mod_icontent', 'dateformat'), $question->qtimecreated),
            $modifiedby->firstname.' '.$modifiedby->lastname.'<br>'.date(get_config('mod_icontent', 'dateformat'), $question->qtimemodified),

            $question->qbequestioncategoryid,

        ];
    }
} else {
                   
    echo html_writer::div(get_string('emptyquestionbank', 'mod_icontent'), 'alert alert-warning');
    echo $OUTPUT->footer();
    exit;
}
// Show elements HTML.
echo html_writer::div(get_string('infomaxquestionperpage', 'mod_icontent'), 'alert alert-info');
echo $answerscurrentpage ? html_writer::div(get_string('msgstatusdisplay', 'mod_icontent'), 'alert alert-warning') : null;
echo html_writer::start_tag('form',
    ['action' => new moodle_url('addquestionpage.php',
    ['id' => $id, 'pageid' => $pageid]),
    'method' => 'POST']
    );
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => true]);
echo html_writer::start_div('categoryquestionscontainer');
echo html_writer::table($table);
echo $OUTPUT->paging_bar($tquestions, $page, $perpage, $url);
echo html_writer::end_div();
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('add'), 'class' => 'btn btn-primary'] + $disabled);
echo html_writer::end_tag('form');

//echo 'test start';
// Got this bit of code from moodledev/question/editlib.php about line 74 which then links to the next bit
// And got the question_categorylist bit of code from moodledev/lib/questionlib.php about line 1314
//QBEquestioncategoryid
//$recurse = true;
//echo 'test start ';
    // Get list of categories.
//    if ($recurse) {
        //$categorylist = question_categorylist($category->id);
//        $categorylist = question_categorylist($question->qbequestioncategoryid);
//    } else {
        //$categorylist = [$category->id];
//        $categorylist = [$question->qbequestioncategoryid];
//    }
//print_object($categorylist);
//echo 'test end';


// Finish the page.
echo $OUTPUT->footer();
