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
 * Results report landing page.
 *
 * @package    mod_icontent
 * @copyright  2024 AL Rachels <drachels@drachels.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

use mod_icontent\local\icontent_info;

global $DB, $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT); // Course Module ID.
$group = optional_param('group', 0, PARAM_INT); // Group filter.
$sort = optional_param('sort', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', ICONTENT_PER_PAGE, PARAM_INT);

$cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
/** @var \context $context */
require_capability('mod/icontent:grade', $context);

$PAGE->set_url('/mod/icontent/report.php', [
    'id' => $cm->id,
    'group' => $group,
    'sort' => $sort,
    'page' => $page,
    'perpage' => $perpage,
]);
$PAGE->set_pagelayout('incourse');

// Show the iContent TOC block while in Results mode for quick page navigation.
$pages = icontent_info::icontent_preload_pages($icontent);
$currenttocpage = icontent_get_startpagenum($icontent, $context);
$tocedit = has_capability('mod/icontent:edit', $context);
if (!empty($pages)) {
    icontent_add_fake_block($pages, $currenttocpage, $icontent, $cm, $tocedit);
}

// Respect selected/current group before pulling summary and table data.
$showgroupmenu = groups_get_activity_groupmode($cm) != NOGROUPS;
if ($showgroupmenu) {
    $currentgroup = groups_get_activity_group($cm, true);
    if ($currentgroup) {
        $group = $currentgroup;
    }
}

$PAGE->set_title($icontent->name);
$PAGE->set_heading($course->fullname);

$gradesurl = new moodle_url('/mod/icontent/grade.php', ['id' => $id, 'action' => 'overview', 'group' => $group]);
$manualreviewurl = new moodle_url('/mod/icontent/grading.php', ['id' => $id, 'action' => 'grading', 'group' => $group]);

$sort = icontent_check_value_sort($sort);
$attemptusers = icontent_count_attempts_users($cm->id, $group);
$reviewusers = icontent_count_attempts_users_with_open_answers($cm->id, ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE, $group);
$totalquestions = icontent_get_totalquestions_by_instance($cm->id);
$tquestinstance = icontent_get_totalmaxfraction_by_instance($cm->id);
if ($tquestinstance <= 0) {
    $tquestinstance = (float)$totalquestions;
}

// Build detailed grade table data inline (same source as grade.php).
$url = new moodle_url('/mod/icontent/report.php', [
    'id' => $id,
    'group' => $group,
    'sort' => $sort,
    'page' => $page,
    'perpage' => $perpage,
]);
$attemptsusers = icontent_get_attempts_users($cm->id, $sort, $page, $perpage, $group);
$tattemptsusers = icontent_count_attempts_users($cm->id, $group);

$table = new html_table();
$table->id = 'idtableattemptsusers';
$table->colclasses = ['fullname', 'answers', 'result', 'grades'];
$table->attributes = ['class' => 'table table-hover tableattemptsusers'];
$table->head  = [
    get_string('fullname'),
    get_string('answers', 'mod_icontent'),
    get_string('partialresult', 'mod_icontent'),
    get_string('gradingscale', 'mod_icontent', $icontent->grade),
];

if ($attemptsusers) {
    foreach ($attemptsusers as $attemptuser) {
        $picture = $OUTPUT->user_picture($attemptuser, ['size' => 35, 'class' => 'img-thumbnail pull-left']);
        $linkfirstname = html_writer::link(
            new moodle_url('/user/view.php', ['id' => $attemptuser->id, 'course' => $course->id]),
            $attemptuser->firstname . ' ' . $attemptuser->lastname,
            ['title' => $attemptuser->firstname, 'class' => 'lkfullname']
        );

        $stropenanswer = $attemptuser->totalopenanswers ? get_string(
            'stropenanswer',
            'mod_icontent',
            $attemptuser->totalopenanswers
        ) : '';

        $attemptmaxfraction = (float)($attemptuser->maxfraction ?? 0);
        if ($attemptmaxfraction <= 0) {
            $attemptmaxfraction = (float)$attemptuser->totalanswers;
        }

        $evaluate = new stdClass();
        $evaluate->fraction = number_format($attemptuser->sumfraction, 2);
        $evaluate->maxfraction = number_format($attemptmaxfraction, 2);
        $evaluate->percentage = $attemptmaxfraction > 0 ? round(($attemptuser->sumfraction * 100) / $attemptmaxfraction) : 0;
        $evaluate->openanswer = $stropenanswer;
        $evaluate->finalgrade = $tquestinstance > 0 ? ($attemptuser->sumfraction * $icontent->grade) / $tquestinstance : 0;
        $strevaluate = get_string('strtoevaluate', 'mod_icontent', $evaluate);

        $table->data[] = [
            $picture . $linkfirstname,
            $attemptuser->totalanswers,
            $strevaluate,
            number_format($evaluate->finalgrade, 2),
        ];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($icontent->name);
echo $OUTPUT->heading(get_string('reportoverview', 'mod_icontent'), 3);

echo html_writer::div(
    html_writer::link($gradesurl, get_string('grades'), ['class' => 'btn btn-primary mr-2']) .
    html_writer::link($manualreviewurl, get_string('manualreview', 'mod_icontent'), ['class' => 'btn btn-secondary']),
    'mb-3 icontent-results-mode-toggle'
);

if ($showgroupmenu) {
    $groupmenuurl = new moodle_url('/mod/icontent/report.php', [
        'id' => $id,
        'sort' => $sort,
        'page' => $page,
        'perpage' => $perpage,
    ]);
    echo groups_print_activity_menu($cm, $groupmenuurl, true);
}

echo html_writer::div(
    get_string('summaryattempts', 'mod_icontent') . ': ' . $attemptusers . ' | ' .
    get_string('manualreview', 'mod_icontent') . ': ' . $reviewusers . ' | ' .
    get_string('questions', 'mod_icontent') . ': ' . $totalquestions,
    'alert alert-info'
);

$summarycards = html_writer::div(
    html_writer::div(
        html_writer::tag('div', get_string('summaryattempts', 'mod_icontent'), ['class' => 'card-title']) .
        html_writer::tag('div', $attemptusers, ['class' => 'display-6']) .
        html_writer::tag('div', get_string('grades'), ['class' => 'text-muted']),
        'card-body'
    ),
    'card shadow-sm flex-fill'
);
$summarycards .= html_writer::div(
    html_writer::div(
        html_writer::tag('div', get_string('manualreview', 'mod_icontent'), ['class' => 'card-title']) .
        html_writer::tag('div', $reviewusers, ['class' => 'display-6']) .
        html_writer::tag('div', get_string('strmaxgrade', 'mod_icontent'), ['class' => 'text-muted']),
        'card-body'
    ),
    'card shadow-sm flex-fill'
);
$summarycards .= html_writer::div(
    html_writer::div(
        html_writer::tag('div', get_string('questions', 'mod_icontent'), ['class' => 'card-title']) .
        html_writer::tag('div', $totalquestions, ['class' => 'display-6']) .
        html_writer::tag('div', get_string('gradingscale', 'mod_icontent', $icontent->grade) . ' | ' . $tquestinstance, ['class' => 'text-muted']),
        'card-body'
    ),
    'card shadow-sm flex-fill'
);
echo html_writer::div($summarycards, 'd-flex flex-wrap gap-3 mb-4');

echo $OUTPUT->heading(get_string('summaryattempts', 'mod_icontent'), 4);
if ($attemptsusers) {
    echo html_writer::start_div('idtablegradeattemptsusers');
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($tattemptsusers, $page, $perpage, $url);
    echo html_writer::end_div();
} else {
    echo html_writer::div(get_string('norecordsfound', 'mod_icontent'), 'alert alert-warning');
}

echo $OUTPUT->footer();
