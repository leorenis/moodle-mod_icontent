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
 * Keyboard utilities for MooTyper.
 *
 * 3/19/2020 Moved these functions from locallib.php to here.
 *
 * @package    mod_icontent
 * @copyright  AL Rachels (drachels@drachels.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_icontent\local;
defined('MOODLE_INTERNAL') || die(); // @codingStandardsIgnoreLine

/**
 * Utility class for iContent results.
 *
 * @package    mod_icontent
 * @copyright  AL Rachels (drachels@drachels.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class icontent_info {

    /**
     * Preload icontent pages.
     *
     * Returns array of pages.
     * Please note the icontent/text of pages is not included.
     *
     * @param object $icontent
     * @return array of id=>icontent
     */
    public static function icontent_preload_pages($icontent) {
        global $DB;
        $pages = $DB->get_records('icontent_pages',
            [
                'icontentid' => $icontent->id,
            ],
            'pagenum',
            'id,
            icontentid,
            cmid,
            pagenum,
            coverpage,
            title,
            hidden'
        );
        if (!$pages) {
            return [];
        }
        $first = true;
        $pagenum = 0; // Page sort.
        foreach ($pages as $id => $pg) {
            $oldpg = clone($pg);
            $pagenum++;
            $pg->pagenum = $pagenum;
            if ($first) {
                $first = false;
            }
            if ($oldpg->pagenum != $pg->pagenum || $oldpg->hidden != $pg->hidden) {
                // Update only if something changed.
                $DB->update_record('icontent_pages', $pg);
            }
            $pages[$id] = $pg;
        }
        return $pages;
    }

    /**
     * Count icontent notes.
     *
     * Returns array of notes.
     *
     * @param object $icontent
     * @return array of id=>icontent
     */
    public static function icontent_note_count($icontent) {
        global $DB;
        $pages = $DB->get_records('icontent_pages',
            [
                'icontentid' => $icontent->id,
            ],
            'pagenum',
            'id,
            icontentid,
            cmid,
            pagenum,
            coverpage,
            title,
            hidden'
        );

        if (!$pages) {
            return [];
        }
        $notesnum = 0; // Note count.
        foreach ($pages as $page) {
            $notes = $DB->get_records('icontent_pages_notes',
                [
                    'pageid' => $page->id,
                ],
                    'cmid,
                    tab,
                    private,
                    featured'
                );
                foreach ($notes as $note) {
                    if ($note->tab == 'note') {
                        $notesnum++;
                    }
                }
            }
        return $notesnum;
    }

    /**
     * Count icontent notes where it is a note.
     *
     * Returns array of notes.
     *
     * @param object $icontent
     * @return array of id=>icontent
     */
    public static function icontent_doubt_count($icontent) {
        global $DB;
        $pages = $DB->get_records('icontent_pages',
            [
                'icontentid' => $icontent->id,
            ],
            'pagenum',
            'id,
            icontentid,
            cmid,
            pagenum,
            coverpage,
            title,
            hidden'
        );

        if (!$pages) {
            return [];
        }
        $doubtsnum = 0; // Note count where it is a doubt/question to the teacher/tutor.
        foreach ($pages as $page) {
            $doubts = $DB->get_records('icontent_pages_notes',
                [
                    'pageid' => $page->id,
                ],
                    'cmid,
                    tab,
                    private,
                    featured'
                );
                foreach ($doubts as $doubt) {
                    if ($doubt->tab == 'doubt') {
                        $doubtsnum++;
                    }
                }
            }
        return $doubtsnum;
    }
}