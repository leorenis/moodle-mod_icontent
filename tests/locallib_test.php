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
 * Tests for iContent locallib branch navigation helpers.
 *
 * @package    mod_icontent
 * @category   test
 * @copyright  2026 AL Rachels <drachels@drachels.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_icontent;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/icontent/locallib.php');

/**
 * Testcases for branch-style iContent navigation.
 *
 * @covers ::icontent_get_toc
 * @covers ::icontent_get_next_pagenum
 * @covers ::icontent_get_prev_pagenum
 * @covers ::icontent_duplicate_page
 */
final class locallib_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Ensure branch TOC output includes an indented default branch label.
     */
    public function test_get_toc_renders_default_branch_label(): void {
        [$course, $module, $icontent, $student] = $this->create_activity_fixture();
        $this->setUser($student);

        $parentpageid = $this->create_page($icontent->id, $module->cmid, 1, 'Main page');
        $this->create_page($icontent->id, $module->cmid, 2, 'Branch page A', [
            'branchref' => 'support-a',
            'branchparentpageid' => $parentpageid,
        ]);
        $this->create_page($icontent->id, $module->cmid, 3, 'Branch page B', [
            'branchref' => 'support-a',
            'branchparentpageid' => $parentpageid,
        ]);

        $cm = get_coursemodule_from_id('icontent', $module->cmid, 0, false, MUST_EXIST);
        $pages = \mod_icontent\local\icontent_info::icontent_preload_pages($icontent);
        $currentpage = $pages[$parentpageid];

        $toc = \icontent_get_toc($pages, $currentpage, $icontent, $cm, false);

        $this->assertStringContainsString('Branch 1', $toc);
        $this->assertStringContainsString('cluster-list', $toc);
        $this->assertStringContainsString('Branch page A', $toc);
        $this->assertStringContainsString('Branch page B', $toc);
    }

    /**
     * Ensure routed next/branch traversal/history-aware previous work together.
     */
    public function test_branch_navigation_uses_routes_and_history(): void {
        global $DB;

        [$course, $module, $icontent, $student] = $this->create_activity_fixture();
        $this->setUser($student);

        $entrypageid = $this->create_page($icontent->id, $module->cmid, 1, 'Check-in');
        $branchpage1id = $this->create_page($icontent->id, $module->cmid, 2, 'Branch start', [
            'branchref' => 'remedial',
            'branchname' => 'Remedial path',
            'branchparentpageid' => $entrypageid,
        ]);
        $branchpage2id = $this->create_page($icontent->id, $module->cmid, 3, 'Branch follow-up', [
            'branchref' => 'remedial',
            'branchname' => 'Remedial path',
            'branchparentpageid' => $entrypageid,
        ]);
        $resumepageid = $this->create_page($icontent->id, $module->cmid, 4, 'Resume mainline');

        $time = time();
        $pagesquestionid = (int)$DB->insert_record('icontent_pages_questions', (object) [
            'pageid' => $entrypageid,
            'questionid' => 999999,
            'cmid' => $module->cmid,
            'timecreated' => $time,
            'timemodified' => $time,
            'maxmark' => 1,
            'remake' => 0,
            'qtype' => 'truefalse',
            'correctnextpageid' => $branchpage1id,
            'incorrectnextpageid' => 0,
            'manualreviewnextpageid' => 0,
            'defaultnextpageid' => 0,
        ]);
        $DB->insert_record('icontent_question_attempts', (object) [
            'pagesquestionsid' => $pagesquestionid,
            'questionid' => 999999,
            'userid' => $student->id,
            'cmid' => $module->cmid,
            'fraction' => 1,
            'rightanswer' => 'correct',
            'answertext' => 'Correct',
            'timecreated' => $time,
        ]);

        $entrypage = $DB->get_record('icontent_pages', ['id' => $entrypageid], '*', MUST_EXIST);
        $branchpage1 = $DB->get_record('icontent_pages', ['id' => $branchpage1id], '*', MUST_EXIST);
        $branchpage2 = $DB->get_record('icontent_pages', ['id' => $branchpage2id], '*', MUST_EXIST);
        $resumepage = $DB->get_record('icontent_pages', ['id' => $resumepageid], '*', MUST_EXIST);

        $this->assertSame((int)$branchpage1->pagenum, \icontent_get_next_pagenum($entrypage));
        $this->assertSame((int)$branchpage2->pagenum, \icontent_get_next_pagenum($branchpage1));
        $this->assertSame((int)$resumepage->pagenum, \icontent_get_next_pagenum($branchpage2));

        \icontent_record_page_navigation($entrypageid, $branchpage1id, $module->cmid);
        \icontent_record_page_navigation($branchpage1id, $branchpage2id, $module->cmid);
        \icontent_record_page_navigation($branchpage2id, $resumepageid, $module->cmid);

        $this->assertSame((int)$branchpage2->pagenum, \icontent_get_prev_pagenum($resumepage));
    }

    /**
     * Ensure duplicated pages are inserted after source and reset nav overrides.
     */
    public function test_duplicate_page_inserts_after_source_and_resets_nav_overrides(): void {
        global $DB;

        [$course, $module, $icontent, $student] = $this->create_activity_fixture();
        $this->setUser($student);

        $firstpageid = $this->create_page($icontent->id, $module->cmid, 1, 'Intro');
        $sourcepageid = $this->create_page($icontent->id, $module->cmid, 2, 'Source page', [
            'branchref' => 'branch-a',
            'branchname' => 'Branch A',
            'branchparentpageid' => $firstpageid,
            'prevmode' => 2,
            'prevpageid' => $firstpageid,
            'nextmode' => 2,
            'nextpageid' => $firstpageid,
            'pageicontent' => '<p>Sample content</p>',
        ]);
        $followingpageid = $this->create_page($icontent->id, $module->cmid, 3, 'After source');

        $context = \context_module::instance($module->cmid);
        $duplicated = \icontent_duplicate_page((int)$icontent->id, (int)$module->cmid, $sourcepageid, $context);

        $source = $DB->get_record('icontent_pages', ['id' => $sourcepageid], '*', MUST_EXIST);
        $following = $DB->get_record('icontent_pages', ['id' => $followingpageid], '*', MUST_EXIST);
        $newpage = $DB->get_record('icontent_pages', ['id' => (int)$duplicated->id], '*', MUST_EXIST);

        $this->assertSame((int)$source->pagenum + 1, (int)$newpage->pagenum);
        $this->assertSame(4, (int)$following->pagenum);

        $this->assertSame('branch-a', (string)$newpage->branchref);
        $this->assertSame('Branch A', (string)$newpage->branchname);
        $this->assertSame((int)$source->branchparentpageid, (int)$newpage->branchparentpageid);

        $this->assertSame(0, (int)$newpage->prevmode);
        $this->assertSame(0, (int)$newpage->prevpageid);
        $this->assertSame(1, (int)$newpage->nextmode);
        $this->assertSame(0, (int)$newpage->nextpageid);

        $this->assertSame((string)$source->pageicontent, (string)$newpage->pageicontent);
        $this->assertStringContainsString('(Copy)', (string)$newpage->title);
    }

    /**
     * Ensure duplicate copies files from both page and bgpage fileareas.
     */
    public function test_duplicate_page_copies_page_and_bgpage_files(): void {
        [$course, $module, $icontent, $student] = $this->create_activity_fixture();
        $this->setUser($student);

        $sourcepageid = $this->create_page($icontent->id, $module->cmid, 1, 'Source with files');
        $context = \context_module::instance($module->cmid);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => (int)$context->id,
            'component' => 'mod_icontent',
            'filearea' => 'page',
            'itemid' => $sourcepageid,
            'filepath' => '/',
            'filename' => 'inline.txt',
        ], 'Inline page content file');
        $fs->create_file_from_string([
            'contextid' => (int)$context->id,
            'component' => 'mod_icontent',
            'filearea' => 'bgpage',
            'itemid' => $sourcepageid,
            'filepath' => '/',
            'filename' => 'background.png',
        ], 'PNGDATA');

        $duplicated = \icontent_duplicate_page((int)$icontent->id, (int)$module->cmid, $sourcepageid, $context);
        $newpageid = (int)$duplicated->id;

        $newpagefiles = $fs->get_area_files(
            (int)$context->id,
            'mod_icontent',
            'page',
            $newpageid,
            'filepath, filename',
            false
        );
        $newbgfiles = $fs->get_area_files(
            (int)$context->id,
            'mod_icontent',
            'bgpage',
            $newpageid,
            'filepath, filename',
            false
        );

        $this->assertCount(1, $newpagefiles);
        $this->assertCount(1, $newbgfiles);
        $this->assertSame('inline.txt', reset($newpagefiles)->get_filename());
        $this->assertSame('background.png', reset($newbgfiles)->get_filename());
    }

    /**
     * Create a baseline iContent activity fixture.
     *
     * @return array
     */
    private function create_activity_fixture(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $module = $generator->create_module('icontent', ['course' => $course->id, 'name' => 'Branch test iContent']);
        $icontent = $DB->get_record('icontent', ['id' => $module->id], '*', MUST_EXIST);

        return [$course, $module, $icontent, $student];
    }

    /**
     * Create an iContent page record.
     *
     * @param int $icontentid
     * @param int $cmid
     * @param int $pagenum
     * @param string $title
     * @param array $overrides
     * @return int
     */
    private function create_page(int $icontentid, int $cmid, int $pagenum, string $title, array $overrides = []): int {
        global $DB;

        $time = time();
        $record = (object) array_merge([
            'icontentid' => $icontentid,
            'cmid' => $cmid,
            'coverpage' => 0,
            'title' => $title,
            'branchref' => null,
            'branchname' => null,
            'branchparentpageid' => 0,
            'showtitle' => 1,
            'pageicontent' => $title . ' content',
            'pageicontentformat' => FORMAT_HTML,
            'showbgimage' => 0,
            'bgimage' => null,
            'bgcolor' => '#FCFCFC',
            'layout' => 1,
            'transitioneffect' => '0',
            'bordercolor' => '#E4E4E4',
            'borderwidth' => 1,
            'pagenum' => $pagenum,
            'hidden' => 0,
            'maxnotesperpages' => 15,
            'attemptsallowed' => 0,
            'prevmode' => 0,
            'prevpageid' => 0,
            'nextmode' => 0,
            'nextpageid' => 0,
            'expandnotesarea' => 0,
            'expandquestionsarea' => 0,
            'timecreated' => $time,
            'timemodified' => $time,
        ], $overrides);

        return (int)$DB->insert_record('icontent_pages', $record);
    }
}
