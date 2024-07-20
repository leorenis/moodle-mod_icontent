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
 * Note utilities for iContent.
 *
 * 3/19/2020 Moved these functions from locallib.php to here.
 *
 * @package    mod_icontent
 * @copyright  AL Rachels (drachels@drachels.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_icontent\notes;
defined('MOODLE_INTERNAL') || die(); // @codingStandardsIgnoreLine
use mod_icontent\notes\icontent_note_options;
use context_module;

/**
 * Utility class for iContent notes.
 *
 * @package    mod_icontent
 * @copyright  AL Rachels (drachels@drachels.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class icontent_note_options {

    /**
     * Remove notes from a page. If the param $pagenoteid was passed, It will delete only the current note and their daughters.
     *
     * Returns boolean true or false.
     *
     * @param int $pageid
     * @param int $pagenoteid
     * @return boolean true or false
     */
    public static function icontent_remove_notes($pageid, $pagenoteid = null) {
        global $DB;
        $rs = false;
        if ($pagenoteid) {
            // Verifies that note have daughters.
            $notesdaughters = icontent_get_notes_daughters($pagenoteid);
            if ($notesdaughters) {
                foreach ($notesdaughters as $pnid => $comment) {
                    self::icontent_remove_note_likes($pnid);
                    $rs = $DB->delete_records('icontent_pages_notes', ['id' => $pnid]);
                }
            }
            // Remove current note.
            self::icontent_remove_note_likes($pagenoteid);
            $rs = $DB->delete_records('icontent_pages_notes', ['id' => $pagenoteid]);
            return $rs ? true : false;
        }
        // Get notes.
        $pagenotes = $DB->get_records('icontent_pages_notes', ['pageid' => $pageid]);
        foreach ($pagenotes as $pagenote) {
            self::icontent_remove_note_likes($pagenote->id);
            $rs = $DB->delete_records('icontent_pages_notes', ['id' => $pagenote->id]);
        }
        return $rs ? true : false;
    }

    /**
     * Remove note likes of page.
     *
     * Returns boolean true or false
     *
     * @param int $pagenoteid
     * @return boolean true or false
     */
    public static function icontent_remove_note_likes($pagenoteid) {
        global $DB;
        $rs = $DB->delete_records('icontent_pages_notes_like', ['pagenoteid' => $pagenoteid]);
        return $rs ? true : false;
    }

    /**
     * Get count notes of users in featured or private by course modules ID <iContent>.
     *
     * This function is reached via the, Comments, on the activity navigation bar.
     * Returns object notes users.
     *
     * @param int $cmid
     * @param int $private
     * @param int $featured
     * @param int $doubttutor
     * @param int $likes
     * @param string $tab
     * @return object $notes, otherwhise false.
     */
    public static function icontent_count_notes_users_instance(
        $cmid,
        $private = null,
        $featured = null,
        $doubttutor = null,
        $likes = null,
        $tab = null) {
        global $DB, $USER;
        // Get context.
        $context = context_module::instance($cmid);
        // Filter.
        $andfilter = '';
        $joinfilter = '';
        $distinct = '';
        $arrayfilter = [$cmid];
        if ($private) {
            $andfilter .= 'AND pn.private = ? ';
            array_push($arrayfilter, $private);
        }
        if ($featured) {
            $andfilter .= 'AND pn.featured = ? ';
            array_push($arrayfilter, $featured);
        }
        if ($doubttutor) {
            $andfilter .= 'AND pn.doubttutor = ? ';
            array_push($arrayfilter, $doubttutor);
        }
        // If not has any capability and $likes equals null, so add filter for user.
        if (!has_any_capability(['mod/icontent:edit', 'mod/icontent:manage'], $context) && !$likes) {
            $andfilter .= 'AND u.id = ? ';
            array_push($arrayfilter, $USER->id);
        }
        if ($likes) {
            $joinfilter .= 'INNER JOIN {icontent_pages_notes_like} pnl ON pn.id = pnl.pagenoteid';
            $andfilter .= 'AND pnl.userid = ? ';
            array_push($arrayfilter, $USER->id);
        }
        // Query.
        $sql = "SELECT Count(*) AS total
                  FROM {icontent_pages_notes} pn
            INNER JOIN {user} u
                    ON pn.userid = u.id
                       {$joinfilter}
                 WHERE pn.cmid = ?
                       {$andfilter};";
        $notes = $DB->get_record_sql($sql, $arrayfilter);
        return $notes->total;
    }

}
