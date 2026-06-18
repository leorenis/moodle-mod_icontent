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

namespace mod_icontent;

use core\message\message;

/**
 * Event observer for mod_icontent.
 *
 * @package    mod_icontent
 * @copyright  2026 AL Rachels <drachels@drachels.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_observer {
    /**
     * Send teacher notifications when a new question is posted.
     *
     * iContent stores notes and questions in the same table, so this observer
     * filters to only records posted in the Questions tab.
     *
     * @param \mod_icontent\event\note_created $event
     * @return void
     */
    public static function note_created(\mod_icontent\event\note_created $event): void {
        global $DB;

        $context = \context_module::instance($event->contextinstanceid);

        $note = $event->get_record_snapshot('icontent_pages_notes', $event->objectid);
        if (!$note) {
            $note = $DB->get_record('icontent_pages_notes', ['id' => $event->objectid]);
        }

        if (!$note || ($note->tab ?? '') !== 'doubt') {
            return;
        }

        $cm = get_coursemodule_from_id('icontent', $event->contextinstanceid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        $icontent = $event->get_record_snapshot('icontent', $cm->instance);
        if (!$icontent) {
            $icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);
        }

        $author = $DB->get_record('user', ['id' => $event->userid], '*', MUST_EXIST);

        // Keep notifications scoped to enrolled teachers/managers for the module.
        $teachers = get_enrolled_users($context, 'mod/icontent:manage', 0, 'u.*');
        if (empty($teachers)) {
            return;
        }

        $url = new \moodle_url('/mod/icontent/view.php', ['id' => $cm->id, 'pageid' => (int) $note->pageid]);
        $activityname = format_string($icontent->name, true, ['context' => $context]);

        foreach ($teachers as $teacher) {
            if ((int)$teacher->id === (int)$event->userid) {
                continue;
            }

            $a = (object) [
                'student' => fullname($author),
                'activity' => $activityname,
                'url' => $url->out(false),
            ];

            $messagedata = new message();
            $messagedata->courseid = $course->id;
            $messagedata->component = 'mod_icontent';
            $messagedata->name = 'question_notification';
            $messagedata->userfrom = $author;
            $messagedata->userto = $teacher;
            $messagedata->subject = get_string('questionnotificationsubject', 'mod_icontent', $a);
            $messagedata->fullmessage = get_string('questionnotificationbody', 'mod_icontent', $a);
            $messagedata->fullmessageformat = FORMAT_PLAIN;
            $messagedata->fullmessagehtml = '';
            $messagedata->smallmessage = get_string('questionnotificationsmall', 'mod_icontent', $a);
            $messagedata->contexturl = $url->out(false);
            $messagedata->contexturlname = $activityname;

            message_send($messagedata);
        }
    }
}
