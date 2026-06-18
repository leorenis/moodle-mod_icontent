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
 * Privacy class for requesting user data.
 *
 * @package   mod_icontent
 * @copyright 2024 AL Rachels <drachels@drachels.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_icontent\privacy;

defined('MOODLE_INTERNAL') || die(); // phpcs:ignore

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

require_once($CFG->dirroot . '/mod/icontent/lib.php');

/**
 * Privacy class for requesting user data.
 *
 * @package   mod_icontent
 * @copyright 2019 AL Rachels <drachels@drachels.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Provides meta data that is stored about a user with mod_icontent.
     * The acutal student data is in the, mod_icontent_pages_notes table.
     *
     * @param collection $collection The initialized collection to add items to.
     * @return collection Returns the collection of metadata.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'icontent_pages_notes',
            [
                'pageid' => 'privacy:metadata:icontent_pages_notes:pageid',
                'userid' => 'privacy:metadata:icontent_pages_notes:userid',
                'cmid' => 'privacy:metadata:icontent_pages_notes:cmid',
                'comment' => 'privacy:metadata:icontent_pages_notes:comment',
                'timecreated' => 'privacy:metadata:icontent_pages_notes:timecreated',
                'timemodified' => 'privacy:metadata:icontent_pages_notes:timemodified',
                'tab' => 'privacy:metadata:icontent_pages_notes:tab',
                'path' => 'privacy:metadata:icontent_pages_notes:path',
                'parent' => 'privacy:metadata:icontent_pages_notes:parent',
                'private' => 'privacy:metadata:icontent_pages_notes:private',
                'featured' => 'privacy:metadata:icontent_pages_notes:featured',
                'doubttutor' => 'privacy:metadata:icontent_pages_notes:doubttutor',
            ],
            'privacy:metadata:icontent_pages_notes'
        );

        $collection->add_database_table(
            'icontent_pages_notes_like',
            [
                'pagenoteid' => 'privacy:metadata:icontent_pages_notes_like:pagenoteid',
                'userid' => 'privacy:metadata:icontent_pages_notes_like:userid',
                'cmid' => 'privacy:metadata:icontent_pages_notes_like:cmid',
                'timemodified' => 'privacy:metadata:icontent_pages_notes_like:timemodified',
            ],
            'privacy:metadata:icontent_pages_notes_like'
        );

        $collection->add_database_table(
            'icontent_pages_displayed',
            [
                'pageid' => 'privacy:metadata:icontent_pages_displayed:pageid',
                'userid' => 'privacy:metadata:icontent_pages_displayed:userid',
                'cmid' => 'privacy:metadata:icontent_pages_displayed:cmid',
                'timecreated' => 'privacy:metadata:icontent_pages_displayed:timecreated',
            ],
            'privacy:metadata:icontent_pages_displayed'
        );

        $collection->add_database_table(
            'icontent_question_attempts',
            [
                'pagesquestionsid' => 'privacy:metadata:icontent_question_attempts:pagesquestionsid',
                'questionid' => 'privacy:metadata:icontent_question_attempts:questionid',
                'userid' => 'privacy:metadata:icontent_question_attempts:userid',
                'cmid' => 'privacy:metadata:icontent_question_attempts:cmid',
                'fraction' => 'privacy:metadata:icontent_question_attempts:fraction',
                'rightanswer' => 'privacy:metadata:icontent_question_attempts:rightanswer',
                'answertext' => 'privacy:metadata:icontent_question_attempts:answertext',
                'timecreated' => 'privacy:metadata:icontent_question_attempts:timecreated',
            ],
            'privacy:metadata:icontent_question_attempts'
        );

        $collection->add_database_table(
            'icontent_grades',
            [
                'icontentid' => 'privacy:metadata:icontent_grades:icontentid',
                'userid' => 'privacy:metadata:icontent_grades:userid',
                'cmid' => 'privacy:metadata:icontent_grades:cmid',
                'grade' => 'privacy:metadata:icontent_grades:grade',
                'timemodified' => 'privacy:metadata:icontent_grades:timemodified',
            ],
            'privacy:metadata:icontent_grades'
        );

        return $collection;
    }

    /** @var int */
    private static $modid;

    /**
     * Get the module id for the 'icontent' module.
     * @return false|mixed
     * @throws \dml_exception
     */
    private static function get_modid() {
        global $DB;
        if (self::$modid === null) {
            self::$modid = $DB->get_field('modules', 'id', ['name' => 'icontent']);
        }
        return self::$modid;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        $modid = self::get_modid();
        if (!$modid) {
            return $contextlist; // The icontent module is not installed.
        }

                $params = [
                        'modid' => $modid,
                        'contextlevel' => CONTEXT_MODULE,
                        'useridnotes' => $userid,
                        'useridlikes' => $userid,
                        'useriddisplayed' => $userid,
                        'useridattempts' => $userid,
                        'useridgrades' => $userid,
                ];

                $sql = '
                        SELECT DISTINCT c.id
                            FROM {context} c
                            JOIN {course_modules} cm
                                ON cm.id = c.instanceid
                             AND c.contextlevel = :contextlevel
                             AND cm.module = :modid
                 LEFT JOIN {icontent_pages_notes} n
                                ON n.cmid = cm.id
                             AND n.userid = :useridnotes
                 LEFT JOIN {icontent_pages_notes_like} l
                                ON l.cmid = cm.id
                             AND l.userid = :useridlikes
                 LEFT JOIN {icontent_pages_displayed} d
                                ON d.cmid = cm.id
                             AND d.userid = :useriddisplayed
                 LEFT JOIN {icontent_question_attempts} qa
                                ON qa.cmid = cm.id
                             AND qa.userid = :useridattempts
                 LEFT JOIN {icontent_grades} g
                                ON g.cmid = cm.id
                             AND g.userid = :useridgrades
                         WHERE n.id IS NOT NULL
                                OR l.id IS NOT NULL
                                OR d.id IS NOT NULL
                                OR qa.id IS NOT NULL
                                OR g.id IS NOT NULL
                ';

                $contextlist->add_from_sql($sql, $params);

                return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $modid = self::get_modid();
        if (!$modid) {
            return; // Checklist module not installed.
        }

        if (!$cm = get_coursemodule_from_id('icontent', $context->instanceid)) {
                return;
        }

                $params = ['cmid' => $cm->id];

                $sql = '
                        SELECT userid FROM {icontent_pages_notes} WHERE cmid = :cmid
                        UNION
                        SELECT userid FROM {icontent_pages_notes_like} WHERE cmid = :cmid
                        UNION
                        SELECT userid FROM {icontent_pages_displayed} WHERE cmid = :cmid
                        UNION
                        SELECT userid FROM {icontent_question_attempts} WHERE cmid = :cmid
                        UNION
                        SELECT userid FROM {icontent_grades} WHERE cmid = :cmid
                ';

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            if (!$cm = get_coursemodule_from_id('icontent', $context->instanceid)) {
                continue;
            }

            $notes = $DB->get_records(
                'icontent_pages_notes',
                ['cmid' => $cm->id, 'userid' => $userid],
                'timecreated DESC'
            );
            $likes = $DB->get_records(
                'icontent_pages_notes_like',
                ['cmid' => $cm->id, 'userid' => $userid],
                'timemodified DESC'
            );
            $displayed = $DB->get_records(
                'icontent_pages_displayed',
                ['cmid' => $cm->id, 'userid' => $userid],
                'timecreated DESC'
            );
            $attempts = $DB->get_records(
                'icontent_question_attempts',
                ['cmid' => $cm->id, 'userid' => $userid],
                'timecreated DESC'
            );
            $grades = $DB->get_records(
                'icontent_grades',
                ['cmid' => $cm->id, 'userid' => $userid],
                'timemodified DESC'
            );

            $export = (object) [
                'notes' => array_map(static function ($note) {
                    return (object) [
                        'pageid' => $note->pageid,
                        'comment' => strip_tags($note->comment),
                        'tab' => $note->tab,
                        'path' => $note->path,
                        'parent' => $note->parent,
                        'private' => (int) $note->private,
                        'featured' => (int) $note->featured,
                        'doubttutor' => (int) $note->doubttutor,
                        'timecreated' => $note->timecreated ? transform::datetime($note->timecreated) : '',
                        'timemodified' => $note->timemodified ? transform::datetime($note->timemodified) : '',
                    ];
                }, array_values($notes)),
                'likes' => array_map(static function ($like) {
                    return (object) [
                        'pagenoteid' => $like->pagenoteid,
                        'timemodified' => $like->timemodified ? transform::datetime($like->timemodified) : '',
                        'visible' => (int) $like->visible,
                    ];
                }, array_values($likes)),
                'displayedpages' => array_map(static function ($row) {
                    return (object) [
                        'pageid' => $row->pageid,
                        'timecreated' => $row->timecreated ? transform::datetime($row->timecreated) : '',
                    ];
                }, array_values($displayed)),
                'questionattempts' => array_map(static function ($attempt) {
                    return (object) [
                        'pagesquestionsid' => $attempt->pagesquestionsid,
                        'questionid' => $attempt->questionid,
                        'fraction' => $attempt->fraction,
                        'rightanswer' => $attempt->rightanswer,
                        'answertext' => $attempt->answertext,
                        'timecreated' => $attempt->timecreated ? transform::datetime($attempt->timecreated) : '',
                    ];
                }, array_values($attempts)),
                'grades' => array_map(static function ($grade) {
                    return (object) [
                        'icontentid' => $grade->icontentid,
                        'grade' => $grade->grade,
                        'timemodified' => $grade->timemodified ? transform::datetime($grade->timemodified) : '',
                    ];
                }, array_values($grades)),
            ];

            self::export_icontent_data_for_user($export, $cm->id, $contextlist->get_user());
        }
    }

    /**
     * Export the supplied personal data for a single icontent activity, along with any generic data or area files.
     *
     * @param \stdClass $items The data for the icontent module.
     * @param int $cmid
     * @param \stdClass $user
     */
    protected static function export_icontent_data_for_user(\stdClass $items, int $cmid, \stdClass $user) {
        // Fetch the generic module data for the choice.
        $context = \context_module::instance($cmid);
        $contextdata = helper::get_context_data($context, $user);

        // Merge with icontent data and write it.
        $contextdata = (object)array_merge((array)$contextdata, (array)$items);
        writer::with_context($context)->export_data([], $contextdata);

        // Write generic module intro files.
        helper::export_context_files($context, $user);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context) {
            return;
        }

        // This should not happen, but just in case.
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        if (!$cm = get_coursemodule_from_id('icontent', $context->instanceid)) {
            return;
        }

        $DB->delete_records('icontent_pages_notes', ['cmid' => $cm->id]);
        $DB->delete_records('icontent_pages_notes_like', ['cmid' => $cm->id]);
        $DB->delete_records('icontent_pages_displayed', ['cmid' => $cm->id]);
        $DB->delete_records('icontent_question_attempts', ['cmid' => $cm->id]);
        $DB->delete_records('icontent_grades', ['cmid' => $cm->id]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!$contextlist->count()) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            if (!$cm = get_coursemodule_from_id('icontent', $context->instanceid)) {
                continue;
            }
            $DB->delete_records('icontent_pages_notes', ['cmid' => $cm->id, 'userid' => $userid]);
            $DB->delete_records('icontent_pages_notes_like', ['cmid' => $cm->id, 'userid' => $userid]);
            $DB->delete_records('icontent_pages_displayed', ['cmid' => $cm->id, 'userid' => $userid]);
            $DB->delete_records('icontent_question_attempts', ['cmid' => $cm->id, 'userid' => $userid]);
            $DB->delete_records('icontent_grades', ['cmid' => $cm->id, 'userid' => $userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!is_a($context, \context_module::class)) {
            return;
        }
        $modid = self::get_modid();
        if (!$modid) {
            return; // The icontent module is not installed.
        }
        if (!$cm = get_coursemodule_from_id('icontent', $context->instanceid)) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $DB->delete_records_select(
            'icontent_pages_notes',
            "userid $insql AND cmid = :cmid",
            array_merge($inparams, ['cmid' => $cm->id])
        );
        $DB->delete_records_select(
            'icontent_pages_notes_like',
            "userid $insql AND cmid = :cmid",
            array_merge($inparams, ['cmid' => $cm->id])
        );
        $DB->delete_records_select(
            'icontent_pages_displayed',
            "userid $insql AND cmid = :cmid",
            array_merge($inparams, ['cmid' => $cm->id])
        );
        $DB->delete_records_select(
            'icontent_question_attempts',
            "userid $insql AND cmid = :cmid",
            array_merge($inparams, ['cmid' => $cm->id])
        );
        $DB->delete_records_select(
            'icontent_grades',
            "userid $insql AND cmid = :cmid",
            array_merge($inparams, ['cmid' => $cm->id])
        );
    }
}
