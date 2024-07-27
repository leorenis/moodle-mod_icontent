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
use mod_icontent\question\icontent_question_options;

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or.
$n = optional_param('n', 0, PARAM_INT);  // The icontent instance ID.
$pageid = optional_param('pageid', 0, PARAM_INT); // Chapter ID.
$action = optional_param('action', '', PARAM_BOOL);

$sort = optional_param('sort', '', PARAM_RAW);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', ICONTENT_PER_PAGE, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($n) {
    $icontent = $DB->get_record('icontent', ['id' => $n], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $icontent->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('icontent', $icontent->id, $course->id, false, MUST_EXIST);
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

if ($action) {
    // Receives values.
    $questions = optional_param_array('question', [], PARAM_RAW);
    // Save values.
    if (icontent_add_questionpage($questions, $pageid, $cm->id)) {
        $urlredirect = new moodle_url('/mod/icontent/view.php', ['id' => $cm->id, 'pageid' => $pageid]);
        redirect($urlredirect, get_string('msgaddquestionpage', 'mod_icontent'));
    }
}

// Output starts here.
echo $OUTPUT->header();
echo $OUTPUT->heading($icontent->name. ": ". get_string('addquestion', 'mod_icontent'));


// Get info.
$sort = icontent_check_value_sort($sort);

// 20240107 Added to fix ticket 1147 and 1158.
$qcids = \qbank_managecategories\helper::get_categories_for_contexts(
    $coursecontext,
    $sortorder = 'parent, sortorder, name ASC',
    $top = false
);
foreach ($qcids as $qcid) {
    $questioncategoryid = $qcid->id;
}

$questions = icontent_question_options::icontent_get_questions_of_questionbank($coursecontext,
    $questioncategoryid,
    $sort,
    $page,
    $perpage
);
$tquestions = icontent_count_questions_of_questionbank($coursecontext);
// 20240107 Added info but the text needs more info.
echo 'Current $questioncategoryid is: '.$questioncategoryid.' ';
echo get_string('totalquestioncount', 'icontent', $tquestions);
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
    get_string('question'),
    get_string('question').' ID',
    get_string('category'),
    get_string('context'),
    get_string('course'),

    get_string('status'),
    get_string('version'),
    get_string('createdby', 'question'),
    get_string('commentplural', 'qbank_comment'),
    get_string('discrimination_index', 'qbank_statistics'),
    get_string('facility_index', 'qbank_statistics'),
    get_string('discriminative_efficiency', 'qbank_statistics'),
    get_string('questionusage', 'qbank_usage'),
    get_string('questionlastused', 'qbank_usage'),
    get_string('modifiedby', 'qbank_viewcreator'),
];

if ($questions) {
    foreach ($questions as $question) {
        $checked = isset($qtscurrentpage[$question->qid]) ? ['checked' => 'checked'] : [];
        $disabled = $answerscurrentpage ? ['disabled' => 'disabled'] : [];
        $checkbox = html_writer::empty_tag('input', ['type' => 'checkbox',
            'name' => 'question[]',
            'value' => $question->qid,
            'id' => 'idcheck'.$question->qid] + $checked + $disabled);
        $qtype = html_writer::empty_tag('img', ['src' => $OUTPUT->image_url('q/'.$question->qqtype, 'mod_icontent'),
            'class' => 'smallicon', 'alt' => get_string($question->qqtype, 'mod_icontent'),
            'title' => get_string($question->qqtype, 'mod_icontent')]
        );
        $qname = html_writer::label($question->qname, 'idcheck'.$question->qid);

        // These users must exist or you will get an error.
        $createdby = icontent_get_user_by_id($question->qcreatedby);
        $modifiedby = icontent_get_user_by_id($question->qmodifiedby);

        // This needs a LOT more work to make it look more like the page you see in a course Question Bank.
        $table->data[] = [
            $checkbox,
            $qtype,
            $question->qname,
            $question->qvquestionid,
            $question->qbequestioncategoryid,
            $question->qccontextid,
            $course->id,
            $question->qvstatus,
            $question->qvversion,


            $createdby->firstname.' '.$createdby->lastname.
                '<br>'.date(get_config('mod_icontent', 'dateformat'), $question->qtimecreated),

            // Need Comments, Needs Checking?, Facility index, Discriminative efficiency, and Usage placed here.
            get_string('commentplural', 'qbank_comment'),
            get_string('discrimination_index', 'qbank_statistics'),
            get_string('facility_index', 'qbank_statistics'),
            get_string('discriminative_efficiency', 'qbank_statistics'),
            get_string('questionusage', 'qbank_usage'),
            get_string('questionlastused', 'qbank_usage'),

            $modifiedby->firstname.' '.$modifiedby->lastname.
                '<br>'.date(get_config('mod_icontent', 'dateformat'), $question->qtimemodified),
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

// 20240107 Create a link back to where we came from in case we want to cancel.
$url2 = new moodle_url('/mod/icontent/view.php',
    [
        'id' => $id,
        'pageid' => $pageid,
    ]
);
// 20240107 Added two buttons, Add and Cancel..
echo '<input class="btn btn-primary"
    style="border-radius: 8px"
    name="button"
    onClick="return clClick()"
    type="submit" value="'
    .get_string('add')
    .'"> <a href="'
    .$url2
    .'" class="btn btn-secondary"  style="border-radius: 8px">'
    .get_string('cancel')
    .'</a>';
echo html_writer::end_tag('form');

// Finish the page.
echo $OUTPUT->footer();
