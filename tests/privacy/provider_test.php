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
 * Privacy provider tests.
 *
 * @package    mod_icontent
 * @category   test
 * @copyright  2026 AL Rachels <drachels@drachels.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_icontent\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\tests\provider_testcase;

/**
 * Testcases for mod_icontent privacy provider.
 *
 * @covers \mod_icontent\privacy\provider
 */
final class provider_test extends provider_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Ensure user contexts are discovered from iContent user-data tables.
     */
    public function test_get_contexts_for_userid(): void {
        [$cm1, $ctx1, $page1, $qmap1] = $this->create_icontent_page_and_questionmap();
        [$cm2, $ctx2, $page2, $qmap2] = $this->create_icontent_page_and_questionmap();

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();

        $this->seed_user_data_for_cmid((int) $ctx1->instanceid, $page1, $qmap1, $u1->id);
        $this->seed_user_data_for_cmid((int) $ctx2->instanceid, $page2, $qmap2, $u2->id);

        $contextidsu1 = array_map('intval', provider::get_contexts_for_userid($u1->id)->get_contextids());
        $this->assertContains($ctx1->id, $contextidsu1);
        $this->assertNotContains($ctx2->id, $contextidsu1);
    }

    /**
     * Ensure deleting one user in a context removes only their iContent rows.
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        [$cm, $context, $pageid, $qmapid] = $this->create_icontent_page_and_questionmap();
        $cmid = (int) $context->instanceid;
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();

        $this->seed_user_data_for_cmid($cmid, $pageid, $qmapid, $u1->id);
        $this->seed_user_data_for_cmid($cmid, $pageid, $qmapid, $u2->id);

        $approved = new approved_contextlist($u1, 'mod_icontent', [$context->id]);
        provider::delete_data_for_user($approved);

        $this->assertSame(0, $DB->count_records('icontent_pages_notes', ['cmid' => $cmid, 'userid' => $u1->id]));
        $this->assertSame(1, $DB->count_records('icontent_pages_notes', ['cmid' => $cmid, 'userid' => $u2->id]));

        $this->assertSame(0, $DB->count_records('icontent_pages_notes_like', ['cmid' => $cmid, 'userid' => $u1->id]));
        $this->assertSame(1, $DB->count_records('icontent_pages_notes_like', ['cmid' => $cmid, 'userid' => $u2->id]));

        $this->assertSame(0, $DB->count_records('icontent_pages_displayed', ['cmid' => $cmid, 'userid' => $u1->id]));
        $this->assertSame(1, $DB->count_records('icontent_pages_displayed', ['cmid' => $cmid, 'userid' => $u2->id]));

        $this->assertSame(0, $DB->count_records('icontent_question_attempts', ['cmid' => $cmid, 'userid' => $u1->id]));
        $this->assertSame(1, $DB->count_records('icontent_question_attempts', ['cmid' => $cmid, 'userid' => $u2->id]));

        $this->assertSame(0, $DB->count_records('icontent_grades', ['cmid' => $cmid, 'userid' => $u1->id]));
        $this->assertSame(1, $DB->count_records('icontent_grades', ['cmid' => $cmid, 'userid' => $u2->id]));
    }

    /**
     * Ensure deleting all users in context clears all iContent user-data rows for that module.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        [$cm, $context, $pageid, $qmapid] = $this->create_icontent_page_and_questionmap();
        $cmid = (int) $context->instanceid;
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();

        $this->seed_user_data_for_cmid($cmid, $pageid, $qmapid, $u1->id);
        $this->seed_user_data_for_cmid($cmid, $pageid, $qmapid, $u2->id);

        provider::delete_data_for_all_users_in_context($context);

        $this->assertSame(0, $DB->count_records('icontent_pages_notes', ['cmid' => $cmid]));
        $this->assertSame(0, $DB->count_records('icontent_pages_notes_like', ['cmid' => $cmid]));
        $this->assertSame(0, $DB->count_records('icontent_pages_displayed', ['cmid' => $cmid]));
        $this->assertSame(0, $DB->count_records('icontent_question_attempts', ['cmid' => $cmid]));
        $this->assertSame(0, $DB->count_records('icontent_grades', ['cmid' => $cmid]));
    }

    /**
     * Ensure deleting selected users in context only removes selected users.
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        [$cm, $context, $pageid, $qmapid] = $this->create_icontent_page_and_questionmap();
        $cmid = (int) $context->instanceid;
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();

        $this->seed_user_data_for_cmid($cmid, $pageid, $qmapid, $u1->id);
        $this->seed_user_data_for_cmid($cmid, $pageid, $qmapid, $u2->id);

        $approved = new approved_userlist($context, 'mod_icontent', [$u1->id]);
        provider::delete_data_for_users($approved);

        $this->assertSame(0, $DB->count_records('icontent_pages_notes', ['cmid' => $cmid, 'userid' => $u1->id]));
        $this->assertSame(1, $DB->count_records('icontent_pages_notes', ['cmid' => $cmid, 'userid' => $u2->id]));

        $this->assertSame(0, $DB->count_records('icontent_pages_notes_like', ['cmid' => $cmid, 'userid' => $u1->id]));
        $this->assertSame(1, $DB->count_records('icontent_pages_notes_like', ['cmid' => $cmid, 'userid' => $u2->id]));

        $this->assertSame(0, $DB->count_records('icontent_pages_displayed', ['cmid' => $cmid, 'userid' => $u1->id]));
        $this->assertSame(1, $DB->count_records('icontent_pages_displayed', ['cmid' => $cmid, 'userid' => $u2->id]));

        $this->assertSame(0, $DB->count_records('icontent_question_attempts', ['cmid' => $cmid, 'userid' => $u1->id]));
        $this->assertSame(1, $DB->count_records('icontent_question_attempts', ['cmid' => $cmid, 'userid' => $u2->id]));

        $this->assertSame(0, $DB->count_records('icontent_grades', ['cmid' => $cmid, 'userid' => $u1->id]));
        $this->assertSame(1, $DB->count_records('icontent_grades', ['cmid' => $cmid, 'userid' => $u2->id]));
    }

    /**
     * Create one iContent activity with one page and one page-question mapping.
     *
     * @return array
     */
    private function create_icontent_page_and_questionmap(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('icontent', ['course' => $course->id]);

        $time = time();
        $page = (object) [
            'icontentid' => $module->id,
            'cmid' => $module->cmid,
            'coverpage' => 0,
            'title' => 'Privacy test page',
            'showtitle' => 1,
            'pageicontent' => 'Privacy test content',
            'pageicontentformat' => FORMAT_HTML,
            'pagenum' => 1,
            'hidden' => 0,
            'timecreated' => $time,
            'timemodified' => $time,
        ];
        $pageid = (int) $DB->insert_record('icontent_pages', $page);

        $map = (object) [
            'pageid' => $pageid,
            'questionid' => 0,
            'cmid' => $module->cmid,
            'timecreated' => $time,
            'timemodified' => $time,
            'maxmark' => 0,
            'remake' => 0,
            'qtype' => 'essay',
        ];
        $qmapid = (int) $DB->insert_record('icontent_pages_questions', $map);

        return [
            $module,
            \context_module::instance($module->cmid),
            $pageid,
            $qmapid,
        ];
    }

    /**
     * Seed one user's data in all privacy-relevant iContent user tables.
     *
     * @param int $cmid
     * @param int $pageid
     * @param int $qmapid
     * @param int $userid
     */
    private function seed_user_data_for_cmid(int $cmid, int $pageid, int $qmapid, int $userid): void {
        global $DB;

        $icontentid = (int) $DB->get_field('course_modules', 'instance', ['id' => $cmid]);
        $time = time();
        $noteid = (int) $DB->insert_record('icontent_pages_notes', (object) [
            'pageid' => $pageid,
            'userid' => $userid,
            'cmid' => $cmid,
            'comment' => 'Privacy test note',
            'timecreated' => $time,
            'timemodified' => $time,
            'tab' => 'note',
            'path' => '0',
            'parent' => 0,
            'private' => 0,
            'featured' => 0,
            'doubttutor' => 0,
        ]);

        $DB->insert_record('icontent_pages_notes_like', (object) [
            'pagenoteid' => $noteid,
            'userid' => $userid,
            'cmid' => $cmid,
            'timemodified' => $time,
            'visible' => 1,
        ]);

        $DB->insert_record('icontent_pages_displayed', (object) [
            'pageid' => $pageid,
            'userid' => $userid,
            'cmid' => $cmid,
            'timecreated' => $time,
        ]);

        $DB->insert_record('icontent_question_attempts', (object) [
            'pagesquestionsid' => $qmapid,
            'questionid' => 0,
            'userid' => $userid,
            'cmid' => $cmid,
            'fraction' => 0,
            'rightanswer' => 'right',
            'answertext' => 'answer',
            'timecreated' => $time,
        ]);

        $DB->insert_record('icontent_grades', (object) [
            'icontentid' => $icontentid,
            'userid' => $userid,
            'cmid' => $cmid,
            'grade' => 0,
            'timemodified' => $time,
        ]);
    }
}
