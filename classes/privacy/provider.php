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

defined('MOODLE_INTERNAL') || die(); // @codingStandardsIgnoreLine

use context;
use context_helper;
use stdClass;
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
class provider implements \core_privacy\local\metadata\provider,
                          \core_privacy\local\request\plugin\provider,
                          \core_privacy\local\request\core_userlist_provider {

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
        $contextlist = new contextlist();
        $modid = self::get_modid();
        if (!$modid) {
            return $contextlist; // icontent module not installed.
        }

        $params = [
            'modid' => $modid,
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];

        // User-created icontent entries.
        $sql = '
            SELECT c.id
              FROM {context} c
              JOIN {course_modules} cm ON cm.id = c.instanceid
               AND c.contextlevel = :contextlevel
               AND cm.module = :modid
              JOIN {icontent} ic ON ic.id = cm.instance
              JOIN {icontent_pages_notes} icpn ON icpn.icontent = ic.id
             WHERE icpn.userid = :userid
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
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $modid = self::get_modid();
        if (!$modid) {
            return; // Checklist module not installed.
        }

        $params = [
            'modid' => $modid,
            'contextlevel' => CONTEXT_MODULE,
            'contextid' => $context->id,
        ];

        // Find users with icontent entries.
        $sql = '
            SELECT icpn.userid
              FROM {icontent_page_notes} icpn
              JOIN {icontent} ic
                ON ic.id = icpn.icontent
              JOIN {course_modules} cm
                ON cm.instance = ic.id
               AND cm.module = :modid
              JOIN {context} ctx
                ON ctx.instanceid = cm.id
               AND ctx.contextlevel = :contextlevel
             WHERE ctx.id = :contextid
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

        $user = $contextlist->get_user();
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "
            SELECT cm.id AS cmid,
                   de.*,
                   dp.id AS promptid,
                   dp.datestart AS promptdatestart,
                   dp.datestop AS promptdatestop,
                   dp.text AS prompttext
                 FROM {context} c
                 JOIN {course_modules} cm
                   ON cm.id = c.instanceid
                 JOIN {icontent} d
                   ON d.id = cm.instance
                 JOIN {icontent_pages_notes} de
                   ON de.icontent = d.id
            LEFT JOIN {icontent_prompts} dp
                   ON dp.id = de.promptid
                WHERE c.id $contextsql
                  AND ((de.userid = 0 OR de.userid = :userid1) AND (de.promptid = 0))
                   OR ((de.userid = 0 OR de.userid = :userid2)
                          AND (dp.icontentid = de.icontent)
                          AND (dp.datestart < de.timecreated)
                          AND (dp.datestop > de.timecreated))
                ORDER BY cm.id, de.id DESC
        ";

        $params = ['userid1' => $user->id, 'userid2' => $user->id] + $contextparams;
        $lastcmid = null;
        $itemdata = [];

        // Fetch the individual icontents entries.
        $icontents = $DB->get_recordset_sql($sql, $params);
        foreach ($icontents as $icontent) {
            if ($lastcmid !== $icontent->cmid) {
                if ($itemdata) {
                    self::export_icontent_data_for_user($itemdata, $lastcmid, $user);
                }
                $itemdata = [];
                $lastcmid = $icontent->cmid;
            }

            $itemdata[] = (object)[
                'icontent' => $icontent->icontent,
                'promptid' => $icontent->promptid,
                'promptdatestart' => $icontent->promptdatestart ? transform::datetime($icontent->promptdatestart) : '',
                'promptdatestop' => $icontent->promptdatestop ? transform::datetime($icontent->promptdatestop) : '',
                'prompttext' => strip_tags($icontent->prompttext),
                'timecreated' => $icontent->timecreated ? transform::datetime($icontent->timecreated) : '',
                'timemodified' => $icontent->timemodified ? transform::datetime($icontent->timemodified) : '',
                'title' => strip_tags($icontent->title),
                'text' => strip_tags($icontent->text),
                'rating' => $icontent->rating,
                'entrycomment' => strip_tags($icontent->entrycomment),
                'teacher' => $icontent->teacher,
                'timemarked' => $icontent->timemarked ? transform::datetime($icontent->timemarked) : '',
                'mailed' => $icontent->mailed,
            ];
        }
        $icontents->close();
        if ($itemdata) {
            self::export_icontent_data_for_user($itemdata, $lastcmid, $user);
        }
    }

    /**
     * Export the supplied personal data for a single icontent activity, along with any generic data or area files.
     *
     * @param array $items The data for each of the items in the icontent.
     * @param int $cmid
     * @param \stdClass $user
     */
    protected static function export_icontent_data_for_user(array $items, int $cmid, \stdClass $user) {
        // Fetch the generic module data for the choice.
        $context = \context_module::instance($cmid);
        $contextdata = helper::get_context_data($context, $user);

        // Merge with icontent data and write it.
        $contextdata = (object)array_merge((array)$contextdata, ['items' => $items]);
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

        // Delete the icontent entries.
        $itemids = $DB->get_fieldset_select('icontent_pages_notes', 'id', 'icontent = ?', [$cm->instance]);
        if ($itemids) {
            $DB->delete_records_select('icontent_pages_notes', 'icontent = ? AND userid <> 0', [$cm->instance]);
        }
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
            $itemids = $DB->get_fieldset_select('icontent_pages_notes', 'id', 'icontent = ?', [$cm->instance]);
            if ($itemids) {
                list($isql, $params) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
                $params['userid'] = $userid;
                $params = ['instanceid' => $cm->instance, 'userid' => $userid];
                $DB->delete_records_select('icontent_pages_notes', 'icontent = :instanceid AND userid = :userid', $params);
            }
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

        // Prepare SQL to gather all completed IDs.
        $itemids = $DB->get_fieldset_select('icontent_page_notes', 'id', 'icontent = ?', [$cm->instance]);
        list($itsql, $itparams) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        $userids = $userlist->get_userids();
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Delete user-created personal icontent note/doubt items.
        $DB->delete_records_select(
            'icontent_page_notes',
            "userid $insql AND icontent = :icontentid",
            array_merge($inparams, ['icontentid' => $cm->instance])
        );
    }
}
