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

}
