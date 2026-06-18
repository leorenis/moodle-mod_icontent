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
 * Manual review.
 *
 * @package    mod_icontent
 * @copyright  2016-2015 Leo Santos {@link http://github.com/leorenis}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.
$pageid = optional_param('pageid', 0, PARAM_INT); // Page note ID.
$action = optional_param('action', 'grading', PARAM_ALPHA); // Action.
$status = optional_param('status', ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE, PARAM_ALPHA); // Status.
$group = optional_param('group', 0, PARAM_INT); // Group filter.
$sort = optional_param('sort', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', ICONTENT_PER_PAGE, PARAM_INT);

$cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/icontent:grade', $context);
// Page setting.
$PAGE->set_url('/mod/icontent/grading.php', ['id' => $cm->id, 'action' => $action, 'status' => $status, 'group' => $group]);
$PAGE->set_pagelayout('incourse');

// Show the iContent TOC block while in Manual review mode for quick page navigation.
$pages = \mod_icontent\local\icontent_info::icontent_preload_pages($icontent);
$currenttocpage = icontent_get_startpagenum($icontent, $context);
$tocedit = has_capability('mod/icontent:edit', $context);
if (!empty($pages)) {
    icontent_add_fake_block($pages, $currenttocpage, $icontent, $cm, $tocedit);
}

// Header and strings.
$PAGE->set_title($icontent->name);
$PAGE->set_heading($course->fullname);
// phpcs:ignore
// ...$PAGE->requires->js(new moodle_url($CFG->wwwroot.'/mod/icontent/js/jquery/jquery-1.11.3.min.js'), true);.
// ...$PAGE->requires->js(new moodle_url($CFG->wwwroot.'/mod/icontent/js/bootstrap/bootstrap.min.js'));.
echo $OUTPUT->header();
echo $OUTPUT->heading($icontent->name);
echo $OUTPUT->heading(get_string('strmanualgrading', 'mod_icontent'), 3);
$reportoverviewurl = new moodle_url('/mod/icontent/report.php', ['id' => $id, 'group' => $group]);
$gradesurl = new moodle_url('/mod/icontent/grade.php', ['id' => $id, 'action' => 'overview', 'group' => $group]);
$manualreviewurl = new moodle_url('/mod/icontent/grading.php', [
    'id' => $id,
    'action' => 'grading',
    'status' => $status,
    'group' => $group,
]);
$modetoggle = html_writer::div(
    html_writer::link($reportoverviewurl, get_string('reportoverview', 'mod_icontent'), ['class' => 'btn btn-secondary mr-2']) .
    html_writer::link($gradesurl, get_string('grades'), ['class' => 'btn btn-secondary mr-2']) .
    html_writer::link($manualreviewurl, get_string('manualreview', 'mod_icontent'), ['class' => 'btn btn-primary']),
    'mb-3 icontent-results-mode-toggle'
);
echo $modetoggle;

$toevaluateurl = new moodle_url('/mod/icontent/grading.php', [
    'id' => $id,
    'action' => 'grading',
    'status' => ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE,
    'group' => $group,
]);
$valuedurl = new moodle_url('/mod/icontent/grading.php', [
    'id' => $id,
    'action' => 'grading',
    'status' => ICONTENT_QTYPE_ESSAY_STATUS_VALUED,
    'group' => $group,
]);
$statusnav = html_writer::div(
    html_writer::link(
        $toevaluateurl,
        get_string('toevaluate', 'mod_icontent'),
        ['class' => 'btn ' . ($status === ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE ? 'btn-primary' : 'btn-secondary') . ' mr-2']
    ) .
    html_writer::link(
        $valuedurl,
        get_string('reassess', 'mod_icontent'),
        ['class' => 'btn ' . ($status === ICONTENT_QTYPE_ESSAY_STATUS_VALUED ? 'btn-primary' : 'btn-secondary')]
    ),
    'mb-3 icontent-manualreview-status-toggle'
);
echo $statusnav;
$currentgroup = 0;
if (groups_get_activity_groupmode($cm) != NOGROUPS) {
    $currentgroup = groups_get_activity_group($cm, true);
    if ($currentgroup) {
        $group = $currentgroup;
    }
    $groupmenuurl = new moodle_url('/mod/icontent/grading.php', ['id' => $id, 'action' => $action, 'status' => $status]);
    echo groups_print_activity_menu($cm, $groupmenuurl, true);
}

$url = new moodle_url(
    '/mod/icontent/grading.php',
    [
        'id' => $id,
        'action' => $action,
        'status' => $status,
        'group' => $group,
        'page' => $page,
        'perpage' => $perpage,
    ]
);
// Get sort value.
$sort = icontent_check_value_sort($sort);
// Get answers not evaluated.
$attemptsusers = icontent_get_attempts_users_with_open_answers($cm->id, $sort, $status, $page, $perpage, $group);
$tattemtpsusers = icontent_count_attempts_users_with_open_answers($cm->id, $status, $group);
// Make message info.
$clickhere = html_writer::link(
    new moodle_url(
        '/mod/icontent/grading.php',
        [
            'id' => $cm->id,
            'action' => 'grading',
            'status' => 'valued',
            'group' => $group,
            'page' => $page,
            'perpage' => $perpage,
        ]
    ),
    get_string('clickhere', 'mod_icontent'),
    [
        'title' => get_string('reassess', 'mod_icontent'),
        'data-toggle' => 'tooltip',
        'data-placement' => 'top',
    ]
);
// Make table questions.
$table = new html_table();
$table->id = "idtableattemptsusers";
$table->colclasses = ['fullname', 'answers', 'actions'];
$table->attributes = ['class' => 'table table-hover tableattemptsusers'];
$table->head  = [get_string('fullname'), get_string('toevaluate', 'mod_icontent'), get_string('action', 'mod_icontent')];
if ($attemptsusers) {
    foreach ($attemptsusers as $attemptuser) {
        // Get picture.
        $picture = $OUTPUT->user_picture($attemptuser, ['size' => 35, 'class' => 'img-thumbnail pull-left']);
        $linkfirstname = html_writer::link(
            new moodle_url(
                '/user/view.php',
                [
                'id' => $attemptuser->id,
                'course' => $course->id,
                ]
            ),
            $attemptuser->firstname . ' ' . $attemptuser->lastname,
            [
                'title' => $attemptuser->firstname,
                'class' => 'lkfullname',
            ]
        );
        // String open answers for user.
        $stropenanswer = get_string('stropenanswer', 'mod_icontent', $attemptuser->totalopenanswers);
        $icontoevaluate = html_writer::link(
            new moodle_url(
                'toevaluate.php',
                [
                'id' => $cm->id,
                'status' => $status,
                'userid' => $attemptuser->id,
                'sesskey' => sesskey(),
                ]
            ),
            '<i class="fa fa-check-circle fa-lg"></i>',
            [
                'class' => 'btn btn-primary btn-toevaluate',
                'title' => get_string('evaluate', 'mod_icontent'),
                'data-toggle' => 'tooltip',
                'data-placement' => 'top',
            ]
        );
        // Set data.
        $table->data[] = [$picture . $linkfirstname, $stropenanswer, $icontoevaluate];
    }
} else {
    echo html_writer::div(get_string('norecordsfound', 'mod_icontent') . ' ' . $clickhere .
        get_string('toreassess', 'mod_icontent') . '.', 'alert alert-warning');
    echo $OUTPUT->footer();
    exit;
}
// Show message info.
echo html_writer::div(get_string('answersevaluatedinfo', 'mod_icontent', $clickhere), 'alert alert-info');
// Show table.
echo html_writer::start_div('idtableagradingttemptsusers');
echo html_writer::table($table);
echo $OUTPUT->paging_bar($tattemtpsusers, $page, $perpage, $url);
echo html_writer::end_div();
echo icontent_add_script_load_tooltip();
echo $OUTPUT->footer();
