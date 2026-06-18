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
 * Behat steps for mod_icontent.
 *
 * @package    mod_icontent
 * @category   test
 * @copyright  2024 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');
use Behat\Mink\Exception\ExpectationException;
// phpcs:disable moodle.Files.LineLength.MaxExceeded

/**
 * iContent Behat context.
 */
class behat_mod_icontent extends behat_base {
    /** @var array<string, array<string, mixed>> page deletion state captured before a delete action. */
    protected array $trackedpagedeletions = [];

    /**
     * Convert simple page names to URLs for steps like
     * 'When I am on the "[page name]" page'.
     *
     * @param string $page
     * @return moodle_url
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            default:
                throw new Exception('Unrecognised icontent page type "' . $page . '".');
        }
    }

    /**
     * Convert page instance names to URLs for steps like
     * 'When I am on the "[identifier]" "[page type]" page'.
     *
     * @param string $type
     * @param string $identifier
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'view':
                return new moodle_url('/mod/icontent/view.php', ['id' => $this->get_icontent_cm_by_name($identifier)->id]);

            default:
                throw new Exception('Unrecognised icontent page type "' . $type . '".');
        }
    }

    /**
     * Resolve an iContent course module by activity name.
     *
     * @param string $activityname
     * @return stdClass
     */
    protected function get_icontent_cm_by_name(string $activityname): stdClass {
        global $DB;

        $sql = "SELECT cm.*
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {icontent} i ON i.id = cm.instance
                 WHERE m.name = :modname
                   AND i.name = :activityname";

        return $DB->get_record_sql($sql, ['modname' => 'icontent', 'activityname' => $activityname], MUST_EXIST);
    }

    /**
     * Resolve an iContent page by activity and page title.
     *
     * @param string $activityname
     * @param string $pagetitle
     * @return stdClass
     */
    protected function get_icontent_page_by_title(string $activityname, string $pagetitle): stdClass {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activityname);
        $icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);

        return $DB->get_record('icontent_pages', [
            'icontentid' => $icontent->id,
            'cmid' => $cm->id,
            'title' => $pagetitle,
        ], '*', MUST_EXIST);
    }

    /**
     * Build a stable key for tracked page state.
     *
     * @param string $activityname
     * @param string $pagetitle
     * @return string
     */
    protected function get_page_state_key(string $activityname, string $pagetitle): string {
        return $activityname . '::' . $pagetitle;
    }

    /**
     * Escape a string for XPath literal use.
     *
     * @param string $text
     * @return string
     */
    protected function xpath_literal(string $text): string {
        if (strpos($text, "'") === false) {
            return "'" . $text . "'";
        }

        if (strpos($text, '"') === false) {
            return '"' . $text . '"';
        }

        $parts = explode("'", $text);
        return "concat('" . implode("', \"'\", '", $parts) . "')";
    }

    /**
     * Capture page-linked records before triggering a delete action.
     *
     * @param string $activityname
     * @param string $pagetitle
     * @return array<string, mixed>
     */
    protected function capture_page_delete_state(string $activityname, string $pagetitle): array {
        global $DB;

        $page = $this->get_icontent_page_by_title($activityname, $pagetitle);
        $state = [
            'pageid' => (int)$page->id,
            'mappingids' => array_map(
                'intval',
                $DB->get_fieldset_select('icontent_pages_questions', 'id', 'pageid = ?', [$page->id])
            ),
            'noteids' => array_map(
                'intval',
                $DB->get_fieldset_select('icontent_pages_notes', 'id', 'pageid = ?', [$page->id])
            ),
        ];

        $this->trackedpagedeletions[$this->get_page_state_key($activityname, $pagetitle)] = $state;
        return $state;
    }

    /**
     * Get previously captured delete state for a page.
     *
     * @param string $activityname
     * @param string $pagetitle
     * @return array<string, mixed>
     */
    protected function get_tracked_page_delete_state(string $activityname, string $pagetitle): array {
        $key = $this->get_page_state_key($activityname, $pagetitle);
        if (!array_key_exists($key, $this->trackedpagedeletions)) {
            throw new ExpectationException(
                'No tracked delete state found for iContent page "' . $pagetitle . '" in activity "' . $activityname . '".',
                $this->getSession()
            );
        }

        return $this->trackedpagedeletions[$key];
    }

    /**
     * Open a page delete confirmation link by XPath, then confirm it.
     *
     * @param string $xpath
     * @param string $description
     * @return void
     */
    protected function click_delete_link_and_confirm(string $xpath, string $description): void {
        $page = $this->getSession()->getPage();
        $link = $page->find('xpath', $xpath);

        if (!$link) {
            throw new ExpectationException('Could not find delete link for ' . $description . '.', $this->getSession());
        }

        $link->click();

        $confirmlink = $this->getSession()->getPage()->find(
            'xpath',
            "//a[contains(@href, '/mod/icontent/delete.php') and contains(@href, 'confirm=1')]"
        );

        if (!$confirmlink) {
            throw new ExpectationException('Could not find confirmation link for ' . $description . '.', $this->getSession());
        }

        $confirmlink->click();
    }

    /**
     * Create a simple iContent page for a named activity.
     *
     * @Given /^the icontent "(?P<activity>[^"]*)" has a page titled "(?P<title>[^"]*)" with content "(?P<content>[^"]*)"$/
     *
     * @param string $activity
     * @param string $title
     * @param string $content
     */
    public function the_icontent_has_a_page_titled_with_content(string $activity, string $title, string $content): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);
        $icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);

        $maxpagenum = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(pagenum), 0) FROM {icontent_pages} WHERE icontentid = ?',
            [$icontent->id]
        );
        $timecreated = time();

        $record = (object)[
            'icontentid' => $icontent->id,
            'cmid' => $cm->id,
            'coverpage' => 0,
            'title' => $title,
            'showtitle' => 1,
            'pageicontent' => $content,
            'pageicontentformat' => FORMAT_HTML,
            'showbgimage' => 0,
            'bgimage' => null,
            'bgcolor' => $icontent->bgcolor ?? '#FCFCFC',
            'layout' => 1,
            'transitioneffect' => '0',
            'bordercolor' => $icontent->bordercolor ?? '#E4E4E4',
            'borderwidth' => (int)($icontent->borderwidth ?? 1),
            'pagenum' => $maxpagenum + 1,
            'hidden' => 0,
            'maxnotesperpages' => (int)($icontent->maxnotesperpages ?? 15),
            'attemptsallowed' => 0,
            'expandnotesarea' => 0,
            'expandquestionsarea' => 0,
            'timecreated' => $timecreated,
            'timemodified' => $timecreated,
        ];

        $DB->insert_record('icontent_pages', $record);
    }

    /**
     * Add a note plus a like for an iContent page.
     *
     * @Given /^the icontent "([^"]*)" page "([^"]*)" has a note "([^"]*)" by "([^"]*)" liked by "([^"]*)"$/
     *
     * @param string $activity
     * @param string $pagetitle
     * @param string $comment
     * @param string $username
     * @param string $likeusername
     */
    public function the_icontent_page_has_a_note_liked_by(
        string $activity,
        string $pagetitle,
        string $comment,
        string $username,
        string $likeusername
    ): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);
        $page = $this->get_icontent_page_by_title($activity, $pagetitle);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $likeuser = $DB->get_record('user', ['username' => $likeusername], '*', MUST_EXIST);
        $timecreated = time();

        $noteid = $DB->insert_record('icontent_pages_notes', (object)[
            'pageid' => $page->id,
            'userid' => $user->id,
            'cmid' => $cm->id,
            'comment' => $comment,
            'timecreated' => $timecreated,
            'timemodified' => $timecreated,
            'tab' => 'note',
            'path' => '0',
            'parent' => 0,
            'private' => 0,
            'featured' => 0,
            'doubttutor' => 0,
        ], true);

        $DB->insert_record('icontent_pages_notes_like', (object)[
            'pagenoteid' => $noteid,
            'userid' => $likeuser->id,
            'cmid' => $cm->id,
            'timemodified' => $timecreated,
            'visible' => 1,
        ]);
    }

    /**
     * Navigate to the iContent manual review page by activity name.
     *
     * @Given /^I am on the "(?P<activity_string>(?:[^"\\]|\\.)*)" icontent manual review page$/
     *
     * @param string $activity
     */
    public function i_am_on_the_icontent_manual_review_page(string $activity): void {
        $cm = $this->get_icontent_cm_by_name($activity);
        $url = new moodle_url('/mod/icontent/grading.php', ['id' => $cm->id, 'action' => 'grading']);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Link an existing question to an iContent page and create an evaluated attempt with reviewer comment.
     *
     * @Given /^the icontent "(?P<activity>[^"]*)" page "(?P<pagetitle>[^"]*)" links question "(?P<questionname>[^"]*)" and has an evaluated attempt for "(?P<username>[^"]*)" with answer "(?P<answer>[^"]*)" and teacher comment "(?P<comment>[^"]*)"$/
     *
     * @param string $activity
     * @param string $pagetitle
     * @param string $questionname
     * @param string $username
     * @param string $answer
     * @param string $comment
     */
    public function the_icontent_page_links_question_with_evaluated_attempt(
        string $activity,
        string $pagetitle,
        string $questionname,
        string $username,
        string $answer,
        string $comment
    ): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);
        $page = $this->get_icontent_page_by_title($activity, $pagetitle);
        $question = $DB->get_record('question', ['name' => $questionname], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        $timecreated = time();

        $pagesquestion = $DB->get_record('icontent_pages_questions', [
            'pageid' => $page->id,
            'questionid' => $question->id,
            'cmid' => $cm->id,
        ]);

        if (!$pagesquestion) {
            $pagesquestion = (object)[
                'pageid' => $page->id,
                'questionid' => $question->id,
                'cmid' => $cm->id,
                'timecreated' => $timecreated,
                'timemodified' => $timecreated,
                'maxmark' => 1,
                'remake' => 0,
                'qtype' => $question->qtype,
            ];
            $pagesquestion->id = $DB->insert_record('icontent_pages_questions', $pagesquestion);
        }

        $attempt = (object)[
            'pagesquestionsid' => $pagesquestion->id,
            'questionid' => $question->id,
            'userid' => $user->id,
            'cmid' => $cm->id,
            'fraction' => 1,
            'rightanswer' => 'evaluated',
            'answertext' => $answer,
            'reviewercomment' => $comment,
            'reviewercommentformat' => FORMAT_HTML,
            'timecreated' => $timecreated,
        ];
        $DB->insert_record('icontent_question_attempts', $attempt);
    }

    /**
     * Link an existing question to an iContent page and create a pending manual-review attempt.
     *
     * @Given /^the icontent "(?P<activity>[^"]*)" page "(?P<pagetitle>[^"]*)" links question "(?P<questionname>[^"]*)" and has a pending attempt for "(?P<username>[^"]*)" with answer "(?P<answer>[^"]*)"$/
     *
     * @param string $activity
     * @param string $pagetitle
     * @param string $questionname
     * @param string $username
     * @param string $answer
     */
    public function the_icontent_page_links_question_with_pending_attempt(
        string $activity,
        string $pagetitle,
        string $questionname,
        string $username,
        string $answer
    ): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);
        $page = $this->get_icontent_page_by_title($activity, $pagetitle);
        $question = $DB->get_record('question', ['name' => $questionname], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        $timecreated = time();

        $pagesquestion = $DB->get_record('icontent_pages_questions', [
            'pageid' => $page->id,
            'questionid' => $question->id,
            'cmid' => $cm->id,
        ]);

        if (!$pagesquestion) {
            $pagesquestion = (object)[
                'pageid' => $page->id,
                'questionid' => $question->id,
                'cmid' => $cm->id,
                'timecreated' => $timecreated,
                'timemodified' => $timecreated,
                'maxmark' => 1,
                'remake' => 0,
                'qtype' => $question->qtype,
            ];
            $pagesquestion->id = $DB->insert_record('icontent_pages_questions', $pagesquestion);
        }

        $attempt = (object)[
            'pagesquestionsid' => $pagesquestion->id,
            'questionid' => $question->id,
            'userid' => $user->id,
            'cmid' => $cm->id,
            'fraction' => 0,
            'rightanswer' => 'toevaluate',
            'answertext' => $answer,
            'reviewercomment' => '',
            'reviewercommentformat' => FORMAT_HTML,
            'timecreated' => $timecreated,
        ];
        $DB->insert_record('icontent_question_attempts', $attempt);
    }

    /**
     * Link an existing question to an iContent page and create an auto-graded attempt.
     *
     * @Given /^the icontent "(?P<activity>[^"]*)" page "(?P<pagetitle>[^"]*)" links question "(?P<questionname>[^"]*)" and has a graded attempt for "(?P<username>[^"]*)" with answer "(?P<answer>[^"]*)"$/
     *
     * @param string $activity
     * @param string $pagetitle
     * @param string $questionname
     * @param string $username
     * @param string $answer
     */
    public function the_icontent_page_links_question_with_graded_attempt(
        string $activity,
        string $pagetitle,
        string $questionname,
        string $username,
        string $answer
    ): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);
        $page = $this->get_icontent_page_by_title($activity, $pagetitle);
        $question = $DB->get_record('question', ['name' => $questionname], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        $timecreated = time();

        $pagesquestion = $DB->get_record('icontent_pages_questions', [
            'pageid' => $page->id,
            'questionid' => $question->id,
            'cmid' => $cm->id,
        ]);

        if (!$pagesquestion) {
            $pagesquestion = (object)[
                'pageid' => $page->id,
                'questionid' => $question->id,
                'cmid' => $cm->id,
                'timecreated' => $timecreated,
                'timemodified' => $timecreated,
                'maxmark' => 1,
                'remake' => 0,
                'qtype' => $question->qtype,
            ];
            $pagesquestion->id = $DB->insert_record('icontent_pages_questions', $pagesquestion);
        }

        $attempt = (object)[
            'pagesquestionsid' => $pagesquestion->id,
            'questionid' => $question->id,
            'userid' => $user->id,
            'cmid' => $cm->id,
            'fraction' => 1,
            'rightanswer' => 'correct',
            'answertext' => $answer,
            'reviewercomment' => '',
            'reviewercommentformat' => FORMAT_HTML,
            'timecreated' => $timecreated,
        ];
        $DB->insert_record('icontent_question_attempts', $attempt);
    }

    /**
     * Link an existing question to an iContent page without creating attempts.
     *
     * @Given /^the icontent "([^"]*)" page "([^"]*)" links question "([^"]*)"$/
     *
     * @param string $activity
     * @param string $pagetitle
     * @param string $questionname
     */
    public function the_icontent_page_links_question(
        string $activity,
        string $pagetitle,
        string $questionname
    ): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);
        $page = $this->get_icontent_page_by_title($activity, $pagetitle);
        $question = $DB->get_record('question', ['name' => $questionname], '*', MUST_EXIST);

        $exists = $DB->record_exists('icontent_pages_questions', [
            'pageid' => $page->id,
            'questionid' => $question->id,
            'cmid' => $cm->id,
        ]);

        if ($exists) {
            return;
        }

        $timecreated = time();
        $DB->insert_record('icontent_pages_questions', (object)[
            'pageid' => $page->id,
            'questionid' => $question->id,
            'cmid' => $cm->id,
            'timecreated' => $timecreated,
            'timemodified' => $timecreated,
            'maxmark' => 1,
            'remake' => 0,
            'qtype' => $question->qtype,
        ]);
    }

    /**
     * Remove a linked question from an iContent page as the current user.
     *
     * @When /^I remove question "([^"]*)" from page "([^"]*)" in icontent "([^"]*)"$/
     *
     * @param string $questionname
     * @param string $pagetitle
     * @param string $activity
     */
    public function i_remove_question_from_page_in_icontent(
        string $questionname,
        string $pagetitle,
        string $activity
    ): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);
        $page = $this->get_icontent_page_by_title($activity, $pagetitle);
        $question = $DB->get_record('question', ['name' => $questionname], '*', MUST_EXIST);

        $mapping = $DB->get_record('icontent_pages_questions', [
            'pageid' => $page->id,
            'questionid' => $question->id,
            'cmid' => $cm->id,
        ], '*', MUST_EXIST);

        $url = new moodle_url('/mod/icontent/view.php', ['id' => $cm->id, 'pageid' => $page->id]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));

        $link = $this->getSession()->getPage()->find(
            'xpath',
            "//a[contains(@href, '/mod/icontent/view.php') and contains(@href, 'removeqpid={$mapping->id}') ]"
        );

        if (!$link) {
            throw new ExpectationException(
                'Could not find remove-question link for mapping id ' . $mapping->id . '.',
                $this->getSession()
            );
        }

        $link->click();
    }

    /**
     * Delete a page from the in-page toolbar and confirm the deletion.
     *
     * @When /^I delete page "([^"]*)" from the toolbar in icontent "([^"]*)"$/
     *
     * @param string $pagetitle
     * @param string $activity
     */
    public function i_delete_page_from_the_toolbar_in_icontent(string $pagetitle, string $activity): void {
        $cm = $this->get_icontent_cm_by_name($activity);
        $page = $this->get_icontent_page_by_title($activity, $pagetitle);
        $this->capture_page_delete_state($activity, $pagetitle);

        $url = new moodle_url('/mod/icontent/view.php', ['id' => $cm->id, 'pageid' => $page->id]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));

        $this->click_delete_link_and_confirm(
            "//div[contains(@class, 'toolbarpage')]//a[contains(@class, 'icon-deletepage')]",
            'toolbar page delete for "' . $pagetitle . '"'
        );
    }

    /**
     * Delete a page from the TOC actions list and confirm the deletion.
     *
     * @When /^I delete page "([^"]*)" from the TOC in icontent "([^"]*)"$/
     *
     * @param string $pagetitle
     * @param string $activity
     */
    public function i_delete_page_from_the_toc_in_icontent(string $pagetitle, string $activity): void {
        $cm = $this->get_icontent_cm_by_name($activity);
        $this->capture_page_delete_state($activity, $pagetitle);

        $url = new moodle_url('/mod/icontent/view.php', ['id' => $cm->id]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));

        $pagetitleliteral = $this->xpath_literal(trim($pagetitle));
        $this->click_delete_link_and_confirm(
            "//div[contains(@class, 'block_icontent_toc')]//li[.//a[contains(@class, 'load-page') and normalize-space(.) = {$pagetitleliteral}]]//div[contains(@class, 'action-list')]//a[contains(@href, '/mod/icontent/delete.php')]",
            'TOC page delete for "' . $pagetitle . '"'
        );
    }

    /**
     * Assert that an iContent activity includes at least one linked question of a given qtype.
     *
     * @Then /^the icontent "(?P<activity_string>[^"]*)" should include question type "(?P<qtype_string>[^"]*)"$/
     *
     * @param string $activity
     * @param string $qtype
     */
    public function the_icontent_should_include_question_type(string $activity, string $qtype): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);

        $sql = "SELECT 1
                  FROM {icontent_pages_questions} pq
                  JOIN {question} q ON q.id = pq.questionid
                 WHERE pq.cmid = ?
                   AND q.qtype = ?";
        $exists = $DB->record_exists_sql($sql, [$cm->id, $qtype]);

        if (!$exists) {
            throw new ExpectationException(
                'iContent activity "' . $activity . '" does not include question type "' . $qtype . '".',
                $this->getSession()
            );
        }
    }

    /**
     * Assert that an iContent activity includes a specific page/question/qtype mapping.
     *
     * @Then /^the icontent "([^"]*)" should include page "([^"]*)" with question "([^"]*)" of type "([^"]*)"$/
     *
     * @param string $activity
     * @param string $pagetitle
     * @param string $questionname
     * @param string $qtype
     */
    public function the_icontent_should_include_page_with_question_of_type(
        string $activity,
        string $pagetitle,
        string $questionname,
        string $qtype
    ): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);

        $sql = "SELECT 1
                  FROM {icontent_pages} p
                  JOIN {icontent_pages_questions} pq ON pq.pageid = p.id
                  JOIN {question} q ON q.id = pq.questionid
                 WHERE p.cmid = ?
                   AND p.title = ?
                   AND q.name = ?
                   AND q.qtype = ?";
        $exists = $DB->record_exists_sql($sql, [$cm->id, $pagetitle, $questionname, $qtype]);

        if (!$exists) {
            throw new ExpectationException(
                'Expected mapping not found in iContent "' . $activity . '": page "' . $pagetitle .
                '", question "' . $questionname . '", qtype "' . $qtype . '".',
                $this->getSession()
            );
        }
    }

    /**
     * Assert that an iContent activity does not include a specific page/question mapping.
     *
     * @Then /^the icontent "([^"]*)" should not include page "([^"]*)" with question "([^"]*)"$/
     *
     * @param string $activity
     * @param string $pagetitle
     * @param string $questionname
     */
    public function the_icontent_should_not_include_page_with_question(
        string $activity,
        string $pagetitle,
        string $questionname
    ): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);

        $sql = "SELECT 1
                  FROM {icontent_pages} p
                  JOIN {icontent_pages_questions} pq ON pq.pageid = p.id
                  JOIN {question} q ON q.id = pq.questionid
                 WHERE p.cmid = ?
                   AND p.title = ?
                   AND q.name = ?";
        $exists = $DB->record_exists_sql($sql, [$cm->id, $pagetitle, $questionname]);

        if ($exists) {
            throw new ExpectationException(
                'Unexpected mapping exists in iContent "' . $activity . '": page "' . $pagetitle .
                '", question "' . $questionname . '".',
                $this->getSession()
            );
        }
    }

    /**
     * Assert that a deleted page and its related records were removed.
     *
     * @Then /^the icontent "([^"]*)" page "([^"]*)" should be fully deleted$/
     *
     * @param string $activity
     * @param string $pagetitle
     */
    public function the_icontent_page_should_be_fully_deleted(string $activity, string $pagetitle): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);
        $icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);
        $state = $this->get_tracked_page_delete_state($activity, $pagetitle);
        $pageid = (int)$state['pageid'];

        if ($DB->record_exists('icontent_pages', ['id' => $pageid])) {
            throw new ExpectationException(
                'Deleted page record still exists for iContent page "' . $pagetitle . '".',
                $this->getSession()
            );
        }

        if ($DB->record_exists('icontent_pages', ['icontentid' => $icontent->id, 'title' => $pagetitle])) {
            throw new ExpectationException(
                'Page title "' . $pagetitle . '" still exists in iContent activity "' . $activity . '".',
                $this->getSession()
            );
        }

        if ($DB->record_exists('icontent_pages_questions', ['pageid' => $pageid])) {
            throw new ExpectationException(
                'Question links still exist for deleted iContent page "' . $pagetitle . '".',
                $this->getSession()
            );
        }

        if ($DB->record_exists('icontent_pages_notes', ['pageid' => $pageid])) {
            throw new ExpectationException(
                'Notes still exist for deleted iContent page "' . $pagetitle . '".',
                $this->getSession()
            );
        }

        $mappingids = $state['mappingids'];
        if (!empty($mappingids)) {
            [$insql, $params] = $DB->get_in_or_equal($mappingids);
            if ($DB->record_exists_select('icontent_question_attempts', 'pagesquestionsid ' . $insql, $params)) {
                throw new ExpectationException(
                    'Question attempts still exist for deleted iContent page "' . $pagetitle . '".',
                    $this->getSession()
                );
            }
        }

        $noteids = $state['noteids'];
        if (!empty($noteids)) {
            [$insql, $params] = $DB->get_in_or_equal($noteids);
            if ($DB->record_exists_select('icontent_pages_notes_like', 'pagenoteid ' . $insql, $params)) {
                throw new ExpectationException(
                    'Note likes still exist for deleted iContent page "' . $pagetitle . '".',
                    $this->getSession()
                );
            }
        }
    }
}
// phpcs:enable moodle.Files.LineLength.MaxExceeded
