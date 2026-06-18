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
 * Tests for iContent event observers.
 *
 * @package    mod_icontent
 * @category   test
 * @copyright  2026 AL Rachels <drachels@drachels.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_icontent;

/**
 * Testcases for mod_icontent event observers.
 *
 * @covers \mod_icontent\event_observer
 */
final class event_observer_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Ensure a new doubt/question sends exactly one teacher notification.
     */
    public function test_note_created_doubt_sends_teacher_notification(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $student = $generator->create_user();

        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($student->id, $course->id, 'student');

        $module = $generator->create_module('icontent', ['course' => $course->id, 'name' => 'Notify iContent']);
        $icontent = $DB->get_record('icontent', ['id' => $module->id], '*', MUST_EXIST);
        $context = \context_module::instance($module->cmid);

        $pageid = $this->create_page($icontent->id, $module->cmid);
        $time = time();

        $note = (object) [
            'pageid' => $pageid,
            'userid' => $student->id,
            'cmid' => $module->cmid,
            'comment' => 'Student question',
            'timecreated' => $time,
            'timemodified' => $time,
            'tab' => 'doubt',
            'path' => '/1',
            'parent' => 0,
            'private' => 0,
            'featured' => 0,
            'doubttutor' => 1,
        ];
        $note->id = (int) $DB->insert_record('icontent_pages_notes', $note);

        $sink = $this->redirectMessages();
        $this->setUser($student);

        \mod_icontent\event\note_created::create_from_note($icontent, $context, $note)->trigger();

        $messages = $sink->get_messages_by_component('mod_icontent');
        $this->assertCount(1, $messages);

        $message = reset($messages);
        $this->assertSame('mod_icontent', $message->component);
        $this->assertSame((int)$teacher->id, (int)$message->useridto);
        $this->assertStringContainsString('posted a new question', $message->fullmessage);
    }

    /**
     * Ensure a normal note does not send teacher question notifications.
     */
    public function test_note_created_note_does_not_send_teacher_notification(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $student = $generator->create_user();

        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($student->id, $course->id, 'student');

        $module = $generator->create_module('icontent', ['course' => $course->id, 'name' => 'Notify iContent']);
        $icontent = $DB->get_record('icontent', ['id' => $module->id], '*', MUST_EXIST);
        $context = \context_module::instance($module->cmid);

        $pageid = $this->create_page($icontent->id, $module->cmid);
        $time = time();

        $note = (object) [
            'pageid' => $pageid,
            'userid' => $student->id,
            'cmid' => $module->cmid,
            'comment' => 'Student note',
            'timecreated' => $time,
            'timemodified' => $time,
            'tab' => 'note',
            'path' => '/1',
            'parent' => 0,
            'private' => 0,
            'featured' => 0,
            'doubttutor' => 0,
        ];
        $note->id = (int) $DB->insert_record('icontent_pages_notes', $note);

        $sink = $this->redirectMessages();
        $this->setUser($student);

        \mod_icontent\event\note_created::create_from_note($icontent, $context, $note)->trigger();

        $messages = $sink->get_messages_by_component('mod_icontent');
        $this->assertCount(0, $messages);
    }

    /**
     * Create a single iContent page for event tests.
     *
     * @param int $icontentid
     * @param int $cmid
     * @return int
     */
    private function create_page(int $icontentid, int $cmid): int {
        global $DB;

        $time = time();
        return (int) $DB->insert_record('icontent_pages', (object) [
            'icontentid' => $icontentid,
            'cmid' => $cmid,
            'coverpage' => 0,
            'title' => 'Observer test page',
            'showtitle' => 1,
            'pageicontent' => 'Observer test content',
            'pageicontentformat' => FORMAT_HTML,
            'pagenum' => 1,
            'hidden' => 0,
            'timecreated' => $time,
            'timemodified' => $time,
        ]);
    }
}
