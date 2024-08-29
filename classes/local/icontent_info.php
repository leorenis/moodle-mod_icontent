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
define('ICONTENT_EVENT_TYPE_OPEN', 'open');
define('ICONTENT_EVENT_TYPE_CLOSE', 'close');
use mod_icontent\local\icontent_info;
use stdClass;
use csv_export_writer;
use html_writer;
use context_module;
use calendar_event;
use core_tag_tag;
use moodle_url;

/**
 * Utility class for iContent info.
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

    /**
     * Returns availability status.
     * Added 20200903.
     *
     * @param array $icontent
     */
    public static function icontent_available($icontent) {
        $timeopen = $icontent->timeopen;
        $timeclose = $icontent->timeclose;
        return (($timeopen == 0 || time() >= $timeopen) && ($timeclose == 0 || time() < $timeclose));
    }

    /**
     * Update the calendar entries for this icontent activity.
     * 
     * 20240829 Added calendar function.
     *
     * @param stdClass $icontent the row from the database table icontent.
     * @param int $cmid The coursemodule id
     * @return bool
     */
    public static function icontent_update_calendar(stdClass $icontent, $cmid) {
        global $DB, $CFG;

        if ($CFG->branch > 30) { // If Moodle less than version 3.1 skip this.
            require_once($CFG->dirroot.'/calendar/lib.php');

            // Get CMID if not sent as part of $icontent.
            if (! isset($icontent->coursemodule)) {
                $cm = get_coursemodule_from_instance('icontent', $icontent->id, $icontent->course);
                $icontent->coursemodule = $cm->id;
            }

            // icontent start calendar events.
            $event = new stdClass();
            $event->eventtype = ICONTENT_EVENT_TYPE_OPEN;
            // The ICONTENT_EVENT_TYPE_OPEN event should only be an action event if no close time is specified.
            $event->type = empty($icontent->timeclose) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
            if ($event->id = $DB->get_field('event', 'id', [
                'modulename' => 'icontent',
                'instance' => $icontent->id,
                'eventtype' => $event->eventtype,
            ])) {
                if ((!empty($icontent->timeopen)) && ($icontent->timeopen > 0)) {
                    // Calendar event exists so update it.
                    $event->name = get_string('calendarstart', 'icontent', $icontent->name);
                    $event->description = format_module_intro('icontent', $icontent, $cmid);
                    $event->timestart = $icontent->timeopen;
                    $event->timesort = $icontent->timeopen;
                    $event->visible = instance_is_visible('icontent', $icontent);
                    $event->timeduration = 0;

                    $calendarevent = calendar_event::load($event->id);
                    $calendarevent->update($event, false);
                } else {
                    // Calendar event is no longer needed.
                    $calendarevent = calendar_event::load($event->id);
                    $calendarevent->delete();
                }
            } else {
                // Event doesn't exist so create one.
                if ((!empty($icontent->timeopen)) && ($icontent->timeopen > 0)) {
                    $event->name = get_string('calendarstart', 'icontent', $icontent->name);
                    $event->description = format_module_intro('icontent', $icontent, $cmid);
                    $event->courseid = $icontent->course;
                    $event->groupid = 0;
                    $event->userid = 0;
                    $event->modulename = 'icontent';
                    $event->instance = $icontent->id;
                    $event->timestart = $icontent->timeopen;
                    $event->timesort = $icontent->timeopen;
                    $event->visible = instance_is_visible('icontent', $icontent);
                    $event->timeduration = 0;

                    calendar_event::create($event, false);
                }
            }

            // icontent end calendar events.
            $event = new stdClass();
            $event->type = CALENDAR_EVENT_TYPE_ACTION;
            $event->eventtype = ICONTENT_EVENT_TYPE_CLOSE;
            if ($event->id = $DB->get_field('event', 'id', [
                'modulename' => 'icontent',
                'instance' => $icontent->id,
                'eventtype' => $event->eventtype,
            ])) {
                if ((!empty($icontent->timeclose)) && ($icontent->timeclose > 0)) {
                    // Calendar event exists so update it.
                    $event->name = get_string('calendarend', 'icontent', $icontent->name);
                    $event->description = format_module_intro('icontent', $icontent, $cmid);
                    $event->timestart = $icontent->timeclose;
                    $event->timesort = $icontent->timeclose;
                    $event->visible = instance_is_visible('icontent', $icontent);
                    $event->timeduration = 0;

                    $calendarevent = calendar_event::load($event->id);
                    $calendarevent->update($event, false);
                } else {
                    // Calendar event is on longer needed.
                    $calendarevent = calendar_event::load($event->id);
                    $calendarevent->delete();
                }
            } else {
                // Event doesn't exist so create one.
                if ((!empty($icontent->timeclose)) && ($icontent->timeclose > 0)) {
                    $event->name = get_string('calendarend', 'icontent', $icontent->name);
                    $event->description = format_module_intro('icontent', $icontent, $cmid);
                    $event->courseid = $icontent->course;
                    $event->groupid = 0;
                    $event->userid = 0;
                    $event->modulename = 'icontent';
                    $event->instance = $icontent->id;
                    $event->timestart = $icontent->timeclose;
                    $event->timesort = $icontent->timeclose;
                    $event->visible = instance_is_visible('icontent', $icontent);
                    $event->timeduration = 0;

                    calendar_event::create($event, false);
                }
            }
            return true;
        }
    }
}
