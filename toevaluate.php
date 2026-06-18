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
 * To evaluate user icontent page.
 *
 * @package    mod_icontent
 * @copyright  2016-2015 Leo Santos {@link http://github.com/leorenis}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.
$userid = required_param('userid', PARAM_INT); // Page note ID.
$action = optional_param('action', 0, PARAM_BOOL); // Action.
$status = optional_param('status', ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE, PARAM_ALPHA); // Status.

$cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);
$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('mod/icontent:manage', $context);
// Page setting.
$PAGE->set_url('/mod/icontent/toevaluate.php', ['id' => $cm->id, 'action' => $action, 'userid' => $userid, 'sesskey' => sesskey()]);
// Header and strings.
$PAGE->set_title($icontent->name);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('results', 'mod_icontent'))->add(get_string('manualreview', 'mod_icontent'))->add(fullname($user));
// Get total questions by instance.
$tquestinstance = icontent_get_totalquestions_by_instance($cm->id);
// Check action.
if ($action) {
    // Receives values.
    $questions = optional_param_array('question', [], PARAM_RAW);
    $questioncomments = optional_param_array('questioncomment', [], PARAM_RAW);
    $questioncommentformats = optional_param_array('questioncommentformat', [], PARAM_INT);
    $i = 0;
    $update = false;
    if ($questions) {
        $attemptids = [];
        foreach (array_keys($questions) as $qname) {
            [$strname, $answerid] = explode('-', $qname);
            $attemptids[] = (int)$answerid;
        }

        $maxgradesbyattempt = [];
        if (!empty($attemptids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED);
            $sql = "SELECT qa.id,
                           pq.maxmark,
                           q.defaultmark
                      FROM {icontent_question_attempts} qa
                INNER JOIN {icontent_pages_questions} pq
                        ON qa.pagesquestionsid = pq.id
                INNER JOIN {question} q
                        ON qa.questionid = q.id
                     WHERE qa.id $insql
                       AND qa.userid = :userid
                       AND qa.cmid = :cmid";
            $params = $inparams + ['userid' => $user->id, 'cmid' => $cm->id];
            $records = $DB->get_records_sql($sql, $params);
            foreach ($records as $record) {
                $maxmark = (float)$record->maxmark;
                if ($maxmark <= 0) {
                    $maxmark = (float)$record->defaultmark;
                }
                $maxgradesbyattempt[(int)$record->id] = $maxmark > 0 ? $maxmark : 1.0;
            }
        }

        foreach ($questions as $qname => $qvalue) {
            [$strname, $answerid] = explode('-', $qname);
            $answerid = (int)$answerid;
            $maxgrade = $maxgradesbyattempt[$answerid] ?? 1.0;
            $qvalue = (float)str_replace(',', '.', (string)$qvalue);
            $qvalue = max(0.0, min($qvalue, $maxgrade));
            $commentkey = 'attemptid-' . $answerid;
            $attempt = new stdClass();
            $attempt->id = $answerid;
            $attempt->fraction = $qvalue;
            $attempt->rightanswer = ICONTENT_QTYPE_ESSAY_STATUS_VALUED;
            $attempt->reviewercomment = $questioncomments[$commentkey] ?? '';
            $attempt->reviewercommentformat = $questioncommentformats[$commentkey] ?? FORMAT_HTML;
            // Save values.
            $update = icontent_update_question_attempts($attempt);
            $i++;
        }
    }
    if ($update) {
        // Update grade.
        icontent_set_grade_item($icontent, $cm->id, $user->id);
        // Log event.
        \mod_icontent\event\question_toevaluate_created::create_from_question_toevaluate($icontent, $context, $user)->trigger();
        redirect(
            new moodle_url(
                '/mod/icontent/grading.php',
                [
                'id' => $cm->id,
                'action' => 'grading',
                ]
            ),
            get_string('msgsucessevaluate', 'mod_icontent', $i)
        );
    }
}

$qopenanswers = icontent_get_questions_and_open_answers_by_user($user->id, $cm->id, $status);
if (!$qopenanswers) {
    redirect(
        new moodle_url(
            '/mod/icontent/grading.php',
            [
            'id' => $cm->id,
            'action' => $action,
            ]
        ),
        get_string('norecordsfound', 'mod_icontent')
    );
}

// Make page.
echo $OUTPUT->header();
echo $OUTPUT->heading($icontent->name);
echo $OUTPUT->heading(get_string('manualreviewofparticipant', 'mod_icontent', fullname($user)), 3);
$preferredformat = editors_get_preferred_format($context);
$preferrededitor = editors_get_preferred_editor($preferredformat);
echo html_writer::start_tag('form', ['method' => 'post']);
foreach ($qopenanswers as $qopenanswer) {
    $fieldname = 'question[attemptid-' . $qopenanswer->id . ']';
    $fieldid =
        'idquestion-' . $qopenanswer->questionid . '_pqid-' .
        $qopenanswer->pagesquestionsid . '_' . ICONTENT_QTYPE_ESSAY;
    $commentfieldname = 'questioncomment[attemptid-' . $qopenanswer->id . ']';
    $commentformatname = 'questioncommentformat[attemptid-' . $qopenanswer->id . ']';
    $commentfieldid = 'idcomment-' . $qopenanswer->id;
    $questionblockid = 'idq' . $qopenanswer->questionid;
    $fraction = (
        $status === ICONTENT_QTYPE_ESSAY_STATUS_VALUED
        || (string)$qopenanswer->qtype === ICONTENT_QTYPE_ESSAYAUTOGRADE
    ) ? $qopenanswer->fraction : ''; // Check status.
    $reviewercomment = (string)($qopenanswer->reviewercomment ?? '');
    $reviewercommentformat = (int)($qopenanswer->reviewercommentformat ?? $preferredformat);
    $maxgrade = (float)($qopenanswer->maxmark ?? 0);
    if ($maxgrade <= 0) {
        $maxgrade = (float)($qopenanswer->defaultmark ?? 0);
    }
    if ($maxgrade <= 0) {
        $maxgrade = 1.0;
    }
    // Get page.
    $page = $DB->get_record('icontent_pages', ['id' => $qopenanswer->pageid], 'id, title, pagenum', MUST_EXIST);
    $qanswercontent = icontent_render_manual_review_answer($qopenanswer, (int)$cm->id);
    if ($preferrededitor) {
        $preferrededitor->use_editor($commentfieldid, [
            'context' => $context,
            'autosave' => false,
        ]);
    }
    $templatecontext = [
        'questionblockid' => $questionblockid,
        'questionblockclass' => 'que manualgraded ' . ICONTENT_QTYPE_ESSAY,
        'attempttitle' => get_string('strattempttitle', 'mod_icontent', $page),
        'questiontexthtml' => $qopenanswer->questiontext,
        'answerhtml' => $qanswercontent,
        'commentlabel' => get_string('comments', 'mod_icontent'),
        'commentfieldid' => $commentfieldid,
        'commentfieldname' => $commentfieldname,
        'reviewercomment' => $reviewercomment,
        'commentformatname' => $commentformatname,
        'reviewercommentformat' => $reviewercommentformat,
        'gradenoun' => get_string('gradenoun', 'icontent'),
        'fieldid' => $fieldid,
        'fieldname' => $fieldname,
        'fraction' => $fraction,
        'maxgrade' => $maxgrade,
        'maxgradeformatted' => number_format($maxgrade, 2),
        'strmaxgrade' => get_string('strmaxgrade', 'mod_icontent'),
    ];
    echo $OUTPUT->render_from_template('mod_icontent/manual_review_question_card', $templatecontext);
}
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => true]);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('savechanges')]);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
